<?php
/**
 * Simplifies the parsing of command-line arguments.
 * @author Mike Angstadt
 */
class Args{
	/**
	 * The parsed arguments from the command line.
	 */
	private $commandLine;
	
	/**
	 * The parsed arguments from the config file or null if no
	 * config file was given.
	 */
	private $configFile;
	
	/**
	 * @param string $short the short argument names (see getopt() function)
	 * @param string $long the long argument names (see getopt() function)
	 * @param array(string) $configFileArg (optional) the short ([0]) and long ([1]) argument names for
	 * specifying a path to an INI config file that contains arguments
	 * @throws Exception if a config file was specified and it does not exist
	 */
	public function __construct($short, array $long, array $configFileArg = null){
		$this->commandLine = getopt($short, $long);
		if ($configFileArg != null){
			$path = $this->value($configFileArg[0], $configFileArg[1]);
			if ($path != null){
				if (!is_file($path)){
					throw new Exception("The specified config file does not exist: $path");
				}
				$this->configFile = parse_ini_file($path);
			}
		}
	}
	
	/**
	 * Gets the value of an argument.  If a config file was passed into the constructor,
	 * then the command-line arguments will take precedence over the config file arguments.
	 * @param string $short the short name
	 * @param string $long the long name
	 * @return string the argument value or null if not found
	 */
	public function value($short, $long){
		if (isset($this->commandLine[$short])){
			return $this->commandLine[$short];
		} else if (isset($this->commandLine[$long])){
			return $this->commandLine[$long];
		} else if ($this->configFile != null){
			return @$this->configFile[$long];
		}
		return null;
	}
	
	/**
	 * Determines if an argument exists or not.
	 * @param string $short the short name
	 * @param string $long the long name
	 * @return bool true if it exists, false if not
	 */
	public function exists($short, $long){
		return isset($this->commandLine[$short]) || isset($this->commandLine[$long]) || isset($this->configFile[$long]);
	}
}
