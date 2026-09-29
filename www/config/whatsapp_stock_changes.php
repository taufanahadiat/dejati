<?php
function waChangeQuery(mysqli $db, string $sql, string $types = '', array $params = []): mysqli_stmt {
    $s=$db->prepare($sql);
    if ($types !== '') $s->bind_param($types, ...$params);
    $s->execute();
    return $s;
}
function waChangeItem(mysqli $db, int $id): array {
    $item=waChangeQuery($db,'SELECT id,name,unit,initial_quantity FROM stock_items WHERE id=? AND active=1 FOR UPDATE','i',[$id])->get_result()->fetch_assoc();
    if (!$item) throw new InvalidArgumentException('Item stok tidak ditemukan atau tidak aktif.');
    $movement=waChangeQuery($db,'SELECT COALESCE(SUM(quantity_delta),0) delta, COALESCE(MAX(id),0) revision FROM stock_movements WHERE stock_item_id=?','i',[$id])->get_result()->fetch_assoc();
    $item['quantity']=round((float)$item['initial_quantity']+(float)$movement['delta'],3);
    $item['revision']=(int)$movement['revision'];
    return $item;
}
function waChangeTarget(string $mode, float $amount, float $current): float {
    $target=round($mode==='set' ? $amount : $current+($mode==='add' ? $amount : -$amount),3);
    if (abs($target)>999999999) throw new InvalidArgumentException('Jumlah stok di luar batas.');
    return $target;
}
function waChangeResult(array $row, string $status): array {
    return ['status'=>$status,'token'=>$row['token'],'name'=>$row['item_name'],'unit'=>$row['unit'],
        'old'=>(float)$row['old_quantity'],'new'=>(float)$row['new_quantity'],
        'delta'=>round((float)$row['new_quantity']-(float)$row['old_quantity'],3)];
}
// Caller owns the transaction. All writes and the movement ledger commit together.
function whatsappStockChange(mysqli $db, array $input): array {
    $chat=$input['chat'] ?? ''; $sender=$input['sender'] ?? ''; $action=$input['action'] ?? '';
    foreach ([$chat,$sender] as $id) if (!is_string($id) || !preg_match('/^[0-9-]+@(c\.us|lid|g\.us)$/D',$id)) throw new InvalidArgumentException('Identitas chat tidak valid.');
    if (str_ends_with($sender,'@g.us')) throw new InvalidArgumentException('Pengirim tidak valid.');
    $token=$input['token'] ?? '';
    if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token)) throw new InvalidArgumentException('Token permintaan tidak valid.');
    $row=waChangeQuery($db,'SELECT * FROM whatsapp_stock_changes WHERE token=? FOR UPDATE','s',[$token])->get_result()->fetch_assoc();
    if ($row && ($row['chat_id']!==$chat || $row['sender_id']!==$sender)) throw new InvalidArgumentException('Konfirmasi harus dari pengguna dan chat yang sama.');
    if ($action==='prepare') {
        if ($row) return waChangeResult($row,$row['state']);
        $mode=$input['mode'] ?? '';
        $raw=$input['amount'] ?? null;
        if (!in_array($mode,['set','add','subtract'],true) || !is_string($raw) || !preg_match('/^-?\d+(?:\.\d{1,3})?$/D',$raw) || abs((float)$raw)>999999999 || ($mode!=='set' && (float)$raw<0)) throw new InvalidArgumentException('Jumlah tidak valid.');
        $id=filter_var($input['itemId'] ?? null,FILTER_VALIDATE_INT);
        if (!$id) throw new InvalidArgumentException('Item tidak valid.');
        $item=waChangeItem($db,$id);
        if (!empty($input['unit']) && $input['unit']!==$item['unit']) throw new InvalidArgumentException('Satuan tidak cocok. Gunakan '.$item['unit'].'.');
        $old=$item['quantity'];$new=waChangeTarget($mode,(float)$raw,$old);
        waChangeQuery($db,"UPDATE whatsapp_stock_changes SET state='cancelled' WHERE chat_id=? AND sender_id=? AND state='pending'",'ss',[$chat,$sender]);
        waChangeQuery($db,"INSERT INTO whatsapp_stock_changes (token,chat_id,sender_id,stock_item_id,item_name,unit,mode,amount,old_quantity,new_quantity,movement_revision,expires_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE))",'sssisssdddi',[$token,$chat,$sender,$id,$item['name'],$item['unit'],$mode,(float)$raw,$old,$new,$item['revision']]);
        return waChangeResult(['token'=>$token,'item_name'=>$item['name'],'unit'=>$item['unit'],'old_quantity'=>$old,'new_quantity'=>$new],'pending');
    }
    if (!$row) return ['status'=>'missing'];
    if ($action==='cancel') {
        if ($row['state']==='pending') waChangeQuery($db,"UPDATE whatsapp_stock_changes SET state='cancelled' WHERE token=?",'s',[$token]);
        return $row['state']==='applied' ? waChangeResult($row,'applied') : ['status'=>'cancelled'];
    }
    if ($action!=='confirm') throw new InvalidArgumentException('Aksi tidak valid.');
    $confirmation=$input['confirmationId'] ?? '';
    if (!is_string($confirmation) || !preg_match('/^[a-f0-9]{64}$/D',$confirmation)) throw new InvalidArgumentException('ID konfirmasi tidak valid.');
    if ($row['last_confirmation_id']===$confirmation && $row['last_result']) return json_decode($row['last_result'],true);
    if ($row['state']==='applied') return waChangeResult($row,'applied');
    if ($row['state']!=='pending') return ['status'=>$row['state']];
    if (strtotime($row['expires_at'])<=time()) {
        waChangeQuery($db,"UPDATE whatsapp_stock_changes SET state='expired' WHERE token=?",'s',[$token]);
        return ['status'=>'expired'];
    }
    $item=waChangeItem($db,(int)$row['stock_item_id']);
    if ($item['name']!==$row['item_name'] || $item['unit']!==$row['unit']) {
        waChangeQuery($db,"UPDATE whatsapp_stock_changes SET state='cancelled' WHERE token=?",'s',[$token]);
        return ['status'=>'item_changed'];
    }
    if ($item['revision']!==(int)$row['movement_revision'] || abs($item['quantity']-(float)$row['old_quantity'])>0.0001) {
        $row['old_quantity']=$item['quantity'];
        $row['new_quantity']=waChangeTarget($row['mode'],(float)$row['amount'],$item['quantity']);
        $result=waChangeResult($row,'changed');
        waChangeQuery($db,"UPDATE whatsapp_stock_changes SET old_quantity=?,new_quantity=?,movement_revision=?,last_confirmation_id=?,last_result=?,expires_at=DATE_ADD(NOW(),INTERVAL 10 MINUTE) WHERE token=?",'ddisss',[$row['old_quantity'],$row['new_quantity'],$item['revision'],$confirmation,json_encode($result),$token]);
        return $result;
    }
    $result=waChangeResult($row,'applied');
    $delta=$result['delta'];
    if (abs($delta)>=0.0005) {
        $stockIn=round(max(0,$result['new']-max(0,$result['old'])),3);
        $note='WhatsApp '.$sender.'; '.$result['old'].' -> '.$result['new'];
        waChangeQuery($db,"INSERT INTO stock_movements (stock_item_id,movement_type,quantity_delta,stock_in_quantity,movement_key,note) VALUES (?,'adjustment',?,?,?,?)",'iddss',[(int)$row['stock_item_id'],$delta,$stockIn,'whatsapp:'.$token,$note]);
    }
    waChangeQuery($db,"UPDATE whatsapp_stock_changes SET state='applied',last_confirmation_id=?,last_result=? WHERE token=?",'sss',[$confirmation,json_encode($result),$token]);
    return $result;
}
