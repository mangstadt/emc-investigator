<?php
/*
 * This script downloads EMC Live Map data and persists it.
 */

//class auto-loader
spl_autoload_register(function ($class) {
	$path = str_replace('\\', '/', $class);
	$path = __DIR__ . "/$path.php";
	if (file_exists($path)){
		require_once $path;
	}
});

$stderr = fopen("php://stderr", "w");

//parse the command line arguments
$args = new Args("o:n:s:w:t:ph", array("output:", "name:", "server:", "world:", "timestamp:", "db:", "repeat:", "config:", "prettyPrint", "compress:", "stdout", "help"), array(null, "config"));

//print usage
if ($args->exists('h', 'help')){
	$scriptName = $argv[0];
	echo <<<USAGE
EMC Livemap Data Retriever
by shavingfoam

usage:   php $scriptName ARGS
example: php $scriptName --server=smp7 --world=wilderness --stdout

Arguments

-s SERVER, --server=SERVER (required)
  The server name.
  Example: --server=smp7

-w WORLD, --world=WORLD (required)
  The world to get the update from.
  Valid options are: town, wilderness, wilderness_nether
  
-t TIMESTAMP, --timestamp=TIMESTAMP
  The timestamp (in milliseconds) to include in the request. This will cause
  it to return info on the tiles that changed since this time.

-o DIR, --output=DIR
  The path to the directory where the JSON object will be saved.
  DEFAULTS to the current directory.

--compress=COMPRESS
  Compresses the JSON files, grouping them together by day.
  Valid options are: zip

-n FORMAT, --name=FORMAT
  The name to give the JSON file. Timestamps can be inserted into the
  file name. Just enclose a date format string in brackets.
  DEFAULTS to "update-{Ymd_His}.json".

--db=HOST,DATABASE,USERNAME,PASSWORD
  Persist the JSON data in a database.

--stdout
  Prints the JSON data to stdout.

-p, --prettyPrint
  Pretty-prints the JSON file.

--repeat=COUNT,INTERVAL
  Downloads the player data the specified number of times at the specified
  interval.  For example "--repeat=5,60" downloads the data five times with a
  one minute rest in between downloads.

--config=INI_FILE
  Read command-line parameters from an INI file.  The properties in the INI
  file are named after the long versions of the command.
  If command-line parameters are used in conjunction with the "config"
  parameter, then the command-line parameters will take precidence over the
  INI parameters.

USAGE;
	exit;
}

//get server name
$server = $args->value('s', 'server');
if ($server == null){
	fputs($stderr, "Error: \"--server\" parameter required.\n");
	exit(1);
}

//get world name
$world = $args->value('w', 'world');
if ($world == null){
	fputs($stderr, "Error: \"--world\" parameter required.\n");
	exit(1);
}

//get timestamp
$timestamp = $args->value('t', 'timestamp');

//get output directory
$outputDir = $args->value('o', 'output');
if ($outputDir == null){
	if (!$args->exists(null, 'db')){
		$outputDir = '.';
	}
} else if (!is_dir($outputDir)) {
	$success = mkdir($outputDir, 0744, true);
	if (!$success){
		fputs($stderr, "Error: Could not create directory \"$outputDir\".\n");
		exit(1);
	}
}

//get file name format
$fileName = $args->value('n', 'name');
if ($fileName == null){
	$fileName = 'update-{Ymd_His}.json';
}

//get repeat info
$repeat = $args->value(null, 'repeat');
if ($repeat != null){
	$repeat = preg_split('/,/', $repeat);
	$repeatCount = $repeat[0];
	$repeatInterval = $repeat[1];
} else {
	$repeatCount = 1;
	$repeatInterval = 0;
}

//get pretty-print flag
$prettyPrint = $args->exists('p', 'prettyPrint');

//compression to use if saving to a file
$compress = $args->value(null, 'compress');
if ($compress != null){
	$compress = strtolower($compress);
	if (!in_array($compress, array('zip', 'tar', 'tar.gz', 'tar.bz2'))){
		fputs($stderr, "Error: Invalid value for \"--compress\".\n");
		exit(1);
	}
}

//get stdout flag
$stdout = $args->exists(null, 'stdout');

//get DB info
$db = $args->value(null, 'db');
if ($db != null){
	$db = preg_split("/,/", $db, 4);
	$dbHost = $db[0];
	$dbDatabase = $db[1];
	$dbUsername = $db[2];
	$dbPassword = $db[3];
}

$lockFile = "$outputDir/lock";

//create API object
$api = new EmcMapApi($server, $world);

