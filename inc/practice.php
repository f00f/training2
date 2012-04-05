<?php
require_once 'conf_practices.inc.php';
require_once 'player.php';

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
	
	var $positive = array();
	var $positiveNames = array();
	var $countPos = 0;
	var $negative = array();
	var $negativeNames = array();
	var $countNeg = 0;
	var $nixsagend = array();

	static function isValidID($cid, $pid) {
		global $tables;

		if (!preg_match('/^[0-9]{14}$/', $pid)) return false;
		if ($pid <= 0) return false;
		$cid = mysql_real_escape_string($cid);
		$q = "SELECT `practice_id` FROM `{$tables['practices']}` "
			."WHERE `practice_id` = '{$pid}' AND `club_id` = '{$cid}'";
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
	static function findUpcoming($cid) {
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
						list($begin, $end) = explode(' - ', $nextPractice->zeit);
						$nextPractice->begin   = $begin;
						$nextPractice->end     = $end;
						$nextPractice->ort     = $trainingDates[$weekday]['ort'];
						$nextPractice->anreise = $trainingDates[$weekday]['anreise'];

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

	static function getIdOfNextPractice($club_id) {
		global $tables;

		$now = time() - 2*3600; // offset for overlap
		$dtzEB  = new DateTimeZone('Europe/Berlin');

		$date = new DateTime("@{$now}");
		$date->setTimezone($dtzEB);
		$now = $date->format('Y-m-d H:i:s');

		$q = "SELECT MIN(`practice_id`) AS `pid` FROM `{$tables['practices']}` "
			."WHERE `practice_id` >= '{$now}' AND `club_id` = '{$club_id}'";
		$res = DbQuery($q);
		if (0 == mysql_num_rows($res)) {
			die("Err0r (c'tor)!");
		}
		$row = mysql_fetch_assoc($res);

		return $row['pid'];
	}

	// TODO: add constructor Practice(int)
	function __construct($cid, $pid = false) {
		global $tables;

		$this->club_id = $cid;

		if (!$pid) {
			// find next training p_id
			// find max PID in DB which is smaller than time() - 120 Min
			$pid = self::getIdOfNextPractice($this->club_id);
		}

		$this->practice_id = $pid;
		$this->loadData();
	}

	// assume that $practice_id is a valid ID
	function loadData() {
		global $tables;
		global $int2Tag;

		// load info about practice
		$q = "SELECT `meta` FROM `{$tables['practices']}` "
			."WHERE `practice_id` = '{$this->practice_id}' AND `club_id` = '{$this->club_id}'";
		$res = DbQuery($q);
		if (0 == mysql_num_rows($res)) {
			//die("Err0r! loadData({$this->club_id}, {$this->practice_id})");
			return false;
		}
		$row = mysql_fetch_assoc($res);
		if (!$row['meta']) {
			return false;
		}
		$p = unserialize($row['meta']);
		$this->wtag  = $p->wtag;
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
		global $tables;
		global $playername;
		$q = "SELECT `status` FROM `{$tables['replies']}` "
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
		global $tables;

		$neutralLc = Player::loadAllPlayers($this->club_id);

		$q = "SELECT `name`, `text`, `status` FROM `{$tables['replies']}` "
			."WHERE `practice_id` = '{$this->practice_id}' AND `club_id` = '{$this->club_id}' "
			."ORDER BY `when` DESC";
		$res = DbQuery($q);
		$didreply = array();
		while ($row = mysql_fetch_assoc($res)) {
			$nameLc = strtolower($row['name']);
			if (isset($didreply[ $nameLc ])) {
				continue;
			}
			if ('yes' == $row['status']) {
				$this->positive[] = $row['text'];
				$this->positiveNames[] = $row['name'];
				++$this->countPos;
			}
			if ('no' == $row['status']) {
				$this->negative[] = $row['text'];
				$this->negativeNames[] = $row['name'];
				++$this->countNeg;
			}
			$didreply[ $nameLc ] = true;
			unset($neutralLc[$nameLc]);
		}
		
		$this->nixsagend = array_values($neutralLc);
	}

	function store() {
		global $tables;

		// TODO: zugesagt etc. should not be stored in the DB.
		//       but maybe their counts!
		$text = serialize($this);
		$q = "REPLACE INTO `{$tables['practices']}` "
			. "(`club_id`, `practice_id`, `meta`) "
			. "VALUES "
			. "('{$this->club_id}', '{$this->practice_id}', '{$text}')";
		mysql_query($q);
		//if (mysql_error()) { print mysql_error(); }
	}

	function display() {
		global $einteilung_base;
		global $playername;

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
		<p>
		Das nächste Training ist am <strong><?php print $this->wtag.', '.date('d.m.Y', $this->datum) ?>
		um <?php print $this->begin; ?> Uhr</strong> (Beckenzeit)
		</p>
		<p>
			<form id="reply-form" action="." method="get">
			<input id="player" name="player" value="<?php print $playername; ?>" required="required" onkeyup="updatePlayernames();" />
			<button class="yep" name="action" value="yes">hell yeah</button>
			<button class="nope" name="action" value="no">nope</button><br />
			<small>Name (Grund)*</small>
			<?php $this->displayUpcomingList(); ?>
			</form>
		</p>
		<p>
		<strong class="zusage">Zusagen (<?php print $this->countPos; ?>):</strong><br />
		<?php
		$einteilungs_link = $einteilung_base . '?namen=' . urlencode(implode(',', $this->positiveNames));
		print ($this->countPos ? implode('; ', $this->positive) . '<br />'
				. '<a href="'.$einteilungs_link.'">Autom. Einteilung ansehen</a>'
			: '<em>keine</em>');
		?>
		</p>
		<p>
		<strong class="absage">Absagen (<?php print $this->countNeg;?>):</strong><br />
		<?php
		print ($this->countNeg ? implode('; ', $this->negative) : '<em>keine</em>');
		?>
		</p>
		<p>
		<strong class="nixgesagt">Nichtssagend:</strong><br />
		<?php
		print '<span class="player">';
		print implode('</span>; <span class="player">', $this->nixsagend);
		print '</span>';
		?>
		</p>
		<hr />
		<a href="<?php print $plink; ?>">Permalink</a> (<?php print $plink_id; ?>)<br />
		<?php
	}

	function displayUpcomingList() {
		// information about the practice
		// TODO: and NOT about the upcoming one
		//       (this might coincide, but doesn't have to)
		print '<table class="upcoming" cellspacing="0">';
		print '<tr>'
				.'<th>Kommende Trainings ('. count(self::$next) .')</th>'
				.'<th><span class="playername">Aktionen:</span></th>'
				.'<th>Zus. / Du</th>'
				.'</tr>';
		foreach(self::$next as $p) {
			$wtag = $p->wtag;
			$datum = date('d.m.', $p->datum);
			list($begin, $end) = explode(' - ', $p->zeit);
			$ort = $p->ort;
			$plink_id = preg_replace('/[^0-9]/', '', $p->practice_id);
			$view_link = '?p='.$plink_id;
			// TODO: add player name to links
			$yes_link = $view_link.'&amp;zusage=1';
			$no_link = $view_link.'&amp;absage=1';

			// information about the players
			// ...

			$duClass = '';
			if ('yes' == $p->userStatus) {
				$duClass = ' class="zusage"';
			}
			if ('no' == $p->userStatus) {
				$duClass = ' class="absage"';
			}
			?>
			<tr>
			<td align="right"><a href="<?php print $view_link; ?>"><?php print $wtag.' '.$datum ?>, <?php print $begin; ?> Uhr</a>,
			<?php print $ort; ?></td>
			<td><button class="yep" name="action" value="yes">Zus.</button>
			<button class="nope" name="action" value="no">Abs.</button></td>
			<td><strong class="zusage"><?php print $p->countPos; ?></strong>
			/ <span<?php print $duClass; ?>><?php print $p->userStatus; ?></span></td>
			</tr>
			<?php
		}
		print '</table>';
	}
}
?>