<?php
require_once './inc/lib2.inc.php';
require_once './inc/dbconf.inc.php';
require_once './inc/import.inc.php';

// DBG
$club_id = 'ba';

# connect to db
mysql_connect($dbHost, $dbUser, $dbPass);
mysql_select_db($dbDB);

// import old data from version 1
importV1Data();

// find next practice and enter into DB.
$nP = Practice::findNext($club_id);

// detect which practice to display
$pid = 0;
if (@$_REQUEST['p']) {
	$pid = (int) $_REQUEST['p'];
	if (! Practice::isValidID($club_id, $pid)) {
		$pid = 0;
	}
}
if (!$pid) {
	// find max TID in DB which is smaller than time() - 120 Min
}

$p = new Practice($club_id, $pid);

# disconnect from db
mysql_close();

html_header();
// display practice
$p->display();
html_footer();
?>