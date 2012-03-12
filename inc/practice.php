<?php
require_once 'trainingszeiten.inc.php';

$tag2Int = array(
	'Mo' => 1,
	'Di' => 2,
	'Mi' => 3,
	'Do' => 4,
	'Fr' => 5,
	'Sa' => 6,
	'So' => 0,
);

class Practice {
	static $next = false;
	static $trainings = array();
	
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
		if (!is_int($pid)) return false;
		if ($pid <= 0) return false;
		$cid = mysql_real_escape_string($cid);
		$q = "SELECT `practice_id` FROM `{$table}` WHERE `practice_id` = '{$pid}' AND `club_id` = '{$cid}'";
		$res = DbQuey($q);
		$rv = (mysql_num_rows($res) > 0);
		mysql_free_result($res);
		return $rv;
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
		$pid = $date->format('Y-d-m H:i:s');
		$q = "REPLACE INTO `{$table}` "
			. "(`club_id`, `practice_id`, `name`, `text`, `when`, `status`, `ip`, `host`) "
			. "VALUES "
			. "('{$cid}', '{$pid}', 'RESET', '{$text}', '{$when}', '', '', '')";
		mysql_query($q);
	}

	static function findNext($cid) {
		if (! self::$next) {
			global $training;
			global $tag2Int;

			$trainingDates = array();
			foreach($training as $t) {
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
		if (!$pid) {
			// find next training p_id
		}
		$this->loadData($cid, $pid);
	}

	function loadData($practice_id) {
	}

	function display() {
		// information about the practice
		// TODO: and NOT about the upcoming one
		//       (this might coincide, but doesn't have to)
		$wtag = self::$next['wtag'];
		$datum = date('d.m.', self::$next['datum']);
		list($begin, $end) = explode(' - ', self::$next['zeit']);
		$ort = self::$next['ort'];
		
		// information about the players
		// ...
		?>
		Das nächste Training ist am <strong><?php print $wtag.', '.$datum ?>
		um <?php print $begin; ?> Uhr</strong> (Beckenzeit)
		im <?php print $ort; ?>
		<?php
	}
}
?>