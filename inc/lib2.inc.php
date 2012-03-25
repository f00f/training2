<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('SECONDS_PER_DAY', 86400);

require_once './inc/practice.php';

// ENVIRONMENT & MISC

if (!defined('ON_TEST_SERVER')) {
	if (!defined(@$_SERVER['HTTP_HOST'])) {
		define('ON_TEST_SERVER', true);
	} else {
		define('ON_TEST_SERVER', (false !== strpos($_SERVER['HTTP_HOST'], '.test')));
	}
}

function GetAction() {
	$action = strtolower(@$_REQUEST['action']);
	if ('yes' == $action || 'no' == $action) {
		return $action;
	}
	return false;
}

function FirstWord($p_text) {
	$matches = array();
	if (preg_match('/^(.*?)[^A-Za-z0-9äöüßÄÖÜ]/', $p_text, $matches)) {
		return $matches[1];
	} else {
		return $p_text;
	}
}

// DATABASE

function DbQuery($query) {
	$result	= mysql_query($query);
	if (mysql_errno() != 0) {
		die(mysql_error());
	}
	return $result;
}

// VIEW

function html_header() {
	global $club_id, $playername;
?>
<html>
<head><link rel="stylesheet" href="./css/training.css" type="text/css" /></head>
<body>
<h3><?php print $club_id; ?>::uwr::training</h3>
<div id="practice">
	<h2>Hallo, <?php print $playername; ?>.</h2>
<?php
}

function html_footer() {
	global $pid;
?>
	<h3>Links</h3>
	<ul>
	<li><a href="<?php print 'http' . (443 == $_SERVER['SERVER_PORT'] ? 's' : '') .'://'. $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']; ?>">Aktualisieren</a>: Einfach Seite neu laden</li>
	<?php
	if ($pid) {
		print '<li>Zurück zum <a href="./">aktuellen Training</a></li>';
	}
	?>
	</ul>
</div><?php /* /#practice */ ?>
<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
<script type="text/javascript" src="./js/training.js"></script>
<script type="text/javascript">
jQuery(updatePlayernames());
/* TODO: attach handler to .player onclick */
</script>
</body>
</html>
<?php
}
?>