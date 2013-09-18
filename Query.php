<?php
/*
 * Static aliasing utility for kissMySQL
 */
require('kissMySQL.php');

class Query{
	protected static $dbh;
	protected static $vars = array(
		'user' => null,
		'pass' => null,
		'name' => null,
		'host' => 'localhost',
		'charset' => 'utf8',
		'collate' => 'utf8_general_ci'
	);

	public static function init($user = null, $pass = null, $name = null, $host = null, $charset = null, $collate = null){
		foreach(self::$vars as $var => $default){

		}

		self::$dbh = new kissMySQL($user, $pass, $name, $host, $charset, $collate);
	}

	public static function last(){
		return self::$dbh->get_last();
	}

	public static function affected(){
		return self::$dbh->affected_rows;
	}

	public static function run(){
		return call_user_func_array(array(self::$dbh, 'query'), func_get_args());
	}

	public static function insert(){
		return call_user_func_array(array(self::$dbh, 'insert'), func_get_args());
	}

	public static function replace(){
		return call_user_func_array(array(self::$dbh, 'replace'), func_get_args());
	}

	public static function update(){
		return call_user_func_array(array(self::$dbh, 'update'), func_get_args());
	}

	public static function updateByID(){
		return call_user_func_array(array(self::$dbh, 'update_by_id'), func_get_args());
	}

	public static function delete(){
		return call_user_func_array(array(self::$dbh, 'delete'), func_get_args());
	}

	public static function getVar(){
		return call_user_func_array(array(self::$dbh, 'get_var'), func_get_args());
	}

	public static function getVarXY(){
		return call_user_func_array(array(self::$dbh, 'get_var_x_y'), func_get_args());
	}

	public static function getCol(){
		return call_user_func_array(array(self::$dbh, 'get_col'), func_get_args());
	}

	public static function getColX(){
		return call_user_func_array(array(self::$dbh, 'get_col_x'), func_get_args());
	}

	public static function getRow(){
		return call_user_func_array(array(self::$dbh, 'get_row'), func_get_args());
	}

	public static function getResults(){
		return call_user_func_array(array(self::$dbh, 'get_results'), func_get_args());
	}

	public static function __callStatic($name, $arguments){
		$callback = array(self::$dbh, $name);

		if(is_callable($callback)){
			return call_user_func_array($callback, $arguments);
		}
	}

	public static function now(){
		return date('Y-m-d H:i:s');
	}
}