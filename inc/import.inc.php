<?php
require_once './inc/lib2.inc.php';

function importV2Data($verbose = false) {
	importV2Practices($verbose);
	importV2Replies($verbose);
	updateV2ReplyCounts($verbose);
}
// import practice sessions
function importV2Practices($verbose = false) {
	global $importTableV2; // V2-table
	global $tables; // all V3-tables

	$sql = "REPLACE INTO `{$tables['practices']}` "
			."(`club_id`, `practice_id`, `meta`, `count_yes`, `count_no`) "
			."SELECT `club_id`, `practice_id`, `text`, 0, 0 FROM `{$importTableV2}` WHERE 'RESET' = `name`";
	$res = DbQuery($sql);
}
function importV2Replies($verbose = false) {
	global $importTableV2; // V2-table
	global $tables; // all V3-tables

	// import practice replies
	$sql = "REPLACE INTO `{$tables['replies']}` "
			."(`club_id`, `practice_id`, `name`, `text`, `when`, `status`, `ip`, `host`) "
			."SELECT `club_id`, `practice_id`, `name`, `text`, `when`, `status`, `ip`, `host` "
			."FROM `{$importTableV2}` WHERE 'RESET' != `name`";
	$res = DbQuery($sql);
	// convert practice replies
	$sql = "UPDATE `{$tables['replies']}` "
			."SET `status` = 'yes' "
			."WHERE `status` = 'ja' ";
	$res = DbQuery($sql);
	$sql = "UPDATE `{$tables['replies']}` "
			."SET `status` = 'no' "
			."WHERE `status` = 'nein' ";
	$res = DbQuery($sql);
}
// update count_* field values
function updateV2ReplyCounts($verbose = false) {
	global $importTableV2; // V2-table
	global $tables; // all V3-tables

	$sql = "SELECT `club_id`, `practice_id` FROM `{$tables['practices']}` ORDER BY `practice_id`";
	$res_p = DbQuery($sql);
	while($p = mysql_fetch_assoc($res_p)) {
		$cid = $p['club_id'];
		$pid = $p['practice_id'];
		$sql = "SELECT `name`, `status` FROM `{$importTableV2}` "
			."WHERE `practice_id` = '{$pid}' AND `club_id` = '{$cid}' "
			."AND `name` != 'RESET' "
			."ORDER BY `when` DESC";
		$res = DbQuery($sql);
		$count_yes = 0;
		$count_no = 0;
		$hatwasgesagt = array();
		while ($row = mysql_fetch_assoc($res)) {
			$name = strtolower($row['name']);
			if (isset($hatwasgesagt[ $name ])) {
				continue;
			}
			if ('ja' == $row['status']) {
				++$count_yes;
			}
			if ('nein' == $row['status']) {
				++$count_no;
			}
			$hatwasgesagt[ $name ] = true;
		}
		$sql = "UPDATE `{$tables['practices']}` "
			. "SET `count_yes` = '{$count_yes}', `count_no` = '{$count_no}' "
			. "WHERE  `practice_id` = '{$pid}' AND `club_id` = '{$cid}'";
		$res = DbQuery($sql);
	}
}

