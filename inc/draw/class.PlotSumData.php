<?php
/**
 * This file contains class::PlotSumData
 * @package Runalyze\Plot
 */

use Runalyze\Configuration;

/**
 * Plot sum data
 * @package Runalyze\Plot
 */
abstract class PlotSumData extends Plot {
	/**
	 * Key as year for last 12 months
	 * @var string
	 */
	const LAST_12_MONTHS = 'last12months';

	/**
	 * @var enum 
	 */
	const ANALYSIS_DEFAULT = 'kmorh';

	/**
	 * @var enum 
	 */
	const ANALYSIS_TRIMP = 'trimp';

	/**
	 * @var enum 
	 */
	const ANALYSIS_JD = 'jd';

	/**
	 * URL to window
	 * @var string
	 */
	static public $URL = 'call/window.plotSumData.php';

	/**
	 * URL to shared window
	 * @var string
	 */
	static public $URL_SHARED = 'call/window.plotSumData.shared.php';

	/**
	 * Year
	 * @var string
	 */
	protected $Year = '';

	/**
	 * Sport
	 * @var Sport
	 */
	protected $Sport = null;

	/**
	 * Raw data from database
	 * @var array
	 */
	protected $RawData = array();

	/**
	 * First week/month/etc.
	 * @var int
	 */
	protected $timerStart = 0;

	/**
	 * Last week/month/etc.
	 * @var int
	 */
	protected $timerEnd = 0;

	/**
	 * Show distance instead of time?
	 * @var bool
	 */
	protected $usesDistance = false;

	/**
	 * Which analysis to show
	 * @var enum
	 */
	protected $Analysis;

	/**
	 * Constructor
	 */
	public function __construct() {
		$sportid = strlen(Request::param('sportid')) > 0 ? Request::param('sportid') : Configuration::General()->runningSport();

		$this->Year  = Request::param('y') == self::LAST_12_MONTHS ? self::LAST_12_MONTHS : (int)Request::param('y');
		$this->Sport = new Sport($sportid);

		parent::__construct($this->getCSSid(), 800, 500);

		$this->init();
	}

	/**
	 * Display
	 */
	final public function display() {
		$this->displayHeader();
		$this->displayContent();
	}

	/**
	 * Display header
	 */
	private function displayHeader() {
		echo '<div class="panel-heading">';
		echo '<div class="panel-menu">';
		echo $this->getNavigationMenu();
		echo '</div>';
		echo HTML::h1( $this->getTitle() );
		echo '</div>';
	}

	/**
	 * Display content
	 */
	private function displayContent() {
		echo '<div class="panel-content">';
		$this->outputDiv();
		$this->outputJavaScript();
		$this->displayInfos();
		echo '</div>';
	}

	/**
	 * Display additional info
	 */
	protected function displayInfos() {}

	/**
	 * Get navigation
	 */
	private function getNavigationMenu() {
		$Menus = array(
			array(
				'title' => __('Choose analysis'),
				'links'	=> $this->getMenuLinksForGrouping()
			),
			array(
				'title' => __('Choose evaluation'),
				'links'	=> $this->getMenuLinksForAnalysis()
			),
			array(
				'title' => __('Choose sport'),
				'links'	=> $this->getMenuLinksForSports()
			),
			array(
				'title' => __('Choose year'),
				'links'	=> $this->getMenuLinksForYears()
			)
		);

		if (Request::param('group') == 'sport')
			unset($Menus[0]);

		$Code  = '<ul>';

		foreach ($Menus as $Menu) {
			$Code .= '<li class="with-submenu"><span class="link">'.$Menu['title'].'</span><ul class="submenu">';
			$Code .= implode('', $Menu['links']);
			$Code .= '</ul></li>';
		}

		$Code .= '</ul>';

		return $Code;
	}

