<?php
header('Content-Type: application/json'); // Set JSON header


//parameternya file & name pdf name,mesin,

$machname = $_REQUEST['namaMesin'];
$param = strtolower($_GET['term']);
//for edit only $insertval = array($_POST[],$_POST[],$_POST[]);
/*
$location = "\\\\131.107.251.".$machname[0]."\\WinCC_Project_Slitter";
$user = "user";
$pass = "superadmin27";
$letter = $machname[1];
system("net use ".$letter.": \"".$location."\" ".$pass." /user:".$user." /persistent:no>nul 2>&1");
*/
/*
if ( $f = fopen('//mnt/HMI-'.$machname.'/WinCC_Project_Slitter/Data/Recipe/PAR.csv', 'r')){
while (($list = fgetcsv($f, 1000, ",")) !== FALSE) {//read csv
	if (strpos(strtolower($list[0]), $param) !== false) {
					$data = $list[0];
					echo "$data\n";
				}
  }
}
else
{
  echo "FILE NOT FOUND";
}
fclose($f);
*/
$suggestions = [];

$filepath = "//mnt/HMI-$machname/WinCC_Project_Slitter/Data/Recipe/PAR.csv";
if ($f = fopen($filepath, 'r')) {
    while (($list = fgetcsv($f, 1000, ",")) !== false) {
        if (strpos(strtolower($list[0]), $param) !== false) {
            $suggestions[] = $list[0]; // Just the string name
        }
    }
    fclose($f);
} else {
    // Optionally return a message
    $suggestions[] = "FILE NOT FOUND";
}

// Output as JSON
echo json_encode($suggestions);



?> 