// FIXME: This is broken, because a page load stores the next few trainings to the DB.
// Possible fix: imported rows have emtpy `text` field, stored ones not.
function importV1Data($verbose = false) {
	global $importClubId, $importTableV1;
	global $importTableV2;

	// reset db
	//$sql = "TRUNCATE `{$importTableV2}`";
	//$result = DbQuery($sql);

	if ($verbose) {
		print "Fetching latest imported practice session from new DB.\n";
	}
	$latest_session = 0;
	$sql = "SELECT MAX(`when`) AS 'LATEST_SESSION' FROM `{$importTableV2}`";
	$result = DbQuery($sql);
	if (!$result) {	die (mysql_error()); }
	if (0 < mysql_num_rows($result)) {
		$row = mysql_fetch_assoc($result);
		$latest_session = $row['LATEST_SESSION'];
	}
	if ($verbose) {
		print "  Latest session: {$latest_session}.\n";
	}
	//$date = new DateTime("{$latest_session}");
	//$latest_session = $date->format('U');
	//print "  Latest session: {$latest_session}.\n";

	$more = false;
	$current_session = 0;

	$i = 0;

	do {
		if ($i++ > 2) break;

		if ($verbose) {
			print "Fetching next batch of practice sessions.\n";
		}
		$sql = "SELECT * FROM `{$importTableV1}` "
				. "WHERE `when` > {$latest_session} "
				. "ORDER BY `when` ASC "
				. "LIMIT 100";
		if ($verbose) {
			print "------\n{$sql}\n\n";
		}
		$result = DbQuery($sql);
		if (!$result) {	die (mysql_error()); }
		$more = false;
		$current_session = 0;
		if (0 < mysql_num_rows($result)) {
			$more = true;
		}
		if ($verbose) {
			print "  found ".mysql_num_rows($result). " entries.\n";
		}

		$buffer = array();
		while ($row = @mysql_fetch_assoc($result)) {
			// collect lines in buffer
			$buffer[] = $row;

			// store lines to DB and update cursor
			if ('RESET' == $row['name'] /*AND '' == $row['text']*/) {
				$latest_session = $row['when'];
				$date = new DateTime("@{$latest_session}");
				$dtfmt = $date->format('Y-m-d H:i:s');
				if ($verbose) {
					print "Adding ".count($buffer)." lines.\n";
				}
				foreach ($buffer as $row) {
					if ($verbose) {
						print "Inserting {$row['name']}\n";
					}
					$dt2 = new DateTime("@{$row['when']}");
					$when_dt = $dt2->format('Y-m-d H:i:s');
					$sql = "REPLACE INTO `{$importTableV2}` "
							."(`club_id`, `practice_id`, `name`, `text`, `when`, `when_dt`, `status`, `ip`, `host`) "
							."VALUES "
							."('{$importClubId}', '".$dtfmt."', '{$row['name']}', '{$row['text']}', {$row['when']}, '{$when_dt}', '{$row['status']}', '{$row['ip']}', '{$row['host']}')";
					//print "------\n{$sql}\n\n";
					$result = DbQuery($sql);
				}
				unset($buffer);
				$buffer = array();
			}
		}
		unset($buffer);
		if ($verbose) {
			print "  Latest session: {$latest_session}.\n";
		}
	} while ($more);
}

function createTablesV3() {
	global $tables;
	// table for practices (session meta data)
	$sql = "CREATE TABLE IF NOT EXISTS `{$tables['practices']}` (
	  `club_id` varchar(6) NOT NULL,
	  `practice_id` datetime NOT NULL,
	  `meta` TEXT,
	  `count_yes` INT,
	  `count_no` INT,
	  UNIQUE KEY `namewhen` (`club_id`,`practice_id`),
	  KEY `club_id` (`club_id`),
	  KEY `practice_id` (`practice_id`)
	);";
	$res = mysql_query($sql);
	if (mysql_errno()) {
		die('DB error: '.mysql_error());
	}
	// table for replies
	$sql = "CREATE TABLE IF NOT EXISTS `{$tables['replies']}` (
	  `club_id` varchar(6) NOT NULL,
	  `practice_id` datetime NOT NULL,
	  `name` varchar(30) NOT NULL default '',
	  `text` text NOT NULL,
	  `when` int(11) NOT NULL default '0',
	  `status` varchar(10) NOT NULL default '',
	  `ip` varchar(15) NOT NULL default '',
	  `host` varchar(255) NOT NULL default '',
	  UNIQUE KEY `namewhen` (`name`,`when`),
	  KEY `club_id` (`club_id`),
	  KEY `practice_id` (`practice_id`),
	  KEY `name` (`name`),
	  KEY `status` (`status`)
	);";
	//  `when_dt` datetime NOT NULL,
	$res = mysql_query($sql);
	if (mysql_errno()) {
		die('DB error: '.mysql_error());
	}
}

/**
 * @deprecated
 */
function createTablesV2() {
// `when_dt` is not really required
/*
CREATE TABLE IF NOT EXISTS `training2` (
  `club_id` varchar(6) NOT NULL,
  `practice_id` datetime NOT NULL,
  `name` varchar(30) NOT NULL default '',
  `text` text NOT NULL,
  `when` int(11) NOT NULL default '0',
  `when_dt` datetime NOT NULL,
  `status` varchar(10) NOT NULL default '',
  `ip` varchar(15) NOT NULL default '',
  `host` varchar(255) NOT NULL default '',
  UNIQUE KEY `namewhen` (`name`,`when`),
  KEY `club_id` (`club_id`),
  KEY `name` (`name`),
  KEY `when_dt` (`practice_id`)
);
*/
}
?>