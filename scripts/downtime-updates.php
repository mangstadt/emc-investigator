<?php
/*
 * Downloads map updates every minute from every server and builds an INSERT statement.
 * The INSERT statements are sent to stdout.
 * This script can be used while the live database is down so that the missing map updates can be inserted later.
 * Usage: php downtime-updates.php > insert-statements.sql  
 */

//class auto-loader
spl_autoload_register(function ($class) {
	$path = str_replace('\\', '/', $class);
	$path = __DIR__ . "/$path.php";
	if (file_exists($path)){
		require_once $path;
	}
});

date_default_timezone_set('America/New_York');

$servers = array('utopia');
for ($i = 1; $i <= 9; $i++){
	$servers[] = "smp$i";
}

$apis = array();
foreach ($servers as $server){
	$apis[] = new EmcMapApi($server, 'wilderness');
}

while(true){
	$nextRun = time() + 60;
	
	stderr("Running... ");
	for ($i = 0; $i < count($servers); $i++){
		stderr("$i ");
		$api = $apis[$i];
		$server = $servers[$i];
		
		$jsonStr = $api->getUpdate($timestamp, 10);
		if ($jsonStr === false || strlen($jsonStr) == 0){
			echo "--Error downloading player data for $server.  EMC might be down or your Internet connection might be down.\n";
		} else {
			$jsonObj = json_decode($jsonStr);
			if ($jsonObj === false || !isset($jsonObj->timestamp)){
				echo "--Error parsing JSON data for $server.  EMC might be down or your Internet connection might be down.\n";
			} else {
				//remove "updates" field (we don't care about tile updates)
				unset($jsonObj->updates);

				$ts = $jsonObj->timestamp / 1000;
				$tsStr = date('Y-m-d H:i:s', $ts);
				
				$jsonStr = json_encode($jsonObj);
				$jsonStr = str_replace("'", "\\'", $jsonStr);

				echo "INSERT INTO readings (ts, json, server_id) VALUES ('$tsStr', '$jsonStr', $i);\n";
			}
		}
	}
	stderr("\n");

	//sleep before the next run
	$sleep = $nextRun-time();
	if ($sleep < 0){
		stderr("Behind!\n");
	} else {
		stderr("Sleeping {$sleep}s...\n");
	}
	time_sleep_until($nextRun);
}

function stderr($msg){
	fwrite(STDERR, "$msg");
}
