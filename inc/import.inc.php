<?php
function importV1Data($verbose = false) {
	global $importClubId, $importTableV1;
	global $table;

	// reset db
	//$sql = "TRUNCATE `{$table}`";
	//$result = DbQuery($sql);

	print "Fetching latest imported practice session from new DB.\n";
	$latest_session = 0;
	$sql = "SELECT MAX(`when`) AS 'LATEST_SESSION' FROM `{$table}`";
	$result = DbQuery($sql);
	if (!$result) {	die (mysql_error()); }
	if (0 < mysql_num_rows($result)) {
		$row = mysql_fetch_assoc($result);
		$latest_session = $row['LATEST_SESSION'];
	}
	print "  Latest session: {$latest_session}.\n";
	//$date = new DateTime("{$latest_session}");
	//$latest_session = $date->format('U');
	//print "  Latest session: {$latest_session}.\n";

	$more = false;
	$current_session = 0;

	$i = 0;

	do {
		if ($i++ > 2) break;

		print "Fetching next batch of practice sessions.\n";
		$sql = "SELECT * FROM `{$importTableV1}` WHERE `when` > '{$latest_session}' ORDER BY `when` ASC LIMIT 50";
		//print "------\n{$sql}\n\n";
		$result = DbQuery($sql);
		if (!$result) {	die (mysql_error()); }
		$more = false;
		$current_session = 0;
		if (0 < mysql_num_rows($result)) {
			$more = true;
		}
		print "  found ".mysql_num_rows($result). " entries.\n";

		$buffer = array();
		while ($row = @mysql_fetch_assoc($result)) {
			// collect lines in buffer
			$buffer[] = $row;

			// store lines to DB and update cursor
			if ('RESET' == $row['name']) {
				$latest_session = $row['when'];
				$date = new DateTime("@{$latest_session}");
				print "Adding ".count($buffer)." lines.\n";
				foreach ($buffer as $row) {
					print "Inserting {$row['name']}\n";
					$sql = "REPLACE INTO `{$table}` "
							."(`club_id`, `practice_id`, `name`, `text`, `when`, `status`, `ip`, `host`) "
							."VALUES "
							."('{$importClubId}', '".$date->format('Y-m-d H:i:s')."', '{$row['name']}', '{$row['text']}', {$row['when']}, '{$row['status']}', '{$row['ip']}', '{$row['host']}')";
					//print "------\n{$sql}\n\n";
					$result = DbQuery($sql);
				}
				unset($buffer);
				$buffer = array();
			}
		}
		unset($buffer);
		print "  Latest session: {$latest_session}.\n";
	} while ($more);
}
?>