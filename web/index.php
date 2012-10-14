<?php
//class auto-loader
spl_autoload_register(function ($class) {
	$path = str_replace('\\', '/', $class);
	$path = __DIR__ . "/_lib/$path.php";
	if (file_exists($path)){
		require_once $path;
	}
});

// Composer auto-loader
require_once __DIR__ . '/_lib/Twig/Autoloader.php';
Twig_Autoloader::register();

// Set up Twig
$loader = new Twig_Loader_Filesystem(__DIR__ . '/_templates');
$twig = new Twig_Environment($loader, array(
    'cache' => __DIR__ . '/twig_cache',
	'auto_reload' => true, // Set to 'off' in production
));

//$servers = array('smp1', 'smp2', 'smp3', 'smp4', 'smp5', 'smp6', 'smp7', 'smp8', 'smp9', 'utopia');
$servers = array('smp7'); 

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
	
	$startTime = @$_GET['startTime'];
	$endTime = @$_GET['endTime'];
	if ($startTime === null || $endTime === null) {
		$errors[] = 'A start time and end time are required.';
	} else {
		$startTime = strtotime($startTime);
		$endTime = strtotime($endTime);
		if ($startTime === false || $endTime === false){
			$errors[] = 'Invalid start/end times.';
		} else if ($startTime > $endTime) {
			$errors[] = 'Start time must come before end time.';
		} else if ($endTime - $startTime > 60*60*24*7) {
			$errors[] = 'Time span cannot be longer than 1 week.';
		}
	}

	$x1 = @$_GET['x1'];
	$z1 = @$_GET['z1'];
	$x2 = @$_GET['x2'];
	$z2 = @$_GET['z2'];
	$coords = array($x1, $z1, $x2, $z2);
	$coordsEntered = 0;
	foreach ($coords as $coord){
		if ($coord !== null){
			$coordsEntered++;
		}
	}
	if ($coordsEntered > 0 && $coordsEntered < 4){
		$errors[] = 'Some coordinates are missing values.';
	} else if ($coordsEntered == 4){
		$numeric = true;
		foreach ($coords as $coord){
			if (!preg_match('/^-?\\d+$/', $coord)){
				$errors[] = 'Coordinates must be numeric and without decimals.';
				$numeric = false;
				break;
			}
		}
		
		if ($numeric){
			if ($x1 > $x2){
				$errors[] = 'X1 must be less than X2';
			}
			if ($z1 > $z2){
				$errors[] = 'Z1 must be less than Z2';
			}
		}
	}

	$player = @$_GET['player'];
	
	if (count($errors) == 0){
		//$dao = new DbDao(getenv('DB_HOST'), getenv('DB_NAME'), getenv('DB_USER'), getenv('DB_PASS'));
		$dao = new DbDao('localhost', 'emc_investigator', 'root', 'root', null, "/tmp/mysql.sock"); //mac
		//$dao = new DbDao('localhost', 'emc_investigator', 'root', 'root'); //netbook
		$results = $dao->getReadings('smp7', 'wilderness', $startTime, $endTime, $x1, $z1, $x2, $z2, $player);
	}
}

echo $twig->render('index.html', array(
	'errors' => $errors,
	'servers' => $servers,
	'selectedServer' => @$_GET['server'],
	'startTime' => @$_GET['startTime'],
	'endTime' => @$_GET['endTime'],
	'x1' => @$x1,
	'x2' => @$x2,
	'z1' => @$z1,
	'z2' => @$z2,
	'player' => @$player,
	'results' => @$results,
));

?>