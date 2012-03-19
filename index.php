<?php
require_once './inc/lib2.inc.php';
require_once './inc/dbconf.inc.php';
require_once './inc/import.inc.php';

// DBG
$club_id = 'ba';
$playername = 'dummyplayer';
$importV1 = false;
$importV2 = false;

# connect to db
mysql_connect($dbHost, $dbUser, $dbPass);
mysql_select_db($dbDB);

if ($importV1) {
// import old data from versions 1
$verbose = true;
importV1Data();
}
if ($importV2) {
	// and from version 2
	createTablesV3();
	importV2Data();
}

// find next practice and enter into DB.
$nP = Practice::findNext($club_id);

// decide which practice to display
$pid = 0;
if (@$_REQUEST['p']) {
	$pid = $_REQUEST['p'];
	if (! Practice::isValidID($club_id, $pid)) {
		$pid = 0;
	}
}

$p = new Practice($club_id, $pid);
$p->loadPlayersDetails();

# disconnect from db, only output follows
mysql_close();

html_header();
// display practice
$p->display();
$einteilungs_link = $einteilung_base . '?namen=' . urlencode(implode(',', $p->zusagend));
print '<h3>Siehe auch</h3>';
print '<ul>';
print '<li><a href="./">Zum aktuellen Training</a> (nur, wenn nicht das aktuelle angezeigt wird)</li>';
print '<li><a href="'.$einteilungs_link.'">Einteilung ansehen</a> (Link hinzu)</li>';
print '<li>Aktualisieren: Einfach Seite neu laden</li>';
print '</ul>';
html_footer();
?>