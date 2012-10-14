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
	
	public function __construct($ts, $players){
		$this->ts = $ts;
		$this->players = $players;
	}
}