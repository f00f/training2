<?php
require_once 'trainingszeiten.inc.php';

$tag2Int = array(
	'Mo' => 1, 'Di' => 2, 'Mi' => 3, 'Do' => 4,
	'Fr' => 5, 'Sa' => 6, 'So' => 0,
);
$int2Tag = array(
	1 => 'Mo', 2 => 'Di', 3 => 'Mi', 4 => 'Do',
	5 => 'Fr', 6 => 'Sa', 7 => 'So',
);

class Practice {
	static $next = false;
	static $trainings = array();
	
	var $club_id = '';
	var $practice_id = 0;
	var $wtag = '';
	var $datum = '';
	var $zeit = '';
	var $begin = '';
	var $end = '';
	var $ort = '';
	var $userStatus = '';
	
	var $zusagend = array();
	var $absagend = array();
	var $nixsagend = array();

	static function isValidID($cid, $pid) {
		global $table;

		if (!preg_match('/^[0-9]{14}$/', $pid)) return false;
		if ($pid <= 0) return false;
		$cid = mysql_real_escape_string($cid);
		$q = "SELECT `practice_id` FROM `{$table}` "
			."WHERE `practice_id` = '{$pid}' AND `club_id` = '{$cid}' AND `name` = 'RESET'";
		$res = DbQuery($q);
		return (mysql_num_rows($res) > 0);
	}

	static function addWithEndDate(&$pTraining, $pEndDate) {
		$date = date('Ymd');
		if ($date <= $pEndDate) {
			self::$trainings[] = $pTraining;
		}
	}

	static function addWithStartDate(&$pTraining, $pStartDate) {
		$date = date('Ymd');
		$oneWeekBefore = date('Ymd', strtotime($pStartDate) - 7*SECONDS_PER_DAY);
		if ($date > $oneWeekBefore) {
			self::$trainings[] = $pTraining;
		}
	}

	static function addSingleDate(&$pTraining, $pDate) {
		$date = date('Ymd');
		$oneWeekBefore = date('Ymd', strtotime($pDate) - 7*SECONDS_PER_DAY);
		if ($date > $oneWeekBefore AND $date <= $pDate) {
			self::$trainings[] = $pTraining;
		}
	}

	// find list of practices for next week
	static function findNext($cid) {
		if (! self::$next) {
			global $tag2Int;

			self::$next = array();
			$trainingDates = array();

			foreach(self::$trainings as $t) {
				$wdayIdx = $tag2Int[$t['tag']];
				$trainingDates[$wdayIdx] = $t;
			}

			$weekday      = date('w');
			$daysLeft     = 0;
			$nextTraining = false;
			$iter         = 0;

			//	$now = strtotime('+1 hour'); // timezone correction
			$nowTime = date('H:i');
			// TODO: check condition
			while (/*false === $nextTraining AND*/ ++$iter <= 8) { // 8, ok 
				// TODO: date check for $trainings
				if (isset($trainingDates[$weekday])
					AND ($daysLeft > 0 OR $trainingDates[$weekday]['zeit'] >= $nowTime)) {
					// TODO: should be a Practice object
					$nextTraining = array(
						'wtag'    => $trainingDates[$weekday]['tag'],
						'datum'   => strtotime('+ '.$daysLeft.'days'),
						'zeit'    => $trainingDates[$weekday]['zeit'],
						'ort'     => $trainingDates[$weekday]['ort'],
						'anreise' => $trainingDates[$weekday]['anreise'],
					);

					// correct timestamp
					list($ntHour, $ntMin) = explode(':', $nextTraining['zeit']);
					$ntMin = (int) $ntMin; // kludge: cut the tail
					$ntDay	= date('d', $nextTraining['datum']);
					$ntMon	= date('m', $nextTraining['datum']);
					$ntYear	= date('Y', $nextTraining['datum']);
					$nextTraining['when'] = mktime($ntHour, $ntMin, 0, $ntMon, $ntDay, $ntYear);

					$nextTraining['practice_id'] = date('Y-m-d H:i:s', $nextTraining['when']);

					$nextPractice = new Practice($cid, $nextTraining['practice_id']);
					if (!$nextPractice->wtag) { // was not in db, yet
						// fill object
						$nextPractice->wtag    = $trainingDates[$weekday]['tag'];
						$nextPractice->datum   = strtotime('+ '.$daysLeft.'days');
						$nextPractice->zeit    = $trainingDates[$weekday]['zeit'];
						$nextPractice->ort     = $trainingDates[$weekday]['ort'];
						$nextPractice->anreise = $trainingDates[$weekday]['anreise'];
						$nextPractice->when    = mktime($ntHour, $ntMin, 0, $ntMon, $ntDay, $ntYear);

						// store object
						$nextPractice->store();
					}

					self::$next[] = $nextPractice;
				}
				$weekday = ++$weekday % 7;
				++$daysLeft;
			}
		}

		return self::$next[0];
	}

	// TODO: add constructor Practice(int)
	function __construct($cid, $pid = false) {
		global $table;

		$this->club_id = $cid;

		if (!$pid) {
			// find next training p_id
			// find max PID in DB which is smaller than time() - 120 Min
			$now = time();
			$date = new DateTime("@{$now}");
			$now = $date->format('Y-m-d H:i:s');
			$q = "SELECT MIN(`practice_id`) AS `pid` FROM `{$table}` "
				."WHERE `practice_id` >= '{$now}' AND `club_id` = '{$this->club_id}' AND `name` = 'RESET'";
			$res = DbQuery($q);
			if (0 == mysql_num_rows($res)) {
				die("Err0r (c'tor)!");
			}
			$row = mysql_fetch_assoc($res);
			$pid = $row['pid'];
		}

		$this->practice_id = $pid;
		$this->loadData();
	}

