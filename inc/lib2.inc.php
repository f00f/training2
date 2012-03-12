<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('SECONDS_PER_DAY', 86400);

require_once './inc/practice.php';

if (!defined('ON_TEST_SERVER')) {
	if (!defined(@$_SERVER['HTTP_HOST'])) {
		define('ON_TEST_SERVER', true);
	} else {
		define('ON_TEST_SERVER', (false !== strpos($_SERVER['HTTP_HOST'], '.test')));
	}
}

function DbQuery($query) {
	$result	= mysql_query($query);
	if (mysql_errno() != 0) {
		die(mysql_error());
	}
	return $result;
}

function html_header() {
?>
<html><head></head><body>
<?php
}

function html_footer() {
?>
</body></html>
<?php
}
?>