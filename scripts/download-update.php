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
$args = new Args("o:n:s:w:t:ph", array("output:", "name:", "server:", "world:", "timestamp:", "db", "repeat:", "config:", "prettyPrint", "compress:", "stdout", "help"), array(null, "config"));

//print usage
if ($args->exists('h', 'help')){
	$scriptName = $argv[0];
	echo <<<USAGE
EMC Live Map Player Data Retriever
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
  Valid options are: zip, tar, tar.gz, tar.bz2

-n FORMAT, --name=FORMAT
  The name to give the JSON file. Timestamps can be inserted into the
  file name. Just enclose a date format string in brackets.
  DEFAULTS to "update-{Ymd_His}.json".

--db
  Persist the JSON data in the database.

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
if ($args->exists(null, 'db')){
	$dbHost = getenv('DB_HOST');
	$dbDatabase = getenv('DB_NAME');
	$dbUsername = getenv('DB_USER');
	$dbPassword = getenv('DB_PASS');
}

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

			if (strpos($compress, "tar") === 0){
				//see: http://pear.php.net/package/Archive_Tar
				require_once 'Archive/Tar.php';
				
				$dotPos = strpos($compress, '.');
				$tarType = null;
				if ($dotPos !== false){
					$tarType = substr($compress, $dotPos+1);
				}
				$tar = new Archive_Tar($archiveFile, $tarType);
				$tar->addString($jsonFileName, $jsonStr);
			} else if ($compress == 'zip'){
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
		} else {
			file_put_contents("$outputDir/$jsonFileName", $jsonStr);
		}
	}

	//save to DB
	if (isset($dbHost)){
		$mysqli = new mysqli($dbHost, $dbUsername, $dbPassword, $dbDatabase);
		//$mysqli = new mysqli('localhost', 'root', 'root', 'emc_investigator', 3306, '/tmp/mysql.sock'); //Mac
		if ($mysqli->connect_errno) {
			throw new Exception('Connect Error (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
		}

		//get id for server
		$sql = "SELECT id from servers WHERE name = '" . $mysqli->real_escape_string($server) . "'";
		$result = $mysqli->query($sql);
		if ($result === false){
			fputs($stderr, "Error: Problem running query: $sql\n");
			exit(1);
		} else if ($result->num_rows == 0){
			fputs($stderr, "Error: Server \"$server\" is not in the database (add a row to \"servers\" table).\n");
			exit(1);
		} else {
			$rows = $result->fetch_array();
			$serverId = $rows[0];
		}

		//get id for world
		$sql = "SELECT id from worlds WHERE name = '" . $mysqli->real_escape_string($world) . "'";
		$result = $mysqli->query($sql);
		if ($result === false){
			fputs($stderr, "Error: Problem running query: $sql\n");
			exit(1);
		} else if ($result->num_rows == 0){
			fputs($stderr, "Error: World \"$world\" is not in the database (add a row to \"worlds\" table).\n");
			exit(1);
		} else {
			$rows = $result->fetch_array();
			$worldId = $rows[0];
		}
	
		$stmt = $mysqli->prepare("INSERT INTO readings (ts, json, server_id, world_id) VALUES (?, ?, ?, ?)");
		$ts = date('Y-m-d H:i:s');
		$stmt->bind_param('ssii', $ts, $jsonStr, $serverId, $worldId);
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