	// assume that $practice_id is a valid ID
	function loadData() {
		global $table;
		global $int2Tag;

		// load info about practice
		$q = "SELECT `text` FROM `{$table}` "
			."WHERE `practice_id` = '{$this->practice_id}' AND `club_id` = '{$this->club_id}'";
		$res = DbQuery($q);
		if (0 == mysql_num_rows($res)) {
			//die("Err0r! loadData({$this->club_id}, {$this->practice_id})");
			return false;
		}
		$row = mysql_fetch_assoc($res);
		$p = unserialize($row['text']);
		$this->wtag  = $p->wtag;
		$this->when  = $p->when;
		$this->datum = $p->datum;
		$this->ort   = $p->ort;
		$this->zeit  = $p->zeit;
		$this->begin = $p->begin;
		$this->end   = $p->end;
		$this->club_id = $p->club_id;
		$this->practice_id = $p->practice_id;

		$this->loadUserStatus();

		return true;
	}

	// load info about current user
	function loadUserStatus() {
		global $table;
		global $playername;
		$q = "SELECT `status` FROM `{$table}` "
			."WHERE `practice_id` = '{$this->practice_id}' AND `club_id` = '{$this->club_id}' "
			."AND `name` = '{$playername}' "
			."ORDER BY `when` DESC "
			."LIMIT 1";
		$res = DbQuery($q);
		$this->userStatus = 'nix';
		if (mysql_num_rows($res) > 0) {
			$row = mysql_fetch_assoc($res);
			$this->userStatus = $row['status'];
		}
	}

	// load info about players
	function loadPlayersDetails() {
		global $table;
		$q = "SELECT `name`, `text`, `status` FROM `{$table}` "
			."WHERE `practice_id` = '{$this->practice_id}' AND `club_id` = '{$this->club_id}' "
			."AND `name` != 'RESET' "
			."ORDER BY `when` DESC";
		$res = DbQuery($q);
		$hatwasgesagt = array();
		while ($row = mysql_fetch_assoc($res)) {
			$name = strtolower($row['name']);
			if (isset($hatwasgesagt[ $name ])) {
				continue;
			}
			if ('ja' == $row['status']) {
				$this->zusagend[] = $row['text'];
			}
			if ('nein' == $row['status']) {
				$this->absagend[] = $row['text'];
			}
			$hatwasgesagt[ $name ] = true;
		}
	}

	function store() {
		global $table;

		// TODO: zugesagt etc. should not be stored in the DB.
		//       but maybe their counts!
		$text = serialize($this);
		$q = "REPLACE INTO `{$table}` "
			. "(`club_id`, `practice_id`, `name`, `text`, `when`, `when_dt`, `status`, `ip`, `host`) "
			. "VALUES "
			. "('{$this->club_id}', '{$this->practice_id}', 'RESET', '{$text}', '{$this->when}', '{$this->practice_id}', '', '', '')";
		mysql_query($q);
		//if (mysql_error()) { print mysql_error(); }
	}

	function display() {
		// information about the practice
		// TODO: and NOT about the upcoming one
		//       (this might coincide, but doesn't have to)
		//list($begin, $end) = explode(' - ', $this->zeit);
		//$ort = $this->ort;
		$plink_id = preg_replace('/[^0-9]/', '', $this->practice_id);
		$plink = '?p='.$plink_id;

		// information about the players
		// ...
		?>
		Das nächste Training ist am <strong><?php print $this->wtag.', '.date('d.m.Y', $this->datum) ?>
		um <?php print $this->begin; ?> Uhr</strong> (Beckenzeit)
		<hr />
		<a href="<?php print $plink; ?>">Permalink</a> (<?php print $plink_id; ?>)<br />
		<em>(Fehler: practice_id in Datenbank ist falsche Zeitzone)</em>
		<?php
		print '<p>'
				.'<h3>Kommende Trainings ('.count(self::$next).')</h3>';
		$this->displayUpcomingList();
		print '</p>';
	}
	function displayUpcomingList() {
		// information about the practice
		// TODO: and NOT about the upcoming one
		//       (this might coincide, but doesn't have to)
		print '<ul>';
		foreach(self::$next as $p) {
			$wtag = $p->wtag;
			$datum = date('d.m.Y', $p->datum);
			list($begin, $end) = explode(' - ', $p->zeit);
			$ort = $p->ort;
			$plink_id = preg_replace('/[^0-9]/', '', $p->practice_id);
			$view_link = '?p='.$plink_id;
			// TODO: add player name to links
			$yes_link = $view_link.'&amp;zusage=1';
			$no_link = $view_link.'&amp;absage=1';

			// information about the players
			// ...

			?>
			<li>Training am <strong><?php print $wtag.', '.$datum ?>
			um <?php print $begin; ?> Uhr</strong>
			im <?php print $ort; ?>
			<a href="<?php print $view_link; ?>">Ansehen</a>
			| <a href="<?php print $yes_link; ?>">Zusagen</a>
			| <a href="<?php print $no_link; ?>">Absagen</a>
			(<?php print $plink_id; ?>)</li>
			<?php
		}
		print '</ul>';
	}
}
?>