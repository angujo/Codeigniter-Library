<?php

require_once 'crud.proxy.inc.php';
require_once 'crud.table.inc.php';

/**
 * Created by PhpStorm.
 * User: Angujo Barrack
 * Date: 18-Feb-16
 * Time: 10:56 AM
 */
class CrudDB
{
	private static $CI;
	private static $DBS         = array();
	private static $db_user     = '';
	private static $db_host     = '';
	private static $db_password = '';
	private static $table;

	function __construct()
	{
		self::$CI          =& get_instance();
		self::$db_user     = LOCAL_SERVER ? 'root' : 'root';
		self::$db_password = LOCAL_SERVER ? 'root' : 'root';
		self::$db_host     = LOCAL_SERVER ? 'localhost' : 'localhost';
		self::$table       = new Ctable();
	}

	public function __get($name)
	{
		if (!isset($this->$name)) {
			$items      = preg_split('/(?=[A-Z])/', $name);
			$items      = explode('-', trim(implode('-', $items), '- '));
			$determiner = trim(array_shift($items));
			switch ($determiner) {
				case 'db':
					$databaseName = implode('_', array_map(function ($v) {
						return strtolower(trim($v));
					}, $items));
					if (!isset(self::$DBS[$databaseName])) {
						self::$DBS[$databaseName] = self::connect($databaseName);
					}
					self::$table->setDB(self::$DBS[$databaseName]);
					$this->$name = new Oproxy(self::$table);
				break;
			}
		}
		return $this->$name;
	}

	private static function connect($db_name)
	{
		if (!trim($db_name)) return FALSE;
		$config['hostname'] = self::$db_host;
		$config['username'] = self::$db_user;
		$config['password'] = self::$db_password;
		$config['database'] = $db_name;
		$config['dbdriver'] = 'mysqli';
		$config['dbprefix'] = '';
		$config['pconnect'] = FALSE;
		$config['db_debug'] = TRUE;
		$config['cache_on'] = FALSE;
		$config['cachedir'] = '';
		$config['char_set'] = 'utf8';
		$config['dbcollat'] = 'utf8_general_ci';
		return self::$CI->load->database($config, TRUE);
	}
}