<?php
/** Olsera snapshots are cumulative per store/day. Stock is reconciled by delta. */
function olseraQuery(mysqli $db, string $sql, string $types='', array $args=[]): mysqli_stmt {
    $s=$db->prepare($sql); if ($types!=='') $s->bind_param($types,...$args); $s->execute(); return $s;
}
function olseraDate(string $date): string {
    $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date);
    if (!$d || $d->format('Y-m-d')!==$date || $date>date('Y-m-d')) throw new InvalidArgumentException('Tanggal Olsera tidak valid.');
    return $date;
}
function olseraNormalize(string $value): string {
    return strtolower(preg_replace('/\s+/u',' ',trim(html_entity_decode($value,ENT_QUOTES|ENT_HTML5,'UTF-8'))));
}
function olseraEnqueue(mysqli $db, string $date, bool $refresh=false): void {
    olseraQuery($db,'INSERT IGNORE INTO olsera_sync_jobs(sales_date) VALUES (?)','s',[$date]);
    if ($refresh) olseraQuery($db,"UPDATE olsera_sync_jobs SET state='pending',requested_at=NOW(),next_attempt_at=NOW(),completed_at=NULL,attempts=0 WHERE sales_date=? AND state='succeeded'",'s',[$date]);
}
function olseraClaim(mysqli $db): ?array {
    $row=$db->query("SELECT * FROM olsera_sync_jobs WHERE (state IN ('pending','failed') AND next_attempt_at<=NOW()) OR (state='running' AND lease_until<NOW()) ORDER BY sales_date LIMIT 1 FOR UPDATE SKIP LOCKED")->fetch_assoc();
    if (!$row) return null;
    $token=bin2hex(random_bytes(32));
    olseraQuery($db,"UPDATE olsera_sync_jobs SET state='running',attempts=attempts+1,lease_token=?,lease_until=DATE_ADD(NOW(),INTERVAL 10 MINUTE),last_error=NULL WHERE sales_date=?",'ss',[$token,$row['sales_date']]);
    return ['date'=>$row['sales_date'],'lease'=>$token];
}
function olseraCatalog(mysqli $db): array {
    $catalog=[];
    foreach (['cafe'=>['tb_datacafe','id_prod','nama_prod'],'carwash'=>['tb_datacarwash','id_produk','produk'],'detailing'=>['tb_datadetailing','id_produk','produk']] as $business=>[$table,$id,$name]) {
        foreach ($db->query("SELECT $id id,$name name FROM $table") as $p) $catalog[olseraNormalize($p['name'])][]=['business'=>$business,'id'=>(int)$p['id']];
    }
    return $catalog;
}
function olseraMatch(array $row, array $catalog): array {
    $name=olseraNormalize($row['product']); $variant=olseraNormalize($row['variant']); $group=olseraNormalize($row['group']);
    $expected=preg_match('/detail/',$group)?'detailing':(preg_match('/car\s*wash|cuci/',$group)?'carwash':null);
    $matches=[];
    foreach (array_unique([$variant!==''?$name.' - '.$variant:$name,$name]) as $key) {
        $matches=$catalog[$key]??[];
        if ($expected) $matches=array_values(array_filter($matches,fn($p)=>$p['business']===$expected));
        if (count($matches)===1) return $matches[0];
        if (count($matches)>1) break;
    }
    throw new InvalidArgumentException('Produk Olsera belum cocok atau ambigu: '.$row['product'].($row['variant']!==''?' / '.$row['variant']:''));
}
function olseraValidateRows(array $rows, array $catalog): array {
    if (!array_is_list($rows)||count($rows)>10000) throw new InvalidArgumentException('Baris Excel tidak valid.');
    $out=[];
    foreach ($rows as $r) {
        foreach (['product','variant','group','sku'] as $k) if (!isset($r[$k])||!is_string($r[$k])||strlen($r[$k])>255) throw new InvalidArgumentException('Kolom produk Excel tidak valid.');
        if (trim($r['product'])==='' || ($r['currency']??'')!=='IDR') throw new InvalidArgumentException('Produk/mata uang Excel tidak valid.');
        foreach (['quantity','gross_sales','discount_amount','return_amount','total_sales'] as $k) {
            if (!isset($r[$k])||!is_numeric($r[$k])||!is_finite((float)$r[$k])||abs((float)$r[$k])>100000000000) throw new InvalidArgumentException('Angka Excel tidak valid.');
            $r[$k]=round((float)$r[$k],$k==='quantity'?3:2);
        }
        if (abs($r['quantity'])>1000000) throw new InvalidArgumentException('Jumlah produk di luar batas.');
        // The exported sold quantity is the stock basis. Returns are retained separately for audit.
        if ($r['return_amount']!=0) throw new InvalidArgumentException('Excel memuat retur; kuantitas retur perlu diperiksa sebelum stok dikurangi.');
        $r['match']=olseraMatch($r,$catalog);
        $key=hash('sha256',json_encode(array_map('olseraNormalize',[$r['product'],$r['variant'],$r['group'],$r['sku']])));
        if (isset($out[$key])) foreach (['quantity','gross_sales','discount_amount','return_amount','total_sales'] as $k) $out[$key][$k]+=$r[$k];
        else $out[$key]=$r;
    }
    return $out;
}
function olseraSummary(mysqli $db, string $date): array {
    $totals=['cafe'=>0.0,'carwash'=>0.0,'detailing'=>0.0,'total'=>0.0];
    $items=olseraQuery($db,'SELECT product_name,variant,business,quantity,total_sales FROM olsera_sales WHERE sales_date=? AND (quantity<>0 OR total_sales<>0) ORDER BY business,product_name,variant','s',[$date])->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($items as $r) {$totals[$r['business']]+=(float)$r['total_sales'];$totals['total']+=(float)$r['total_sales'];}
    return $totals+['items'=>$items];
}
// Caller owns the transaction and the job lock. No network IO occurs here.
function olseraImport(mysqli $db, array $input): array {
    $date=olseraDate((string)($input['date']??''));
    $job=olseraQuery($db,'SELECT * FROM olsera_sync_jobs WHERE sales_date=? FOR UPDATE','s',[$date])->get_result()->fetch_assoc();
    if (!$job || $job['state']!=='running' || !hash_equals($job['lease_token']??'',(string)($input['lease']??'')) || $job['lease_until']<date('Y-m-d H:i:s')) throw new RuntimeException('Lease impor kedaluwarsa.');
    if (($input['store']??'')!=='dejaticoffeegarden.myolsera.com' || ($input['report_date']??'')!==$date || !preg_match('/^[a-f0-9]{64}$/D',(string)($input['sha256']??''))) throw new InvalidArgumentException('Identitas file Olsera tidak sesuai.');
    $file=(string)($input['file_name']??'');
    if (basename($file)!==$file||strlen($file)>255||!str_ends_with($file,'.xlsx')||!str_contains($file,$date.'__'.$date)) throw new InvalidArgumentException('Nama/tanggal Excel tidak sesuai.');
    $fetched=DateTimeImmutable::createFromFormat(DATE_ATOM,(string)($input['fetched_at']??''));
    if (!$fetched || $fetched->getTimestamp()>time()+60 || $fetched->getTimestamp()<strtotime($job['requested_at'])-60) throw new InvalidArgumentException('Waktu pengambilan Excel tidak sesuai.');
    $rows=olseraValidateRows($input['rows']??[],olseraCatalog($db));
    if (!$rows && empty($input['confirmed_empty'])) throw new InvalidArgumentException('Laporan kosong belum terverifikasi.');
    $total=array_sum(array_column($rows,'total_sales'));
    $fetchTime=$fetched->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i:s');
    olseraQuery($db,'INSERT INTO olsera_imports(sales_date,file_sha256,file_name,fetched_at,row_count,total_sales,source_rows) VALUES (?,?,?,?,?,?,?)','ssssids',[$date,$input['sha256'],$file,$fetchTime,count($rows),$total,json_encode($input['rows'],JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)]);
    $importId=$db->insert_id;
    $old=olseraQuery($db,'SELECT * FROM olsera_sales WHERE sales_date=? FOR UPDATE','s',[$date])->get_result()->fetch_all(MYSQLI_ASSOC);
    $oldByKey=array_column($old,null,'row_key');
    foreach ($oldByKey as $key=>$prior) if (!isset($rows[$key])) $rows[$key]=['product'=>$prior['product_name'],'variant'=>$prior['variant'],'group'=>$prior['product_group'],'sku'=>$prior['sku'],'quantity'=>0,'gross_sales'=>0,'discount_amount'=>0,'return_amount'=>0,'total_sales'=>0,'match'=>['business'=>$prior['business'],'id'=>(int)$prior['product_id']]];
    // Lock stock items in a consistent order, including rows used by parallel POS/WhatsApp adjustments.
    $db->query('SELECT id FROM stock_items ORDER BY id FOR UPDATE')->fetch_all();
    foreach ($rows as $key=>$r) {
        $prior=$oldByKey[$key]??null;
        if ($prior && ($prior['business']!==$r['match']['business']||(int)$prior['product_id']!==$r['match']['id'])) throw new InvalidArgumentException('Pemetaan produk berubah; periksa impor sebelumnya.');
        olseraQuery($db,'INSERT INTO olsera_sales(sales_date,row_key,product_name,variant,product_group,sku,business,product_id,quantity,gross_sales,discount_amount,return_amount,total_sales,import_id) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity),gross_sales=VALUES(gross_sales),discount_amount=VALUES(discount_amount),return_amount=VALUES(return_amount),total_sales=VALUES(total_sales),import_id=VALUES(import_id)',
            'sssssssidddddi',[$date,$key,html_entity_decode($r['product'],ENT_QUOTES|ENT_HTML5,'UTF-8'),$r['variant'],$r['group'],$r['sku'],$r['match']['business'],$r['match']['id'],$r['quantity'],$r['gross_sales'],$r['discount_amount'],$r['return_amount'],$r['total_sales'],$importId]);
        $saleId=$prior?(int)$prior['id']:$db->insert_id;
        if (!$prior && $r['match']['business']==='cafe') olseraQuery($db,'INSERT INTO olsera_stock_usage(sale_id,stock_item_id,quantity_per_sale) SELECT ?,stock_item_id,quantity_per_sale FROM stock_product_bindings WHERE product_id=?','ii',[$saleId,$r['match']['id']]);
        $bindings=olseraQuery($db,'SELECT * FROM olsera_stock_usage WHERE sale_id=? ORDER BY stock_item_id','i',[$saleId])->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($bindings as $b) {
            $desired=round($r['quantity']*(float)$b['quantity_per_sale'],3);$delta=round((float)$b['applied_quantity']-$desired,3);
            if (abs($delta)<0.0005) continue;
            $movementKey='olsera:'.$importId.':'.$saleId.':'.$b['stock_item_id'];
            $note=substr('Olsera '.$date.' - '.$r['product'].' '.$r['variant'],0,250);
            olseraQuery($db,"INSERT INTO stock_movements(stock_item_id,movement_type,quantity_delta,movement_key,note) VALUES (?,?,?,?,?)",'isdss',[(int)$b['stock_item_id'],$delta<0?'sale':'cancel',$delta,$movementKey,$note]);
            olseraQuery($db,'UPDATE olsera_stock_usage SET applied_quantity=? WHERE sale_id=? AND stock_item_id=?','dii',[$desired,$saleId,(int)$b['stock_item_id']]);
        }
    }
    $summary=olseraSummary($db,$date);
    // Freeze both messages only after all deductions commit. Never alter a message already claimed/sent.
    $notification=olseraQuery($db,'SELECT * FROM whatsapp_closing_notifications WHERE closing_date=? FOR UPDATE','s',[$date])->get_result()->fetch_assoc();
    if ($notification && $notification['summary_state']==='pending' && $notification['stock_state']==='pending') {
        $payload=json_decode($notification['payload'],true,512,JSON_THROW_ON_ERROR);
        $payload['olsera']=$summary+['fetchedAt'=>$fetchTime,'importId'=>$importId];
        $payload['combined']=['total'=>(float)$payload['total_penjualan']+$summary['total']];
        foreach (['cafe','carwash','detailing'] as $business) $payload['combined'][$business]=(float)($payload['dejatiNet'][$business]??$payload[$business])+$summary[$business];
        $payload['stock']=['asOf'=>date(DATE_ATOM),'items'=>$db->query('SELECT id,name,current_quantity,unit,minimum_quantity FROM vw_stock_balances WHERE active=1 ORDER BY name')->fetch_all(MYSQLI_ASSOC)];
        olseraQuery($db,'UPDATE whatsapp_closing_notifications SET payload=? WHERE closing_date=?','ss',[json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE),$date]);
    }
    olseraQuery($db,"UPDATE olsera_sync_jobs SET state='succeeded',completed_at=NOW(),lease_token=NULL,lease_until=NULL,last_error=NULL WHERE sales_date=?",'s',[$date]);
    return ['ok'=>true,'import_id'=>$importId,'summary'=>$summary];
}
