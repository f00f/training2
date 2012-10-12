<?php
require_once 'conf_players.inc.php'; // still in use for aliases

class Player {
	static $allPlayers = false;

	function __construct($cid) {
		// empty
	}

	// load _all_ player names, from DB
	static function loadPlayerDataFromDB($cid) {
		global $tables;
		global $players;

		# reset configuration from file and use DB instead
		$players = array();

		$result = DbQuery("SELECT `player_name`, `player_data` FROM `{$tables['players_conf']}` "
			. "WHERE `club_id` = '{$cid}'");
		if (mysql_num_rows($result) > 0) {
			while ($row = mysql_fetch_assoc($result)) {
				$players[ $row['player_name'] ] = unserialize($row['player_data']);
			}
		}
	}

	// load _all_ player names, from file and DB
	static function loadAllPlayers($cid) {
		if (false !== self::$allPlayers) {
			return self::$allPlayers;
		}

		self::$allPlayers = array();

		global $tables;
		global $players, $playerAliases;

		self::loadPlayerDataFromDB($cid);

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
				$lcName = mb_strtolower($name, 'utf8');
				self::$allPlayers[ $lcName ] = $name;
			}
		}

		asort(self::$allPlayers);

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
			$pid = Practice::getIdOfNextPractice($cid);
		}
		// TODO: create practice record in db, if not present

		$q = "INSERT INTO `{$tables['replies']}` "
			."(`club_id`, `practice_id`, `name`, `text`, `when`, `status`, `ip`, `host`) "
			."VALUES "
			."('{$cid}', '{$pid}', '{$name}', '{$player}', {$when}, '{$reply}', '{$ip}', '{$host}')";
		DbQuery($q);

		// TODO: update practice session record

		// TODO: redirect user
		$location = false;
		if (!$location && !empty($_SERVER['HTTP_REFERER'])) {
			// TODO: check (validate) referer string
			$location = $_SERVER['HTTP_REFERER'];
		}
		if (!$location) {
			// build url manually
			$prot = 'http' . (443 == $_SERVER['SERVER_PORT'] ? 's' : '') .'://';
			$srv = $_SERVER['HTTP_HOST'];
			$path = $_SERVER['SCRIPT_NAME'];
			if (strrpos($path, 'index.php', -9)) {
				$path = substr($path, 0, -9);
			}
			$qs = "player={$player}";
			$nextPid = Practice::getIdOfNextPractice($cid);
			if ($pid != $nextPid) {
				$qs .= "&pid={$pid}";
			}
			$location = $prot.$srv.$path.'?'.$qs;
		}
		print $location;
		//header("Location: {$location}");
	}
}
?>