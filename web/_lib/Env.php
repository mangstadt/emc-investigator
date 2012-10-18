<?php
/**
 * Stores environment-specific configuration settings.
 */
class Env {
	public static $dbHost, $dbName, $dbUser, $dbPass, $dbPort = 3306;
	public static $twigCache, $twigAutoReload;
	public static $enableHitCounter;
}
Env::$dbHost = getenv('DB_HOST');
Env::$dbName = getenv('DB_NAME');
Env::$dbUser = getenv('DB_USER');
Env::$dbPass = getenv('DB_PASS');
Env::$twigCache = __DIR__ . '/../../twig_cache';
Env::$twigAutoReload = false;
Env::$enableHitCounter = true;