	/**
	 * Get menu links for grouping
	 * @return array
	 */
	private function getMenuLinksForGrouping() {
		$Links = array();

		if ($this->Sport->isRunning())
			$Links[] = $this->link( __('Activity &amp; Competition'), $this->Year, Request::param('sportid'), '', Request::param('group') == '');
		else
			$Links[] = $this->link( __('Total'), $this->Year, Request::param('sportid'), '', Request::param('group') == '');

		$Links[] = $this->link( __('By type'), $this->Year, Request::param('sportid'), 'types', Request::param('group') == 'types');

		return $Links;
	}

	/**
	 * Get menu links for analysis
	 * @return array
	 */
	private function getMenuLinksForAnalysis() {
		$Links = array(
			$this->link( __('Distance/Duration'), $this->Year, Request::param('sportid'), Request::param('group'), $this->Analysis == self::ANALYSIS_DEFAULT, self::ANALYSIS_DEFAULT),
			$this->link( __('TRIMP'), $this->Year, Request::param('sportid'), Request::param('group'), $this->Analysis == self::ANALYSIS_TRIMP, self::ANALYSIS_TRIMP)
		);

		if ($this->Sport->isRunning()) {
			$Links[] = $this->link( __('JD points'), $this->Year, $this->Sport->id(), Request::param('group'), $this->Analysis == self::ANALYSIS_JD, self::ANALYSIS_JD);
		}

		return $Links;
	}

	/**
	 * Get menu links for sports
	 * @return array
	 */
	private function getMenuLinksForSports() {
		$Links = array(
			$this->link( __('All sports'), $this->Year, 0, 'sport', Request::param('group') == 'sport')
		);

		$SportGroup = Request::param('group') == 'sport' ? 'types' : Request::param('group');
		$Sports     = SportFactory::NamesAsArray();
		foreach ($Sports as $id => $name)
			$Links[] = $this->link($name, $this->Year, $id, $SportGroup, $this->Sport->id() == $id);

		return $Links;
	}

	/**
	 * Get menu links for years
	 * @return array
	 */
	private function getMenuLinksForYears() {
		$Links = array(
			$this->link(__('Last 12 months'), self::LAST_12_MONTHS, Request::param('sportid'), Request::param('group'), self::LAST_12_MONTHS == $this->Year)
		);

		for ($Y = date('Y'); $Y >= START_YEAR; $Y--)
			$Links[] = $this->link($Y, $Y, Request::param('sportid'), Request::param('group'), $Y == $this->Year);

		return $Links;
	}

	/**
	 * Link to plot
	 * @param string $text
	 * @param int $year
	 * @param int $sportid
	 * @param string $group
	 * @param boolean $current
	 * @return string
	 */
	private function link($text, $year, $sportid, $group, $current = false, $analysis = false) {
		if (!$analysis) {
			$analysis = $this->Analysis;
		}

		if (FrontendShared::$IS_SHOWN)
			return Ajax::window('<li'.($current ? ' class="active"' : '').'><a href="'.DataBrowserShared::getBaseUrl().'?view='.(Request::param('type')=='week'?'weekkm':'monthkm').'&type='.Request::param('type').'&y='.$year.'&sportid='.$sportid.'&group='.$group.'&analysis='.$analysis.'">'.$text.'</a></li>');
		else
			return Ajax::window('<li'.($current ? ' class="active"' : '').'><a href="'.self::$URL.'?type='.Request::param('type').'&y='.$year.'&sportid='.$sportid.'&group='.$group.'&analysis='.$analysis.'">'.$text.'</a></li>');
	}

	/**
	 * Get CSS id
	 * @return string
	 */
	abstract protected function getCSSid();

	/**
	 * Get title
	 * @return string
	 */
	abstract protected function getTitle();

	/**
	 * Get X labels
	 * @return array
	 */
	abstract protected function getXLabels();

	/**
	 * Init
	 */
	private function init() {
		$this->initData();
		$this->setAxis();
		$this->setOptions();
	}

