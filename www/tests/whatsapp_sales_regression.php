<?php
require __DIR__ . '/../config/config.php';
require __DIR__ . '/../config/whatsapp_reports.php';
// Temporary tables shadow production tables only within this test connection.
$conn->query('CREATE TEMPORARY TABLE orders (id INT,created_at DATETIME,status_order VARCHAR(20),paid_amount INT,total_amount INT)');
$conn->query('CREATE TEMPORARY TABLE order_items (id INT AUTO_INCREMENT PRIMARY KEY,id_tr INT,id_prod VARCHAR(50),item_name VARCHAR(255),quantity INT,total INT)');
$conn->query('CREATE TEMPORARY TABLE order_carwash (id INT AUTO_INCREMENT PRIMARY KEY,id_tr INT,qty INT,total INT)');
$conn->query('CREATE TEMPORARY TABLE order_detailing (id INT AUTO_INCREMENT PRIMARY KEY,id_tr INT,qty INT,total INT)');
$conn->query("INSERT INTO orders VALUES (1,'2026-09-27 00:00:00','PAID',100,100),(2,'2026-09-27 23:59:59','',200,200),(3,'2026-09-27 12:00:00','CANCEL',500,500),(4,'2026-09-27 12:00:00',NULL,0,500),(5,'2026-09-26 23:59:59','PAID',400,400),(6,'2026-09-28 00:00:00','PAID',500,500)");
$conn->query("INSERT INTO order_items (id_tr,id_prod,item_name,quantity,total) VALUES (1,'a','Kopi',2,60),(2,'a','Kopi',3,200),(1,'b','Ayam',1,40),(3,'c','Batal',99,500),(4,'d','Open Bill',99,500),(5,'e','Kemarin',99,400),(6,'f','Besok',99,500)");
$s=whatsappDailySales($conn,'2026-09-27 00:00:00','2026-09-28 00:00:00');
if ((int)$s['transactions']!==2 || (int)$s['revenue']!==300 || count($s['topProducts'])!==2 || $s['topProducts'][0]['name']!=='Kopi' || (int)$s['topProducts'][0]['quantity']!==5) throw new RuntimeException('Sales totals or ranking incorrect');
$s=whatsappDailySales($conn,'2026-10-01 00:00:00','2026-10-02 00:00:00');
if ((int)$s['transactions']!==0 || (int)$s['revenue']!==0 || $s['topProducts']!==[]) throw new RuntimeException('Empty sales report incorrect');
echo "PASS: paid-only totals, no join inflation, day boundaries, product ranking and empty day\n";

$conn->query("INSERT INTO orders VALUES (7,'2026-09-27 12:00:00','PAID',900,900),(8,'2026-09-27 13:00:00','PAID',2,2),(9,'2026-09-27 14:00:00','PAID',5,5)");
$conn->query("INSERT INTO order_items (id_tr,id_prod,item_name,quantity,total) VALUES (7,'x','Campuran',1,600),(8,'y','Pembulatan',1,1),(9,'z','Nol',1,0)");
$conn->query('INSERT INTO order_carwash (id_tr,qty,total) VALUES (7,1,300),(8,1,1),(9,1,0),(3,99,999),(4,99,999)');
$conn->query('INSERT INTO order_detailing (id_tr,qty,total) VALUES (7,1,100),(8,1,1)');
$s=whatsappDailySales($conn,'2026-09-27 00:00:00','2026-09-28 00:00:00');
$d=$s['divisions'];
if ($d['cafe']['revenue']!==843 || $d['carwash']['revenue']!==273 || $d['detailing']['revenue']!==91) throw new RuntimeException('Division allocation mismatch');
if ($d['cafe']['transactions']!==5 || $d['carwash']['transactions']!==3 || $d['detailing']['transactions']!==2) throw new RuntimeException('Distinct transaction counts incorrect');
if (array_sum(array_column($d,'revenue'))!==(int)$s['revenue']) throw new RuntimeException('Division revenue does not reconcile');
echo "PASS: mixed divisions, discounts, adjustments, rounding, zero gross and distinct counts\n";
