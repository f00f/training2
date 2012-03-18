<?php
require_once './inc/lib2.inc.php';
require_once './inc/dbconf.inc.php';
require_once './inc/import.inc.php';

// DBG
$club_id = 'ba';
$playername = 'dummyplayer';

# connect to db
mysql_connect($dbHost, $dbUser, $dbPass);
mysql_select_db($dbDB);

// import old data from version 1
importV1Data();

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
$einteilungs_link = '';
print '<h3>Siehe auch</h3>';
print '<ul>';
print '<li><a href="./">Zum aktuellen Training</a> (nur, wenn nicht das aktuelle angezeigt wird)</li>';
print '<li><a href="'.$einteilungs_link.'">Einteilung ansehen</a> (Link hinzu)</li>';
print '<li>Aktualisieren: Einfach Seite neu laden</li>';
print '</ul>';
html_footer();
?>