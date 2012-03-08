<?php
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
?>