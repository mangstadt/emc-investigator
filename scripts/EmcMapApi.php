<?php
/**
 * Provides an interface to the EMC map API.
 * @author Mike Angstadt
 */
class EmcMapApi {
	private $server;
	private $world;
	private $logOutput = false;
	private $cacheDir;

	/**
	 * @param string $server the EMC server name (e.g. "smp7")
	 * @param string $world the world type (e.g. "town")
	 */
	public function __construct($server, $world){
		$this->server = $server;
		$this->world = $world;
	}
	
	/**
	 * Sets how to log messages.
	 * @param string|boolean $logOutput the path to the log file, true to output to stdout, or false to disable logging (default)
	 */
	public function setLogOutput($logOutput){
		$this->logOutput = $logOutput;
	}
	
	/**
	 * Sets the directory that tile images will be cached to.
	 * @param $cacheDir the directory
	 * @return true on success, false if the directory could not be created
	 */
	public function setCacheDir($cacheDir){
		$cacheDir = "$cacheDir/{$this->server}/{$this->world}";
		if (!is_dir($cacheDir)) {
			$success = mkdir($cacheDir, 0744, true);
			if (!$success){
				return false;
			}
		}
		
		$dir2d = "$cacheDir/2d";
		if (!is_dir($dir2d)){
			$success = mkdir($dir2d);
			if (!$success){
				return false;
			}
		}
		
		$dir3d = "$cacheDir/3d";
		if (!is_dir($dir3d)){
			$success = mkdir($dir3d);
			if (!$success){
				return false;
			}
		}
		
		$this->cacheDir = $cacheDir;
		return true;
	}
	
	/**
	 * Gets the map API coordinates given X and Z coordinates.
	 * @param int $x the x-coordinate
	 * @param int $z the z-coordinate
	 * @return array(int) the map API coordinates
	 */
	public static function get2dCoords($x, $z){
		if ($x % 64 != 0){
			if ($x < 0){
				$temp = (int)($x / 64);
				$x = ($temp-1) * 64;
			} else if ($x > 0) {
				$x -= ($x % 64);
			}
		}
		if ($z % 64 != 0){
			if ($z < 0){
				$z += ($z % 64);
			} else if ($z > 0) {
				$temp = (int)($z / 64);
				$z = ($temp+1) * 64;
			}
		}
		
		$c = (int)($x / 64);
		if ($x < 0){
			$a = (int)(($c+1) / 32);
		} else {
			$a = (int)($c / 32);
		}
		if ($x < 0){
			$a--;
		}
		
		$d = (int)($z / 64);
		$d = -$d;
		if ($z < 0){
			$b = (int)($d / 32);
		} else {
			$b = (int)(($d+1) / 32);
		}
		if ($z > 0){
			$b--;
		}
		
		return array($a, $b, $c, $d);
	}

	/**
	 * Gets the map API coordinates given X and Z coordinates.
	 * @param int $x the x-coordinate
	 * @param int $z the z-coordinate
	 * @return array(int) the map API coordinates
	 */
	 /*
	public static function get2dCoords($x, $z){
		$a = (int)($x / 2084); //64*32
		$c = (int)($x / 64);
		if ($x < 0){
			$a -= 1;
		}
	 
		$b = (int)($z / 2084);
		$d = (int)($z / 64);
		if ($z > 0){
			$b += 1;
		}
		$b = -$b;
		$d = -$d;
	 
		if ($d != 0 && $d % 32 == 0){
			$b++;
		}
		if ($c != 0 && $c % 32 == 0){
			$a++;
		}

		return array($a, $b, $c, $d);
	}
	*/

	/*
	public static function getHdCoords($x, $z){
		//TODO
	}
	*/

	/**
	 * Gets a 2D tile image.
	 * @param int $x the x-coordinate
	 * @param int $z the z-coordinate
`	 * @param curl $curl the cURL resource to use for downloading the tiles
	 * @return image the image or false if there was an error getting it
	 */
	public function get2dTileImage($x, $z, $curl){
		$coords = self::get2dCoords($x, $z);
		$url = $this->buildTileUrl('flat', $coords);
		
		if ($this->cacheDir == null){
			$cacheFile = null;
		} else {
			$cacheFile = $this->buildCachedTilePath('2d', $coords);
		}
		return $this->getTileImage($url, $curl, $cacheFile);
	}

