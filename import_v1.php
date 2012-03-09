<?php
error_reporting(E_ALL /*| E_STRICT*/);
require_once './inc/lib2.inc.php';
require_once './inc/dbconf.inc.php';
require_once './inc/import.inc.php';

# connect to db
mysql_connect($dbHost, $dbUser, $dbPass);
mysql_select_db($dbDB);

$verbose = true;
importV1Data($verbose);

# disconnect from db
mysql_close();
?>