<?php
	if(isset($_GET['q'])){
		$param = strtolower(mysql_escape_string($_GET['q']));
		$dsn = $_REQUEST['namaMesin']."_dsn";
		if ($_REQUEST['jenisInter'] == "allCurve") $table= "dbo.UA#PDFData";
		else $table= "dbo.UA#".$_REQUEST['jenisInter'];
		// Connect to MSSQL
		$conn = odbc_connect($dsn,'OEElogin','arghakarya'); 
		if ($conn) {
			$query = "SELECT PDFName FROM " . $table . " WHERE PDFName LIKE '$param%' ORDER BY PDFName";
			$result=odbc_exec($conn, $query);
			while(odbc_fetch_row($result)) {
				if (strpos(strtolower(odbc_result($result,1)), $param) !== false) {
					$data = odbc_result($result,1);
					echo "$data\n";
				}
			}
			odbc_close ($conn);
		} else echo "Could not connect to database";
	}
?> 