	/**
	 * Set axis
	 */
	private function setAxis() {
		$this->setXLabels($this->getXLabels());
		$this->addYAxis(1, 'left');

		if ($this->Analysis == self::ANALYSIS_DEFAULT) {
			if ($this->usesDistance) {
				$this->addYUnit(1, 'km');
				$this->setYTicks(1, 10, 0);
			} else {
				$this->addYUnit(1, 'h');
				$this->setYTicks(1, 1, 0);
			}
		}
	}

	/**
	 * Set options
	 */
	private function setOptions() {
		$this->showBars(true);
		$this->setTitle($this->getTitle());

		$this->stacked();
	}

	/**
	 * Init data
	 */
	private function initData() {
		if (START_TIME != time() && (
			($this->Year >= START_YEAR && $this->Year <= date('Y') && START_TIME != time()) ||
			$this->Year == self::LAST_12_MONTHS
		)) {
			$this->defineAnalysis();
			$this->loadData();
			$this->setData();
		} else {
			$this->raiseError( __('There are no data for this timerange.') );
		}
	}

	/**
	 * Define analysis
	 */
	private function defineAnalysis() {
		$request = Request::param('analysis');

		if ($request == self::ANALYSIS_JD && $this->Sport->isRunning()) {
			$this->Analysis = self::ANALYSIS_JD;
		} elseif ($request == self::ANALYSIS_TRIMP) {
			$this->Analysis = self::ANALYSIS_TRIMP;
		} else {
			$this->Analysis = self::ANALYSIS_DEFAULT;
		}
	}

