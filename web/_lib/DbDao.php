<?php
class DbDao {
	private $mysqli;
	
	public function __construct($host, $db, $user, $pass, $port = 3306, $socket = null){
		if ($socket == null){
			$socket = ini_get("mysqli.default_socket");
		}
		$this->mysqli = new mysqli($host, $user, $pass, $db, $port, $socket);
		if ($this->mysqli->connect_errno) {
			throw new Exception('Connect Error (' . $this->mysqli->connect_errno . ') ' . $this->mysqli->connect_error);
		}
	}
	
	/**
	 * Gets the database ID for the given server.
	 * @param string $serverName the name of the server (e.g. "smp7")
	 * @return int the ID or null if not found
	 */
	public function getServerId($serverName){
		//get id for SMP7 server
		$stmt = $this->mysqli->prepare("SELECT id FROM servers WHERE name = ?");
		$stmt->bind_param('s', $serverName);
		$stmt->execute();
		$stmt->bind_result($id);
		$stmt->fetch();
		return $id;
	}
	
	/**
	 * @param string $server the server name (e.g. "smp7")
	 * @param string $world the world to search over ("wilderness", "wilderness_nether", or "town")
	 * @param int $startTime the start timestamp
	 * @param int $endTime the end timestamp
	 * @param int $gapInterval if a gap in the server data of this length (in seconds) is found, then a "gap" result will be returned in the results to show that no data exists for this time span
	 * @param int $x1 (optional) the x-coord of one of the corners of the bounded area
	 * @param int $z1 (optional) the z-coord of one of the corners of the bounded area
	 * @param int $x2 (optional) the x-coord of the opposite corner of the bounded area
	 * @param int $z2 (optional) the z-coord of the opposite corner of the bounded area
	 * @param array(string) $players (optional) the players to search for
	 * @return array(ReadingResult) the results
	 * @throws Exception if there's a database problem or a problem parsing the JSON
	 */
	public function getReadings($server, $world, $startTime, $endTime, $gapInterval = 180, $x1 = null, $z1 = null, $x2 = null, $z2 = null, $players = array()){
		$world = strtolower($world);
		
		//get server ID
		$serverId = $this->getServerId($server);
		if ($serverId === null){
			throw new Exception("Invalid server: $server");
		}

		//build SQL query
		$sql = "SELECT ts, json FROM readings WHERE";
		$sql .= " server_id = $serverId";
		if ($startTime != null){
			$sql .= " AND ts >= '" . date('Y-m-d H:i:s', $startTime) . "'";
		}
		if ($endTime != null){
			$sql .= " AND ts <= '" . date('Y-m-d H:i:s', $endTime) . "'";
		}
		$sql .= " ORDER BY ts";
		
		//execute query
		$uresult = $this->mysqli->query($sql, MYSQLI_USE_RESULT);
		if ($uresult === false) {
			throw new Exception("Problem executing query: $sql");
		}
		
		for ($i = 0; $i < count($players); $i++){
			$players[$i] = strtolower($players[$i]);
		}
		
		$readingResults = array();
		$prevTs = $startTime;
		while ($row = $uresult->fetch_assoc()) {
			$json = json_decode($row['json']);
			if ($json === false){
				throw new Exception("Problem parsing JSON: $json");
			} else {
				$ts = strtotime($row['ts']);
				
				//check for a data gap
				$diff = $ts - $prevTs;
				if ($diff > $gapInterval){
					if ($prevTs != $startTime){
						$prevTs += 60;
					}
					$readingResults[] = ReadingResult::gap($prevTs, $ts - 60);
				}
				
				if (isset($json->players)){
					$jsonPlayers = array();
					foreach ($json->players as $jsonPlayer){
						//skip players who are not in the specified world
						if (strtolower($jsonPlayer->world) != $world){
							continue;
						}
						
						if ($x1 !== null && $z1 !== null && $x2 !== null && $z2 !== null){
							//if coordinates were provided, check to see if the player is within those coordinates
							$x = $jsonPlayer->x;
							$z = $jsonPlayer->z;
							$matchedCoords = 
							($x1 <= $x && $x <= $x2 || $x2 <= $x && $x <= $x1) &&
							($z1 <= $z && $z <= $z2 || $z2 <= $z && $z <= $z1);
						} else {
							$matchedCoords = true;
						}
						
						if (count($players) > 0){
							//if player names were provided, check to see if any of them match
							$jsonPlayerName = strtolower($jsonPlayer->name);
							$matchedPlayer = false;
							foreach ($players as $player){
								if (strpos($jsonPlayerName, $player) !== false){
									$matchedPlayer = true;
									break;
								}
							}
						} else {
							$matchedPlayer = true;
						}
						
						if ($matchedCoords && $matchedPlayer){
							$jsonPlayers[] = $jsonPlayer;
						}
					}
					if ($jsonPlayers){
						$readingResults[] = ReadingResult::reading($ts, $jsonPlayers);
					}
				}
			}
			$prevTs = $ts;
		}
		
		$diff = $endTime - $prevTs;
		if ($diff > $gapInterval){
			if ($prevTs != $startTime){
				$prevTs += 60;
			}
			$readingResults[] = ReadingResult::gap($prevTs, $endTime);
		}
		
		return $readingResults;
	}
}