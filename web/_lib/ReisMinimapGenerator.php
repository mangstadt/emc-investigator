<?php
/**
 * <p>
 * Generates a Rei's Minimap waypoint data file.
 * </p>
 * 
 * <p>
 * Waypoint data is in the following format:<br>
 * <code>NAME:X:Y:Z:ENABLED:COLOR</code>
 * </p>
 * 
 * <p>
 * Example:<br>
 * <code>My Waypoint:10:20:-50:true:FF0000</code>
 * </p>
 * 
 * @see http://www.minecraftforum.net/topic/482147-142132125-oct25-reis-minimap-v32-05/
 */
class ReisMinimapGenerator {
	private $str = '';
	
	/**
	 * Adds a waypoint.
	 * @param string $name the waypoint name
	 * @param int $x the X coordinate
	 * @param int $y the Y coordinate
	 * @param int $z the Z coordinate
	 * @param boolean $enabled true to show the waypoint, false to hide it
	 * @param string $color the waypoint color (e.g. "FF0000" for "red")
	 */
	public function addWaypoint($name, $x, $y, $z, $enabled, $color){
		//remove colons
		$name = str_replace(":", " ", $name);
		
		$this->str .= $name;
		$this->str .= ":" . $x;
		$this->str .= ":" . $y;
		$this->str .= ":" . $z;
		$this->str .= ":" . ($enabled ? "true" : "false");
		$this->str .= ":" . $color;
		$this->str .= "\n";
	}
	
	/**
	 * Generates the filename of a waypoint file.
	 * @param string $serverUrl the server URL (e.g. "smp7.empire.us")
	 * @param int $worldId the world ID (e.g. "0")
	 */
	public static function buildFilename($serverUrl, $worldId){
		return "$serverUrl.DIM$worldId.points";
	}

	public function __toString(){
		return $this->str;
	}
}