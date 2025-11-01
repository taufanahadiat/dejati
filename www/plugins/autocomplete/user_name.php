<?php
	if(isset($_GET['q'])){
		$param = strtolower(mysql_escape_string($_GET['q']));
		include "../../../include/db.php";
		$query = mysql_query("SELECT email FROM user WHERE email LIKE '%$param%'");
		while ($list = mysql_fetch_array($query)) {
			if (strpos(strtolower($list[0]), $param) !== false) {
				echo "$list[0]\n";
			}
		}
	}
?> 