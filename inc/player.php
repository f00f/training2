<?php
require_once 'conf_players.inc.php';

class Player {
	static $allPlayers = false;

	function __construct($cid) {
		// empty
	}

	// load _all_ player names, from file and DB
	static function loadAllPlayers($cid) {
		if (false === self::$allPlayers) {
			self::$allPlayers = array();
			
			global $tables;
			global $players, $playerAliases;

			// do not consider players from the config file, but only those from the DB
			$loadOnlyFromDB = false;
			if (! $loadOnlyFromDB) {
				foreach ($players as $s) {
					self::$allPlayers[strtolower($s['name'])] = $s['name'];
				}
			}

			// load additional player names
			$someMonthsAgo = strtotime('- 2 months'); // only those who replied within the last 2 months
			$result = DbQuery("SELECT DISTINCT `name` FROM `{$tables['replies']}` "
				. "WHERE `club_id` = '{$cid}' "
				. "AND `when` > {$someMonthsAgo}");
			if (mysql_num_rows($result) > 0) {
				while ($row = mysql_fetch_assoc($result)) {
					$nameLC = strtolower($row['name']);
					// check $players
					$name = @$players[ $nameLC ]['name'];
					if (!$name) {
						$name = @$playerAliases[ $nameLC ];
					}
					if (!$name) {
						$name = $row['name'];
					}
					self::$allPlayers[ strtolower($name) ] = $name;
				}
			}
		}

		return self::$allPlayers;
	}

	static function store_reply($reply, $player, $cid, $pid) {
		global $tables;
		global $playername;

		$name = FirstWord($player);
		$when = time();
		$ip = $_SERVER['REMOTE_ADDR'];
		$host = gethostbyaddr($ip);

		if (!$player) {
			$player = $playername;
		}
		if ('me' === $player) {
			$player = $playername;
		}
		if (0 === $pid) {
			$pid = Practice::getIdOfNextPractice();
		}
		// TODO: create practice record in db, if not present

		$q = "INSERT INTO `{$tables['replies']}` "
			."(`club_id`, `practice_id`, `name`, `text`, `when`, `status`, `ip`, `host`) "
			."VALUES "
			."('{$cid}', '{$pid}', '{$name}', '{$player}', {$when}, '{$reply}', '{$ip}', '{$host}')";
		DbQuery($q);

		// TODO: update practice record
	}
}
?>