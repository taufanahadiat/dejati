<?php
// Shared upsert keeps the original closing ID, even when web and mobile race.
function saveClosingSnapshot(mysqli $conn, array $closing): int {
    $stmt=$conn->prepare('INSERT INTO tb_closingan (tanggal,total_penjualan,cash,qris,card,cafe,carwash,detailing,detail_pengeluaran,created_at)
        VALUES (?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id),total_penjualan=VALUES(total_penjualan),cash=VALUES(cash),qris=VALUES(qris),card=VALUES(card),cafe=VALUES(cafe),carwash=VALUES(carwash),detailing=VALUES(detailing),detail_pengeluaran=VALUES(detail_pengeluaran),created_at=NOW()');
    $details=json_encode($closing['expenses'],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    $stmt->bind_param('siiiiiiis',$closing['tanggal'],$closing['total_penjualan'],$closing['cash'],$closing['qris'],$closing['card'],$closing['cafe'],$closing['carwash'],$closing['detailing'],$details);
    $stmt->execute();$id=(int)$conn->insert_id;$stmt->close();return $id;
}
