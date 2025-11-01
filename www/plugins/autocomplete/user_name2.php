<?php
	if(isset($_GET['q'])){
		$param = strtolower(mysql_escape_string($_GET['q']));
		include "../../../include/db.php";
		$query = mysql_query("SELECT email FROM user WHERE email LIKE '%$param%'");
		while ($list = mysql_fetch_array($query)) {
				if (($list[0] != 'admin') && ($list[0] != 'user'))
					echo "$list[0]\n";
		}
		echo 'All';
	}
?> 