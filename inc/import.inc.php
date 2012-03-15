<?php
function importV1Data($verbose = false) {
	global $importClubId, $importTableV1;
	global $table;

	// reset db
	//$sql = "TRUNCATE `{$table}`";
	//$result = DbQuery($sql);

	if ($verbose) {
		print "Fetching latest imported practice session from new DB.\n";
	}
	$latest_session = 0;
	$sql = "SELECT MAX(`when`) AS 'LATEST_SESSION' FROM `{$table}`";
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
		$sql = "SELECT * FROM `{$importTableV1}` WHERE `when` > '{$latest_session}' ORDER BY `when` ASC LIMIT 50";
		//print "------\n{$sql}\n\n";
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
			if ('RESET' == $row['name']) {
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
					$sql = "REPLACE INTO `{$table}` "
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
?>