<?php
/*
 * This script deletes old EMC Live Map data from the database.
 */

//class auto-loader
spl_autoload_register(function ($class) {
	$path = str_replace('\\', '/', $class);
	$path = __DIR__ . "/$path.php";
	if (file_exists($path)){
		require_once $path;
	}
});

//parse the command line arguments
$args = new Args("d:h", array("days:", "help"));

//print usage
if ($args->exists('h', 'help')){
	$scriptName = $argv[0];
	echo <<<USAGE
usage:   php $scriptName ARGS
example: php $scriptName --days=90

Arguments

-d DAYS, --days=DAYS (required)
  The number of days old a reading can be before it is deleted.
  Example: --days=90

USAGE;
	exit;
}

//get days
$days = $args->value('d', 'days');
if ($days == null){
	error_log("Error: \"--days\" parameter required.");
	exit(1);
}
if (!preg_match('/^\\d+$/', $days)){
	error_log("Error: \"--days\" parameter must be a number.");
	exit(1);
}

$maxAge = time() - $days*24*60*60;

//create connection
$mysqli = db_connect();

//build SQL
$sql = "DELETE FROM readings WHERE ts <= ?";
$stmt = $mysqli->prepare($sql);
if ($stmt === false) {
	throw new Exception("Problem preparing SQL statement: $sql");
}

$maxAgeStr = date('Y-m-d H:i:s', $maxAge);
$stmt->bind_param('s', $maxAgeStr);
if (!$stmt->execute()){
	throw new Exception("Problem deleting old readings: " . $stmt->error);
}

function db_connect(){
	//get connection info
	$dbHost = getenv('DB_HOST');
	$dbDatabase = getenv('DB_NAME');
	$dbUsername = getenv('DB_USER');
	$dbPassword = getenv('DB_PASS');
	
	//create connection
	$mysqli = new mysqli($dbHost, $dbUsername, $dbPassword, $dbDatabase);
	//$mysqli = new mysqli('127.0.0.1', 'root', 'root', 'emc_investigator'); //Mac
	if ($mysqli->connect_errno) {
		throw new Exception('DB connection error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
	}
	
	return $mysqli;
}
