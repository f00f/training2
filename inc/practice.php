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

	// Store data of next practice in DB
	static function storeNext($cid) {
		global $table;

		$cid = mysql_real_escape_string($cid);
		$text = serialize(self::$next);
		$when = self::$next['when'];
		$date = new DateTime("@{$when}");
		$pid = $date->format('Y-m-d H:i:s');
		$q = "REPLACE INTO `{$table}` "
			. "(`club_id`, `practice_id`, `name`, `text`, `when`, `when_dt`, `status`, `ip`, `host`) "
			. "VALUES "
			. "('{$cid}', '{$pid}', 'RESET', '{$text}', '{$when}', '{$pid}', '', '', '')";
		mysql_query($q);
	}

	static function findNext($cid) {
		if (! self::$next) {
			global $tag2Int;

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
			while (false === $nextTraining AND ++$iter <= 8) { // 8, ok 
				if (isset($trainingDates[$weekday]) AND ($daysLeft > 0 OR $trainingDates[$weekday]['zeit'] >= $nowTime)) {
					$nextTraining = array(
						'wtag'    => $trainingDates[$weekday]['tag'],
						'datum'   => strtotime('+ '.$daysLeft.'days'),
						'zeit'    => $trainingDates[$weekday]['zeit'],
						'ort'     => $trainingDates[$weekday]['ort'],
						'anreise' => $trainingDates[$weekday]['anreise'],
					);
				}
				$weekday = ++$weekday % 7;
				++$daysLeft;
			}
			// correct timestamp
			list($ntHour, $ntMin) = explode(':', $nextTraining['zeit']);
			$ntMin = (int) $ntMin; // kludge: cut the tail
			$ntDay	= date('d', $nextTraining['datum']);
			$ntMon	= date('m', $nextTraining['datum']);
			$ntYear	= date('Y', $nextTraining['datum']);
			$nextTraining['when'] = mktime($ntHour, $ntMin, 0, $ntMon, $ntDay, $ntYear);

			self::$next = $nextTraining;
			self::storeNext($cid);
		}

		return self::$next;
	}

	// TODO: add constructor Practice(int)
	function __construct($cid, $pid = false) {
		global $table;

		$this->club_id = $cid;

		if (!$pid) {
			// find next training p_id
			// find max PID in DB which is smaller than time() - 120 Min
			$now = time() + 7 * 24 * 3600;
			$date = new DateTime("@{$now}");
			$now = $date->format('Y-m-d H:i:s');
			$q = "SELECT MAX(`practice_id`) AS `pid` FROM `{$table}` "
				."WHERE `practice_id` < '{$now}' AND `club_id` = '{$this->club_id}' AND `name` = 'RESET'";
			$res = DbQuery($q);
			if (0 == mysql_num_rows($res)) {
				die('Err0r!');
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
		$q = "SELECT * FROM `{$table}` "
			."WHERE `practice_id` = '{$this->practice_id}' AND `club_id` = '{$this->club_id}'";
		$res = DbQuery($q);
		if (0 == mysql_num_rows($res)) {
			die('Err0r!');
		}
		$row = mysql_fetch_assoc($res);
		$this->when = $row['when'];
		$this->wtag = $int2Tag[ date('N', $this->when) ];
		$this->datum = date('d.m.Y', $this->when);
		$this->begin = date('H:i', $this->when);

		// TODO: load info about players
		//$this->zusagend = array();
		//$this->absagend = array();
		//$this->nixsagend = array();
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
		Das nächste Training ist am <strong><?php print $this->wtag.', '.$this->datum ?>
		um <?php print $this->begin; ?> Uhr</strong> (Beckenzeit)
		<hr />
		<a href="<?php print $plink; ?>">Permalink</a> (<?php print $plink_id; ?>)
		<?php
	}
	function displayNext() {
		// information about the practice
		// TODO: and NOT about the upcoming one
		//       (this might coincide, but doesn't have to)
		$wtag = self::$next['wtag'];
		$datum = date('d.m.', self::$next['datum']);
		list($begin, $end) = explode(' - ', self::$next['zeit']);
		$ort = self::$next['ort'];
		$plink_id = preg_replace('/[^0-9]/', '', $this->practice_id);
		$plink = '?p='.$plink_id;

		// information about the players
		// ...
		?>
		Das nächste Training ist am <strong><?php print $wtag.', '.$datum ?>
		um <?php print $begin; ?> Uhr</strong> (Beckenzeit)
		im <?php print $ort; ?>
		<hr />
		<a href="<?php print $plink; ?>">Permalink</a> (<?php print $plink_id; ?>)
		<?php
	}
}
?>