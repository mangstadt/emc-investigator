<?php
//class auto-loader
spl_autoload_register(function ($class) {
	$path = str_replace('\\', '/', $class);
	$path = __DIR__ . "/_lib/$path.php";
	if (file_exists($path)){
		require_once $path;
	}
});

//setup Twig
require_once __DIR__ . '/_lib/Twig/Autoloader.php';
Twig_Autoloader::register();
$loader = new Twig_Loader_Filesystem(__DIR__ . '/_templates');
$twig = new Twig_Environment($loader, array(
    'cache' => Env::$twigCache,
	'auto_reload' => Env::$twigAutoReload,
));

//define the list of servers
$servers = array('smp1', 'smp2', 'smp3', 'smp4', 'smp5', 'smp6', 'smp7', 'smp8', 'smp9', 'utopia');
$defaultServer = $servers[6];

//define the list of worlds
$worlds = array(
	new World('wilderness', 'frontier (wild)', 0),
	new World('wilderness_nether', 'frontier (nether)', -1),
	new World('town', 'town', 0),
	new World('wastelands', 'wastelands (wild)', 0),
	new World('wastelands_nether', 'wastelands (nether)', -1)
);
$defaultWorld = $worlds[0];
$worlds = indexWorlds($worlds);

$errors = array();
if (count($_GET) > 0){
	//sanitize input
	foreach ($_GET as $key=>$value){
		//trim value
		$_GET[$key] = trim($value);
		
		//if value is empty, unset it
		if ($value == ''){
			unset($_GET[$key]);
		} 
	}
	
	$server = @$_GET['server'];
	$world = @$worlds[@$_GET['world']];
	$startTime = @$_GET['startTime'];
	$endTime = @$_GET['endTime'];
	$x1 = @$_GET['x1'];
	$z1 = @$_GET['z1'];
	$x2 = @$_GET['x2'];
	$z2 = @$_GET['z2'];
	$players = @$_GET['players'];
	if ($players == null){
		$playersArray = array();
	} else{
		$playersArray = preg_split("/\\s*,\\s*/", $players);
	}
	$minimap = @$_GET['minimap'] != null;
	
	if ($server == null){
		$errors[] = 'Please select a server.';
	} else if (!in_array($server, $servers)){
		$errors[] = 'Invalid server selected.';
	}

	if ($world == null){
		$errors[] = 'Invalid world selected.';
	}
	
	if ($startTime === null || $endTime === null) {
		$errors[] = 'A start time and end time are required.';
	} else {
		$startTimeTs = strtotime($startTime);
		$endTimeTs = strtotime($endTime);
		if ($startTimeTs === false || $endTimeTs === false){
			$errors[] = 'Invalid start/end times.';
		} else if ($startTimeTs > $endTimeTs) {
			$errors[] = 'Start time must come before end time.';
		} else if ($endTimeTs - $startTimeTs > 60*60*24*7) {
			$errors[] = 'Time span cannot be longer than 1 week.';
		}
	}

	$coords = array($x1, $z1, $x2, $z2);
	$coordsEntered = 0;
	foreach ($coords as $coord){
		if ($coord !== null){
			$coordsEntered++;
		}
	}
	if ($coordsEntered > 0){
		if ($coordsEntered == 4){
			foreach ($coords as $coord){
				if (!preg_match('/^-?\\d+$/', $coord)){
					$errors[] = 'Coordinates must be numeric and without decimals.';
					break;
				}
			}	
		} else {
			$errors[] = 'Some coordinates are missing values.';
		}
	}

	if (count($errors) == 0){
		$dao = new DbDao(Env::$dbHost, Env::$dbName, Env::$dbUser, Env::$dbPass, Env::$dbPort);
		$results = $dao->getReadings($server, $world->value, $startTimeTs, $endTimeTs, 180, $x1, $z1, $x2, $z2, $playersArray);
		
		//generate minimap data
		if ($minimap){
			$colors = array("FF0000", "00FF00", "0000FF", "00FFFF", "FF00FF", "FFFF00", "FFFFFF", "000000");
			$curColor = 0;
			$playerColors = array();
			$generator = new ReisMinimapGenerator();
			foreach ($results as $result){
				if ($result->players != null){
					foreach ($result->players as $p){
						//get the waypoint color
						$color = @$playerColors[$p->name];
						if ($color == null){
							$color = $colors[$curColor++];
							$playerColors[$p->name] = $color;
							if ($curColor >= count($colors)){
								$curColor = 0;
							}
						}
						
						//add the waypoint
						$name = $p->name . " (" . date('Y-m-d H;i;s', $result->ts) . ")"; //note: colon chars cannot be used
						$generator->addWaypoint($name, $p->x, $p->y, $p->z, true, $color);
					}
				}
			}
			$minimapData = $generator->__toString();
			$minimapFilename = ReisMinimapGenerator::buildFilename("$server.empire.us", $world->minimapWorldId);
		}
	}
} else {
	//set default form values
	$server = $defaultServer;
	$world = $defaultWorld;
	$minimap = false;
}

//get GMT offset
$tz = new DateTimeZone(date_default_timezone_get());
$now = new DateTime();
$gmtOffsetHours = $tz->getOffset($now) / 3600;

//data collection start date
$dataStartDate = strtotime('2012-10-16');

echo $twig->render('index.html', array(
	'enableHitCounter' => Env::$enableHitCounter,
	'errors' => $errors,
	'servers' => $servers,
	'selectedServer' => $server,
	'worlds'=> $worlds,
	'selectedWorld' => $world,
	'startTime' => @$startTime,
	'endTime' => @$endTime,
	'x1' => @$x1,
	'x2' => @$x2,
	'z1' => @$z1,
	'z2' => @$z2,
	'players' => @$players,
	'minimap' => @$minimap,
	'results' => @$results,
	'minimapData' => @$minimapData,
	'minimapFilename' => @$minimapFilename,
	'gmtOffsetHours' => $gmtOffsetHours,
	'dataStartDate' => $dataStartDate
));

function indexWorlds($worlds){
	$map = array();
	foreach ($worlds as $world){
		$map[$world->value] = $world;
	}
	return $map;
}

class World{
	public $value;
	public $displayText;
	public $minimapWorldId;
	public function __construct($value, $displayText, $minimapWorldId){
		$this->value = $value;
		$this->displayText = $displayText;
		$this->minimapWorldId = $minimapWorldId;
	}
}

?>