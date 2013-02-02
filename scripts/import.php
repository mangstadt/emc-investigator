<?php
/*
 * This script imports all the TAR.GZ files into the database.
 */

//see: http://pear.php.net/package/Archive_Tar
require_once 'Archive/Tar.php';

//where the TAR files are located
$tarDir = '.';

//this file keeps track of the last TAR file that was processed, allowing the script to continue where it left off if it was terminated early
$statusFile = 'last-file.txt';

//database info
$dbHost = 'localhost';
$dbUser = 'root';
$dbPass = 'root';
$dbName = 'emc_investigator';
$dbPort = 3306;
$dbSocket = '/tmp/mysql.sock'; //Mac
//$dbSocket = null; //everything else

//=================================================

$mysqli = new mysqli($dbHost, $dbUser, $dbPass, $dbName, $dbPort, $dbSocket);
if ($mysqli->connect_errno) {
	throw new Exception('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
}

//get id for SMP7 server
$result = $mysqli->query("SELECT id FROM servers WHERE name = 'smp7'");
if ($result->num_rows == 0){
	$mysqli->query("INSERT INTO servers (id, name) VALUES (7, 'smp7')");
	$serverId = $mysqli->insert_id;
} else {
	$rows = $result->fetch_array();
	$serverId = $rows[0];
}

$mysqli->autocommit(false);

$stmt = $mysqli->prepare("INSERT INTO readings (ts, json, server_id) VALUES (?, ?, $serverId)");

//get list of all TAR files
$dirStack = array($tarDir);
$tarFiles = array();
$lastReadDay = 0;
if (file_exists($statusFile)){
	$lastReadDay = strtotime(file_get_contents($statusFile));
}
while (count($dirStack) > 0){
	$curDir = array_pop($dirStack);
	
	if ($handle = opendir($curDir)) {
		while (false !== ($fileName = readdir($handle))) {
			if ($fileName == '.' || $fileName == '..'){
				continue;
			}
			
			$path = "$curDir/$fileName";
			if (is_dir($path)){
				$dirStack[] = $path;
			} else if (preg_match("~updates-(\\d{8})\\.tar\\.gz$~", $fileName, $matches)){
				$day = strtotime($matches[1]);
				if ($day > $lastReadDay){
					$tarFiles[] = array($day, $path);
				}
			}
		}
		closedir($handle);
	}
}

//sort tar files by timestamp
usort($tarFiles, function($a, $b){
	return $a[0] - $b[0];
});

//insert TAR files into database
foreach ($tarFiles as $tarFile){
	$day = $tarFile[0];
	$tarFilePath = $tarFile[1];
	echo "Processing $tarFilePath...\n";

	//extract to temp dir
	$tempDir = sys_get_temp_dir() . '/' . microtime();
	mkdir($tempDir);
	$tarFileObj = new Archive_Tar($tarFilePath, 'gz');
	$tarFileObj->extract($tempDir);
	
	//get file list
	$files = array();
	if ($tempHandle = opendir($tempDir)) {
		while (false !== ($file = readdir($tempHandle))) {
			if ($file == '.' || $file == '..'){
				continue;
			}
			$files[] = $file;
		}
	}
	closedir($tempHandle);
	
	//sort by timestamp
	sort($files);
	
	//insert into database
	foreach ($files as $file){
		$path = "$tempDir/$file";
		
		$json = file_get_contents($path);
		$jsonObj = json_decode($json);
		if ($jsonObj === false || !isset($jsonObj->timestamp)){
			echo "  Invalid JSON in file $file.\n";
		} else {
			$ts = date('Y-m-d H:i:s', $jsonObj->timestamp / 1000);
			$stmt->bind_param('ss', $ts, $json);
			$stmt->execute();
		}
		
		unlink($path);
	}
	$mysqli->commit();
	rmdir($tempDir);
	file_put_contents($statusFile, date('Ymd', $day));
	
	echo "  done.\n";
}

/**
 * Gets the timestamp from the name of a JSON file.
 * @param string $fileName the JSON file name (e.g. "update-20121001_132144.json")
 * @return int the timestamp or null if the JSON file name is not in the correct format
 */
function getJsonFileTimestamp($fileName){
	if (preg_match('/^update-(\\d{8})_(\\d{6})\\.json$/', $fileName, $matches)){
		return strtotime($matches[1] . " " . $matches[2]);
	}
	return null;
}