	/**
	 * Gets a 3D tile image.
	 * @param array(int) $coords the map API coords used in the URL
	 * @param curl $curl the cURL resource to use for downloading the tiles
	 * @return image the image or false if there was an error getting it
	 */
	public function get3dTileImage(array $coords, $curl){
		$tileType = ($this->world == World::NETHER) ? 'nt' : 't';
		$url = $this->buildTileUrl($tileType, $coords);
		
		if ($this->cacheDir == null){
			$cacheFile = null;
		} else {
			$cacheFile = $this->buildCachedTilePath('3d', $coords);
		}
		return $this->getTileImage($url, $curl, $cacheFile);
	}
	
	/**
	 * Builds a URL for getting a tile image.
	 * @param string $tile the tile type
	 * @param array(int) $coords the map API tile coords
	 * @return string the url
	 */
	public function buildTileUrl($tile, array $coords){
		return "http://{$this->server}.empire.us:8880/tiles/{$this->world}/$tile/{$coords[0]}_{$coords[1]}/{$coords[2]}_{$coords[3]}.png";
	}
	
	/**
	 * Gets the path to the tile's cached file.
	 * @param $type the tile type ("2d" or "3d")
	 * @param $coords the map API coordinates of the tile
	 * @return the path to the cached file, whether it exists or not
	 */
	private function buildCachedTilePath($type, $coords){
		return "{$this->cacheDir}/$type/{$coords[0]}_{$coords[1]}-{$coords[2]}_{$coords[3]}.png";
	}
	
	/**
	 * Gets a tile image.  If caching is enabled, then it gets the tile from the cache if the
	 * tile is not outdated.
	 * @param string $url the URL to the tile
	 * @param curl $curl the cURL resource to use for downloading the tiles
	 * @param string $cachedFile (optional) the path to the cached tile
	 * @return image the image
	 * @throws Exception if there was an error getting the image
	 */
	private function getTileImage($url, $curl, $cachedFile = null){
		$this->log("getting $url...");
		curl_setopt($curl, CURLOPT_URL, $url);
		
		//see: http://stackoverflow.com/questions/2208288/how-to-test-for-if-modified-since-http-header-support
		if ($cachedFile != null && file_exists($cachedFile)){
			$modDate = ' ' . gmdate('D, d M Y H:i:s \G\M\T', filemtime($cachedFile));
		} else {
			$modDate = ''; //since we're using the same cURL object, remove the header if the header was set for a previous tile
		}
		curl_setopt($curl, CURLOPT_HTTPHEADER, array("If-Modified-Since:$modDate"));
	
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); //response body is returned in the return value of curl_exec()
		$response = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
		
