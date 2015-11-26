<?php

namespace Runalyze\Model\Activity;

use Runalyze\Configuration;
use Runalyze\Model;
use Runalyze\Data\Weather;

use PDO;

/**
 * Generated by hand
 */
class InserterTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @var \PDO
	 */
	protected $PDO;

	protected $OutdoorID;
	protected $IndoorID;

	protected $EquipmentType;
	protected $EquipmentA;
	protected $EquipmentB;
	protected $EquipmentC;

	protected function setUp() {
		\Cache::clean();
		$this->PDO = \DB::getInstance();
		$this->PDO->exec('INSERT INTO `'.PREFIX.'sport` (`name`,`kcal`,`outside`,`accountid`,`power`) VALUES("",600,1,0,1)');
		$this->OutdoorID = $this->PDO->lastInsertId();
		$this->PDO->exec('INSERT INTO `'.PREFIX.'sport` (`name`,`kcal`,`outside`,`accountid`,`power`) VALUES("",400,0,0,0)');
		$this->IndoorID = $this->PDO->lastInsertId();
		$this->PDO->exec('INSERT INTO `'.PREFIX.'equipment_type` (`name`,`accountid`) VALUES("Type",0)');
		$this->EquipmentType = $this->PDO->lastInsertId();
		$this->PDO->exec('INSERT INTO `'.PREFIX.'equipment_sport` (`sportid`,`equipment_typeid`) VALUES('.$this->OutdoorID.','.$this->EquipmentType.')');
		$this->PDO->exec('INSERT INTO `'.PREFIX.'equipment` (`name`,`typeid`,`notes`,`accountid`) VALUES("A",'.$this->EquipmentType.',"",0)');
		$this->EquipmentA = $this->PDO->lastInsertId();
		$this->PDO->exec('INSERT INTO `'.PREFIX.'equipment` (`name`,`typeid`,`notes`,`accountid`) VALUES("B",'.$this->EquipmentType.',"",0)');
		$this->EquipmentB = $this->PDO->lastInsertId();
		$this->PDO->exec('INSERT INTO `'.PREFIX.'equipment` (`name`,`typeid`,`notes`,`accountid`) VALUES("C",'.$this->EquipmentType.',"",0)');
		$this->EquipmentC = $this->PDO->lastInsertId();

		$Factory = new Model\Factory(0);
		$Factory->clearCache('sport');
		\SportFactory::reInitAllSports();
	}

	protected function tearDown() {
		$this->PDO->exec('DELETE FROM `'.PREFIX.'training`');
		$this->PDO->exec('DELETE FROM `'.PREFIX.'sport`');
		$this->PDO->exec('DELETE FROM `'.PREFIX.'equipment_type`');

		$Factory = new Model\Factory(0);
		$Factory->clearCache('sport');
		\Cache::clean();
	}

	/**
	 * @param array $data
	 * @return int
	 */
	protected function insert(array $data) {
		$Inserter = new Inserter($this->PDO, new Object($data));
		$Inserter->setAccountID(0);
		$Inserter->insert();

		return $Inserter->insertedID();
	}

	/**
	 * @param int $id
	 * @return \Runalyze\Model\Activity\Object
	 */
	protected function fetch($id) {
		return new Object(
			$this->PDO->query('SELECT * FROM `'.PREFIX.'training` WHERE `id`="'.$id.'" AND `accountid`=0')->fetch(PDO::FETCH_ASSOC)
		);
	}

	/**
	 * @expectedException \PHPUnit_Framework_Error
	 */
	public function testWrongObject() {
		new Inserter($this->PDO, new Model\Trackdata\Object);
	}

	public function testSimpleInsert() {
		$Object = $this->fetch(
			$this->insert(array(
				Object::TIME_IN_SECONDS => 3600,
				Object::DISTANCE => 12.0
			))
		);

		$this->assertEquals(time(), $Object->get(Object::TIMESTAMP_CREATED), '', 10);
		$this->assertEquals(3600, $Object->duration());
		$this->assertEquals(12.0, $Object->distance());
	}

	public function testOutdoorData() {
		$Object = $this->fetch(
			$this->insert(array(
				Object::TIME_IN_SECONDS => 3600,
				Object::WEATHERID => Weather\Condition::SUNNY,
				Object::TEMPERATURE => 7,
				Object::SPORTID => $this->OutdoorID
			))
		);

		$this->assertEquals(Weather\Condition::SUNNY, $Object->weather()->condition()->id());
		$this->assertEquals(7, $Object->weather()->temperature()->value());
	}

	public function testIndoorData() {
		$Object = $this->fetch(
			$this->insert(array(
				Object::TIME_IN_SECONDS => 3600,
				Object::WEATHERID => Weather\Condition::SUNNY,
				Object::TEMPERATURE => 7,
				Object::SPORTID => $this->IndoorID
			))
		);

		$this->assertTrue($Object->weather()->isEmpty());
	}

	public function testCalories() {
		$ObjectWithout = $this->fetch(
			$this->insert(array(
				Object::TIME_IN_SECONDS => 3600,
				Object::SPORTID => $this->OutdoorID
			))
		);

		$this->assertEquals(600, $ObjectWithout->calories());

		$ObjectWith = $this->fetch(
			$this->insert(array(
				Object::TIME_IN_SECONDS => 3600,
				Object::SPORTID => $this->OutdoorID,
				Object::CALORIES => 873
			))
		);

		$this->assertEquals(873, $ObjectWith->calories());
	}

	public function testStartTimeUpdate() {
		$current = time();
		$timeago = mktime(0,0,0,1,1,2000);

		Configuration::Data()->updateStartTime($current);

		$this->insert(array(
			Object::TIMESTAMP => $current
		));

		$this->assertEquals($current, Configuration::Data()->startTime());

		$this->insert(array(
			Object::TIMESTAMP => $timeago
		));

		$this->assertEquals($timeago, Configuration::Data()->startTime());
	}

	public function testCalculationsForRunning() {
		$Object = $this->fetch( $this->insert(array(
			Object::DISTANCE => 10,
			Object::TIME_IN_SECONDS => 3000,
			Object::HR_AVG => 150,
			Object::SPORTID => Configuration::General()->runningSport()
		)));

		$this->assertGreaterThan(0, $Object->vdotByTime());
		$this->assertGreaterThan(0, $Object->vdotByHeartRate());
		$this->assertGreaterThan(0, $Object->vdotWithElevation());
		$this->assertGreaterThan(0, $Object->jdIntensity());
		$this->assertGreaterThan(0, $Object->trimp());
	}

	public function testCalculationsForNotRunning() {
		$Object = $this->fetch( $this->insert(array(
			Object::DISTANCE => 10,
			Object::TIME_IN_SECONDS => 3000,
			Object::HR_AVG => 150,
			Object::SPORTID => Configuration::General()->runningSport() + 1
		)));

		$this->assertEquals(0, $Object->vdotByTime());
		$this->assertEquals(0, $Object->vdotByHeartRate());
		$this->assertEquals(0, $Object->vdotWithElevation());
		$this->assertEquals(0, $Object->jdIntensity());
		$this->assertGreaterThan(0, $Object->trimp());
	}

	public function testVDOTstatisticsUpdate() {
		$current = time();
		$timeago = mktime(0,0,0,1,1,2000);
		$running = Configuration::General()->runningSport();
		$raceid = Configuration::General()->competitionType();

		Configuration::Data()->updateVdotShape(0);
		Configuration::Data()->updateVdotCorrector(1);

		$this->insert(array(
			Object::TIMESTAMP => $timeago,
			Object::DISTANCE => 10,
			Object::TIME_IN_SECONDS => 30*60,
			Object::HR_AVG => 150,
			Object::SPORTID => $running,
			Object::TYPEID => $raceid + 1,
			Object::USE_VDOT => true
		));
		$this->insert(array(
			Object::TIMESTAMP => $current,
			Object::DISTANCE => 10,
			Object::TIME_IN_SECONDS => 30*60,
			Object::HR_AVG => 150,
			Object::SPORTID => $running + 1,
			Object::USE_VDOT => true
		));
		$this->insert(array(
			Object::TIMESTAMP => $current,
			Object::DISTANCE => 10,
			Object::TIME_IN_SECONDS => 30*60,
			Object::SPORTID => $running,
			Object::USE_VDOT => true
		));

		$this->assertEquals(0, Configuration::Data()->vdotShape());
		$this->assertEquals(1, Configuration::Data()->vdotFactor());

		$this->insert(array(
			Object::TIMESTAMP => $current,
			Object::DISTANCE => 10,
			Object::TIME_IN_SECONDS => 30*60,
			Object::HR_AVG => 150,
			Object::SPORTID => $running,
			Object::TYPEID => $raceid + 1,
			Object::USE_VDOT => true
		));

		$this->assertNotEquals(0, Configuration::Data()->vdotShape());
		$this->assertEquals(1, Configuration::Data()->vdotFactor());

		$this->insert(array(
			Object::TIMESTAMP => $current,
			Object::DISTANCE => 10,
			Object::TIME_IN_SECONDS => 30*60,
			Object::HR_AVG => 150,
			Object::SPORTID => $running,
			Object::TYPEID => $raceid,
			Object::USE_VDOT => true
		));

		$this->assertNotEquals(0, Configuration::Data()->vdotShape());
		$this->assertNotEquals(1, Configuration::Data()->vdotFactor());
	}

	public function testWithCalculationsFromAdditionalObjects() {
		$Activity = new Object(array(
			Object::DISTANCE => 10,
			Object::TIME_IN_SECONDS => 3000,
			Object::HR_AVG => 150,
			Object::SPORTID => Configuration::General()->runningSport()
		));

		$Inserter = new Inserter($this->PDO);
		$Inserter->setAccountID(0);
		$Inserter->insert($Activity);
		$ObjectWithout = $this->fetch( $Inserter->insertedID() );

		$Inserter->setTrackdata(new Model\Trackdata\Object(array(
			Model\Trackdata\Object::TIME => array(1500, 3000),
			Model\Trackdata\Object::HEARTRATE => array(125, 175)
		)));
		$Inserter->setRoute(new Model\Route\Object(array(
			Model\Route\Object::ELEVATION_UP => 500,
			Model\Route\Object::ELEVATION_DOWN => 100
		)));

		$Inserter->insert($Activity);
		$ObjectWith = $this->fetch( $Inserter->insertedID());

		$this->assertGreaterThan($ObjectWithout->vdotWithElevation(), $ObjectWith->vdotWithElevation());
		$this->assertGreaterThan($ObjectWithout->jdIntensity(), $ObjectWith->jdIntensity());
		$this->assertGreaterThan($ObjectWithout->trimp(), $ObjectWith->trimp());
	}

	public function testWithSwimdata() {
		$Activity = new Object(array(
			Object::DISTANCE => 0.2,
			Object::TIME_IN_SECONDS => 120,
		));

		$Inserter = new Inserter($this->PDO);
		$Inserter->setAccountID(0);
		$Inserter->setTrackdata(new Model\Trackdata\Object(array(
			Model\Trackdata\Object::TIME => array(30, 60, 90, 120),
			Model\Trackdata\Object::DISTANCE => array(0.05, 0.1, 0.15, 0.2)
		)));
		$Inserter->setSwimdata(new Model\Swimdata\Object(array(
			Model\Swimdata\Object::STROKE => array(25, 20, 15, 20)
		)));
		$Inserter->insert($Activity);
		$Result = $this->fetch( $Inserter->insertedID());

		$this->assertEquals(80, $Result->totalStrokes());
		$this->assertEquals(50, $Result->swolf());
	}

	public function testTemperature() {
		$Zero = $this->fetch(
			$this->insert(array(
				Object::TEMPERATURE => 0
			))
		);

		$this->assertEquals(0, $Zero->weather()->temperature()->value());
		$this->assertFalse($Zero->weather()->temperature()->isUnknown());
		$this->assertFalse($Zero->weather()->isEmpty());
	}

	public function testPowerCalculation() {
		// TODO: Needs configuration setting
		if (Configuration::ActivityForm()->computePower()) {
			$ActivityIndoor = new Object(array(
				Object::DISTANCE => 10,
				Object::TIME_IN_SECONDS => 3000,
				Object::SPORTID => $this->IndoorID
			));

			$Trackdata = new Model\Trackdata\Object(array(
				Model\Trackdata\Object::TIME => array(1500, 3000),
				Model\Trackdata\Object::DISTANCE => array(5, 10)
			));

			$Inserter = new Inserter($this->PDO);
			$Inserter->setAccountID(0);
			$Inserter->setTrackdata($Trackdata);
			$Inserter->insert($ActivityIndoor);

			$this->assertEquals(0, $this->fetch($Inserter->insertedID())->power());

			$ActivityOutdoor = clone $ActivityIndoor;
			$ActivityOutdoor->set(Object::SPORTID, $this->OutdoorID);
			$Inserter->insert($ActivityOutdoor);

			$this->assertNotEquals(0, $this->fetch($Inserter->insertedID())->power());
			$this->assertNotEmpty($Trackdata->power());
		}
	}

	public function testEquipment() {
		$this->PDO->exec('UPDATE `runalyze_equipment` SET `distance`=0, `time`=0 WHERE `id`='.$this->EquipmentA);
		$this->PDO->exec('UPDATE `runalyze_equipment` SET `distance`=1, `time`=600 WHERE `id`='.$this->EquipmentB);
		$this->PDO->exec('UPDATE `runalyze_equipment` SET `distance`=0, `time`=0 WHERE `id`='.$this->EquipmentC);

		$Inserter = new Inserter($this->PDO);
		$Inserter->setAccountID(0);
		$Inserter->setEquipmentIDs(array($this->EquipmentA, $this->EquipmentB));
		$Inserter->insert(new Object(array(
			Object::DISTANCE => 10,
			Object::TIME_IN_SECONDS => 3600,
			Object::SPORTID => $this->OutdoorID
		)));

		$this->assertEquals(array(10, 3600), $this->PDO->query('SELECT `distance`, `time` FROM `runalyze_equipment` WHERE `id`='.$this->EquipmentA)->fetch(PDO::FETCH_NUM));
		$this->assertEquals(array(11, 4200), $this->PDO->query('SELECT `distance`, `time` FROM `runalyze_equipment` WHERE `id`='.$this->EquipmentB)->fetch(PDO::FETCH_NUM));
		$this->assertEquals(array( 0,    0), $this->PDO->query('SELECT `distance`, `time` FROM `runalyze_equipment` WHERE `id`='.$this->EquipmentC)->fetch(PDO::FETCH_NUM));
	}

	/**
	 * @group verticalRatio
	 */
	public function testStrideLengthAndVerticalRatioCalculation() {
		$Activity = new Object(array(
			Object::DISTANCE => 0.36,
			Object::TIME_IN_SECONDS => 120,
			Object::SPORTID => Configuration::General()->runningSport()
		));

		$Trackdata = new Model\Trackdata\Object(array(
			Model\Trackdata\Object::TIME => array(60, 120),
			Model\Trackdata\Object::DISTANCE => array(0.18, 0.36),
			Model\Trackdata\Object::CADENCE => array(90, 100),
			Model\Trackdata\Object::VERTICAL_OSCILLATION => array(90, 80)
		));

		$Inserter = new Inserter($this->PDO);
		$Inserter->setAccountID(0);
		$Inserter->setTrackdata($Trackdata);
		$Inserter->insert($Activity);

		$this->assertEquals(95, $this->fetch($Inserter->insertedID())->strideLength());
		$this->assertEquals(array(100, 90), $Trackdata->strideLength());

		$this->assertEquals(90, $this->fetch($Inserter->insertedID())->verticalRatio());
		$this->assertEquals(array(90, 89), $Trackdata->verticalRatio());
	}

}
