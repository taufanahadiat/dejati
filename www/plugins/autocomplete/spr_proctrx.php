<?php
	if($type = $_REQUEST['type']== "item"){

	if(isset($_GET['q'])){
		session_start();
		$param = strtolower(mysql_escape_string($_GET['q']));
		$sesi = $_SESSION['type_user'];
		include "../../../include/db.php";
		$query = mysql_query("SELECT data_part.item_code FROM data_part INNER JOIN wo_user ON data_part.datapart_pm=wo_user.item_codefk WHERE item_code LIKE '%$param%' AND typeuser = $sesi AND status != 'COMPLETED' GROUP BY item_code");

			while ($list = mysql_fetch_array($query)) {
				if (strpos(strtolower($list[0]), $param) !== false) {
					echo "$list[0]\n";
				}
			}			
		
			}}
	else if ($type = $_REQUEST['type']== "wo") {
		if(isset($_GET['q'])){
		session_start();
		$param = strtolower(mysql_escape_string($_GET['q']));
		$sesi = $_SESSION['type_user'];
		include "../../../include/db.php";
		$query = mysql_query("SELECT id_wo FROM wo_user WHERE id_wo LIKE '%$param%' AND type_user = $sesi AND status = 'ON PROGRESS' GROUP BY id_wo");

			while ($list = mysql_fetch_array($query)) {
				if (strpos(strtolower($list[0]), $param) !== false) {
					echo "$list[0]\n";
				}
			}			
		
			}
	}

	else if ($type = $_REQUEST['type']== "item_out") {
		if(isset($_GET['q'])){
			session_start();
			$sesi = $_SESSION['type_user'];
		$param = strtolower(mysql_escape_string($_GET['q']));
		include "../../../include/db.php";
		$query = mysql_query("SELECT item_code FROM data_part WHERE item_code LIKE '%$param%' AND typeuser = $sesi GROUP BY item_code");

			while ($list = mysql_fetch_array($query)) {
				if (strpos(strtolower($list[0]), $param) !== false) {
					echo "$list[0]\n";
				}
			}			
		
			}
	}


?> 