		if ($status == 304){
			//use the cached file
			$this->log("304 returned for: $url");
			if ($cachedFile == null){
				//this should never be reached, since the "If-Modified-Since" header is only included if $cacheFile != null
				throw new Exception("Response body is empty.");
			}
			return imagecreatefrompng($cachedFile);
		} else if ($status == 200){
			//save image to cache
			if ($cachedFile != null){
				file_put_contents($cachedFile, $response);
			}
			return imagecreatefromstring($response);
		} else if ($status == 404){
			$msg = "$status returned for \"$url\".";
			throw new Exception($msg);
		} else {
			$msg = "$status returned for \"$url\".  Response body ($contentType):";
			if (preg_match('~^text/(plain|html)~', $contentType)){
				$msg .= "\n$response";
			} else {
				$msg .= " <binary>";
			}
			throw new Exception($msg);
		}
	}

	/**
	 * Gets an image of the player's face.
	 * @param string $playerName the player name
	 * @return image the image or false if there was an error getting it
	 */
	public function getPlayerImage($playerName){
		$url = "http://{$this->server}.empire.us:8880/tiles/faces/32x32/$playerName.png";
		$this->log("getting $url...");
		return imagecreatefrompng($url);
	}

	/**
	 * Gets the update JSON object.
	 * @param int $lastUpdated (optional) the timestamp in milliseconds of the last time update was called.
	 * This is used to get the tiles that were updated since the last call
	 * @return string the JSON object or false if there was an error getting it
	 */
	public function getUpdate($lastUpdated = null){
		if ($lastUpdated == null){
			$lastUpdated = time() * 1000;
		}
		
		$url = "http://{$this->server}.empire.us:8880/up/world/{$this->world}/$lastUpdated";
		$this->log("getting $url...");
		return file_get_contents($url);
	}
	
	/**
	 * Constructs a 2D map of a given area.
	 * @param int $topLeftX the x-coordiate of the top-left corner
	 * @param int $topLeftZ the z-coordinate of the top-left corner
	 * @param int $botRightX the x-coordinate of the bottom-right corner
	 * @param int $botRightZ the z-coordinate of the bottom-right corner
	 * @return image the image
	 * @throws Exception if there's an error getting any of the tile images
	 */
	public function build2dMap($topLeftX, $topLeftZ, $botRightX, $botRightZ){
		//each tile image is 128x128 pixels
		$tilePixelSize = 128;
		
		//each tile image is 64x64 Minecraft blocks (each 2x2 block of pixels is 1 Minecraft block)
		$tileBlockCount = 64;
		
		$width = abs($topLeftX-$botRightX) * 2;
		$height = abs($topLeftZ-$botRightZ) * 2;
		$image = imagecreatetruecolor($width, $height);
		
		$imgX = 0;
		$imgY = 0;
		$curl = curl_init();
		for ($curZ = $topLeftZ; $curZ < $botRightZ; $curZ += $tileBlockCount){
			$imgX = 0;
			for ($curX = $topLeftX; $curX < $botRightX; $curX += $tileBlockCount){
				//get tile image
				$tile = $this->get2dTileImage($curX, $curZ, $curl);
				
				//copy tile onto main image
				imagecopy($image, $tile, $imgX, $imgY, 0, 0, imagesx($tile), imagesy($tile));
				imagedestroy($tile);
				$imgX += $tilePixelSize;
			}
			$imgY += $tilePixelSize;
		}
		curl_close($curl);
		
		return $image;
	}
	
	/**
	 * Constructs a 3D map of a given area.
	 * @param array(array(array(int))) the grid of tiles to download and arrange in a map.
	 * It is a 2D array of arrays that each hold 4 integers.  These integers are used to build the URL of the tile.
	 * @return image the image
	 * @throws Exception if there's an error getting any of the tile images
	 */
	public function build3dMap($grid){
		//each tile image is 128x128 pixels
		$tilePixelSize = 128;
		
		//get the size of the largest row
		$maxRowSize = 0;
		foreach ($grid as $row){
			$size = count($row);
			if ($size > $maxRowSize){
				$maxRowSize = $size;
			}
		}
	
		//create the image for the map
		$width = $maxRowSize * $tilePixelSize; 
		$height = count($grid) * $tilePixelSize;
		$image = imagecreatetruecolor($width, $height);

		$imgX = 0;
		$imgY = 0;
		$curl = curl_init();
		foreach ($grid as $row){
			$imgX = 0;
			foreach ($row as $coords){
				//get tile image
				$tile = $this->get3dTileImage($coords, $curl);
				
				//copy tile onto main image
				imagecopy($image, $tile, $imgX, $imgY, 0, 0, imagesx($tile), imagesy($tile));
				imagedestroy($tile);
				
				$imgX += $tilePixelSize;
			}
			$imgY += $tilePixelSize;
		}
		curl_close($curl);
		
		return $image;
	}

	/**
	 * Logs a message.
	 * @param string $msg the message to log
	 */
	private function log($msg){
		if ($this->logOutput !== false){
			$date = date('d-M-Y H:i:s');
			$msg = "[$date]: $msg\n";
			if ($this->logOutput === true){
				echo $msg;
			} else {
				$fh = fopen($this->logOutput, 'a');
				fwrite($fh, $msg);
				fclose($fh);
			}
		}
	}
}