//get JSON
for ($curRepeatCount = 0; $curRepeatCount < $repeatCount; $curRepeatCount++){
	$nextRun = time() + $repeatInterval;

	$jsonStr = $api->getUpdate($timestamp);
	if ($jsonStr === false || strlen($jsonStr) == 0){
		fputs($stderr, "Error downloading player data.  EMC might be down or your Internet connection might be down.\n");
		exit(1);
	}

	$jsonObj = json_decode($jsonStr);
	if ($jsonObj === false){
		fputs($stderr, "Error parsing JSON data.  EMC might be down or your Internet connection might be down.\n");
		exit(1);
	}

	unset($jsonObj->updates); //remove "updates" field (we don't care about tile updates)
	$jsonStr = json_encode($jsonObj);
	if ($prettyPrint){
		$jsonStr = prettyPrintJson($jsonStr);
	}

	//print to file
	if ($outputDir != null){
		//generate the file name
		$jsonFileName = preg_replace_callback("~\\{(.*?)\\}~", function($matches){ return date($matches[1]); }, $fileName);

		//save JSON to disk
		if ($compress != null){
			$archiveFile = $outputDir . '/updates-' . date('Ymd') . '.' . $compress;
	
			//get lock for the folder
			if (file_exists($lockFile)){
				$fp = fopen($lockFile, 'r');
				flock($fp, LOCK_EX);
			}

			if ($compress == 'zip'){
				$zip = new ZipArchive();
				if (file_exists($archiveFile)){
					$zip->open($archiveFile);
				} else {
					$zip->open($archiveFile, ZipArchive::CREATE);
				}
				$zip->addFromString($jsonFileName, $jsonStr);
				$zip->close();
			} else {
				fputs($stderr, "Unknown compression type: $compress\n");
				exit(1);
			}
	
			//unlock
			if (isset($fp)){
				flock($fp, LOCK_UN);
			}
		} else {
			file_put_contents("$outputDir/$jsonFileName", $jsonStr);
		}
	}

	//save to DB
	if (isset($dbHost)){
		$mysqli = new mysqli($dbHost, $dbUsername, $dbPassword, $dbDatabase);
		if ($mysqli->connect_errno) {
			throw new Exception('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}

		//get id for SMP7 server
		$result = $mysqli->query("SELECT id from servers WHERE name = 'smp7'");
		if ($result->num_rows == 0){
			$mysqli->query("INSERT INTO servers (id, name) VALUES (7, 'smp7')");
			$serverId = $mysqli->insert_id;
		} else {
			$rows = $result->fetch_array();
			$serverId = $rows[0];
		}

		//get id for wilderness world
		$result = $mysqli->query("SELECT id from worlds WHERE name = 'wilderness'");
		if ($result->num_rows == 0){
			$mysqli->query("INSERT INTO worlds (name) VALUES ('wilderness')");
			$worldId = $mysqli->insert_id;
		} else {
			$rows = $result->fetch_array();
			$worldId = $rows[0];
		}
	
		$stmt = $mysqli->prepare("INSERT INTO readings (ts, json, server_id, world_id) VALUES (?, ?, $serverId, $worldId)");
		$ts = date('Y-m-d H:i:s');
		$stmt->bind_param('ss', $ts, $jsonStr);
		$stmt->execute();
	}

	//print to stdout
	if ($stdout){
		echo $jsonStr, "\n";
	}

	//sleep before the next run
	if ($curRepeatCount < $repeatCount - 1 && time() < $nextRun){
		time_sleep_until($nextRun);
	}
}

/**
 * Pretty prints a JSON string.
 * @param string $json the JSON string
 * @return string the pretty-printed JSON string
 * @see http://recursive-design.com/blog/2008/03/11/format-json-with-php/
 */
function prettyPrintJson($json) {

    $result      = '';
    $pos         = 0;
    $strLen      = strlen($json);
    $indentStr   = '  ';
    $newLine     = "\n";
    $prevChar    = '';
    $outOfQuotes = true;

    for ($i=0; $i<=$strLen; $i++) {

        // Grab the next character in the string.
        $char = substr($json, $i, 1);

        // Are we inside a quoted string?
        if ($char == '"' && $prevChar != '\\') {
            $outOfQuotes = !$outOfQuotes;
        
        // If this character is the end of an element, 
        // output a new line and indent the next line.
        } else if(($char == '}' || $char == ']') && $outOfQuotes) {
            $result .= $newLine;
            $pos --;
            for ($j=0; $j<$pos; $j++) {
                $result .= $indentStr;
            }
        }
        
        // Add the character to the result string.
        $result .= $char;

        // If the last character was the beginning of an element, 
        // output a new line and indent the next line.
        if (($char == ',' || $char == '{' || $char == '[') && $outOfQuotes) {
            $result .= $newLine;
            if ($char == '{' || $char == '[') {
                $pos ++;
            }
            
            for ($j = 0; $j < $pos; $j++) {
                $result .= $indentStr;
            }
        }
        
        $prevChar = $char;
    }

    return $result;
}
