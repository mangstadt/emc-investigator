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
	 * @param int $startTime the start timestamp
	 * @param int $endTime the end timestamp
	 * @param int $x1 (optional) the x-coord of the upper-left corner
	 * @param int $z1 (optional) the z-coord of the upper-left corner
	 * @param int $x2 (optional) the x-coord of the bottom-right corner
	 * @param int $z2 (optional) the z-coord of the bottom-right corner
	 * @param string $searchPlayer (optional) the player to search for
	 * @return array(ReadingResult) the results
	 * @throws Exception if there's a database problem or a problem parsing the JSON
	 */
	public function getReadings($server, $startTime, $endTime, $x1 = null, $z1 = null, $x2 = null, $z2 = null, $searchPlayer = null){
		//get server ID
		$serverId = $this->getServerId($server);
		if ($serverId == null){
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
		
		if ($searchPlayer != null){
			$searchPlayer = strtolower($searchPlayer);
		}
		
		$readingResults = array();
		while ($row = $uresult->fetch_assoc()) {
			$json = json_decode($row['json']);
			if ($json === false){
				throw new Exception("Problem parsing JSON: $json");
			} else {
				if (isset($json->players)){
					$players = array();
					foreach ($json->players as $player){
						if ($x1 !== null && $z1 !== null && x2 !== null && z2 !== null){
							//if coordinates were provided, check to see if the player is within those coordinates
							$x = $player->x;
							$z = $player->z;
							$matchedCoords = $x >= $x1 && $x <= $x2 && $z >= $z1 && $z <= $z2;
						} else {
							$matchedCoords = true;
						}
						
						if ($searchPlayer !== null){
							//if a player name was provided, check to see if it matches
							$matchedPlayer = strpos(strtolower($player->name), $searchPlayer) !== false;
						} else {
							$matchedPlayer = true;
						}
						
						if ($matchedCoords && $matchedPlayer){
							$players[] = $player;
						}
					}
					if ($players){
						$readingResults[] = new ReadingResult(strtotime($row['ts']), $players);
					}
				}
			}	
		}
		
		return $readingResults;
	}
}