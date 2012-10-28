<?php
class ReadingResult{
	/**
	 * The timestamp of the JSON file.
	 * @var int
	 */
	public $ts;
	
	/**
	 * The players in the JSON file that matched the search criteria.
	 * @var array
	 */
	public $players;
	
	/**
	 * If this object represents a data gap, then these fields will be populated. 
	 * @var int
	 */
	public $missingDataStart, $missingDataEnd;
	
	/**
	 * Creates a data gap result.
	 * @param int $missingDataStart the gap start time
	 * @param int $missingDataEnd the gap end time
	 */
	public static function gap($missingDataStart, $missingDataEnd){
		$result = new ReadingResult();
		$result->missingDataStart = $missingDataStart;
		$result->missingDataEnd = $missingDataEnd;
		return $result;
	}
	
	/**
	 * Creates a reading result.
	 * @param int $ts the timestamp of the reading
	 * @param array $players the players from the reading
	 */
	public static function reading($ts = null, $players = null){
		$result = new ReadingResult();
		$result->ts = $ts;
		$result->players = $players;
		return $result;
	}
}