	/**
	 * Init to show year
	 */
	private function loadData() {
		$whereSport = (Request::param('group') == 'sport') ? '' : '`sportid`='.$this->Sport->id().' AND';

		$this->usesDistance = $this->Sport->usesDistance();
		if (Request::param('group') != 'sport' && $this->Analysis == self::ANALYSIS_DEFAULT && $this->usesDistance) {
			$num = DB::getInstance()->query('
				SELECT COUNT(*) FROM `'.PREFIX.'training`
				WHERE
					'.$whereSport.'
					`distance` = 0 AND `s` > 0 AND
				'.$this->whereDate().'
			')->fetchColumn();

			if ($num > 0)
				$this->usesDistance = false;
		}

		$this->RawData = DB::getInstance()->query('
			SELECT
				`sportid`,
				`typeid`,
				(`typeid` = '.Configuration::General()->competitionType().') as `wk`,
				'.$this->dataSum().' as `sum`,
				'.$this->timer().' as `timer`
			FROM `'.PREFIX.'training`
			WHERE
				'.$whereSport.'
				'.$this->whereDate().'
			GROUP BY '.$this->groupBy().', '.$this->timer()
		)->fetchAll();
	}

	/**
	 * @return string
	 */
	private function whereDate() {
		if (is_numeric($this->Year)) {
			return '`time` BETWEEN UNIX_TIMESTAMP(\''.(int)$this->Year.'-01-01\') AND UNIX_TIMESTAMP(\''.((int)$this->Year+1).'-01-01\')-1';
		} else {
			return '`time` >= '.$this->beginningOfLast12Months();
		}
	}

	/**
	 * @return int
	 */
	abstract protected function beginningOfLast12Months();

	/**
	 * Sum data for query
	 * @return string
	 */
	private function dataSum() {
		if ($this->Analysis == self::ANALYSIS_JD) {
			return 'SUM(`jd_intensity`)';
		} elseif ($this->Analysis == self::ANALYSIS_TRIMP) {
			return 'SUM(`trimp`)';
		} elseif ($this->usesDistance) {
			return 'SUM(`distance`)';
		}

		return 'SUM(`s`)/3600';
	}

	/**
	 * Timer table for query
	 * @return string
	 */
	abstract protected function timer();

	/**
	 * Group by table for query
	 * @return string
	 */
	private function groupBy() {
		if (Request::param('group') == 'sport')
			return '`sportid`';

		if (Request::param('group') == 'types' && $this->Sport->hasTypes())
			return '`typeid`';

		return '(`typeid` = '.Configuration::General()->competitionType().')';
	}

	/**
	 * Set data
	 */
	private function setData() {
		if (Request::param('group') == 'sport')
			$this->setDataForSports();
		elseif (Request::param('group') == 'types' && $this->Sport->hasTypes())
			$this->setDataForTypes();
		else
			$this->setDataForCompetitionAndTraining();

		if (empty($this->RawData))
			$this->setYLimits(1, 0, 10);
		else
			$this->setYLimitsFromData();
	}

	/**
	 * Set Y limits from data
	 */
	private function setYLimitsFromData() {
		$values = array();

		foreach ($this->Data as $data) {
			foreach ($data['data'] as $i => $val)
				if (!isset($values[$i]))
					$values[$i] = $val;
				else
					$values[$i] += $val;
		}

		$this->setYLimits(1, 0, Helper::ceilFor(max($values), 10));
	}

	/**
	 * Set data to compare training and competition
	 */
	private function setDataForSports() {
		$emptyData  = array_fill(0, $this->timerEnd - $this->timerStart + 1, 0);
		$Sports     = array();
		$SportsData = DB::getInstance()->query('
			SELECT
				id, name
			FROM
				`'.PREFIX.'sport`
		')->fetchAll();

		foreach ($SportsData as $Sport)
			$Sports[$Sport['id']] = array('name' => $Sport['name'], 'data' => $emptyData);

		foreach ($this->RawData as $dat)
			if ($dat['timer'] >= $this->timerStart && $dat['timer'] <= $this->timerEnd)
				$Sports[$dat['sportid']]['data'][$dat['timer']-$this->timerStart] = $dat['sum'];

		foreach ($Sports as $Sport)
			$this->Data[] = array('label' => isset($Sport['name']) ? $Sport['name'] : '?', 'data' => $Sport['data']);
	}

	/**
	 * Set data to compare training and competition
	 */
	private function setDataForTypes() {
		$emptyData = array_fill(0, $this->timerEnd - $this->timerStart + 1, 0);
		$Types     = array(array('name' => 'ohne', 'data' => $emptyData));
		$TypesData = DB::getInstance()->query('
			SELECT
				id, name
			FROM
				`'.PREFIX.'type`
			WHERE
				`sportid`="'.$this->Sport->id().'"
		')->fetchAll();

		foreach ($TypesData as $Type)
			$Types[$Type['id']] = array('name' => $Type['name'], 'data' => $emptyData);

		foreach ($this->RawData as $dat)
			if ($dat['timer'] >= $this->timerStart && $dat['timer'] <= $this->timerEnd)
				$Types[$dat['typeid']]['data'][$dat['timer']-$this->timerStart] = $dat['sum'];

		foreach ($Types as $Type)
			$this->Data[] = array('label' => $Type['name'], 'data' => $Type['data']);
	}

	/**
	 * Set data to compare training and competition
	 */
	private function setDataForCompetitionAndTraining() {
		$Kilometers            = array_fill(0, $this->timerEnd - $this->timerStart + 1, 0);
		$KilometersCompetition = array_fill(0, $this->timerEnd - $this->timerStart + 1, 0);

		foreach ($this->RawData as $dat) {
			if ($dat['timer'] >= $this->timerStart && $dat['timer'] <= $this->timerEnd) {
				if ($dat['wk'] == 1)
					$KilometersCompetition[$dat['timer']-$this->timerStart] = $dat['sum'];
				else
					$Kilometers[$dat['timer']-$this->timerStart] = $dat['sum'];
			}
		}

		// TODO: currently, only ONE competition type is allowed (and used for running)
		// if ($this->Sport->hasTypes())
		if ($this->Sport->isRunning())
			$this->Data[] = array('label' => __('Competition'), 'data' => $KilometersCompetition);

		$this->Data[] = array('label' => __('Activity'), 'data' => $Kilometers, 'color' => '#E68617');
	}
}