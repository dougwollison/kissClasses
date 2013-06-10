<?php
/*
kissMySQL DB Class
Based off ezSQL, but even ez-er (http://justinvincent.com/ezsql)
...Also slightly stripped down; no logging for instance
*/

//Data type flags
define('OBJECT', md5('OBJECT'.time()), true);
define('OBJECT_K', md5('OBJECT_K'.time()), true);
define('ARRAY_A', md5('ARRAY_A'.time()), true);
define('ARRAY_N', md5('ARRAY_N'.time()), true);

//Skip Prepare Flag
define('NO_PREP', md5('NO_PREP'.time()), true);

class kissMySQL{
  public $ready;
	public $last_query;
	public $last_error;
	public $rows_affected;
	public $insert_id;
	public $result;
	public $col_info;
	public $last_result;
	public $field_types;

	private $dbuser;
	private $dbpass;
	private $dbname;
	private $dbhost;
	private $dbcharset;
	private $dbcollate;

	public function __construct($user, $pass, $name, $host = 'localhost', $charset = 'utf8', $collate = 'utf8_general_ci'){
		$this->dbuser = $user;
		$this->dbpass = $pass;
		$this->dbname = $name;
		$this->dbhost = $host;
		$this->dbcharset = $charset;
		$this->dbcollate = $collate;

		$this->connect();
	}

	private function connect(){
		$this->dbh = @mysql_connect($this->dbhost, $this->dbuser, $this->dbpass, $this->dbname);

		if(!$this->dbh) return false;

		$this->ready = true;

		$this->select($this->dbname);
	}

	public function select($db){
		if(!@mysql_select_db($db, $this->dbh)){
			$this->ready = false;
			return false;
		}
		return true;
	}

	private function _escape($string){
		if($this->dbh)
			return mysql_real_escape_string($string, $this->dbh);
		else
			return addslashes($string);
	}

	public function escape($data){
		if(is_array($data)){
			foreach($data as $k => $v){
				if(is_array($v))
					$data[$k] = $this->escape($v);
				else
					$data[$k] = $this->_escape($v);
			}
		} else {
			$data = $this->_escape($data);
		}

		return $data;
	}

	public function escape_by_ref(&$data){
		$data = $this->escape($data);
	}

	public function prepare($query){
		$args = func_get_args();

		array_shift($args);

		if(isset($args[0]) && is_array($args[0]))
			$args = $args[0];

		$query = preg_replace("/'%(?:\d+\$)?s'/", '%$1s', $query); // in case someone mistakenly already singlequoted it
		$query = preg_replace('/"%(?:\d+\$)?s"/', '%$1s', $query); // doublequote unquoting
		$query = preg_replace('/(?<!%)%(?:\d+\$)?s/', "'%$1s'", $query); // quote the strings, avoiding escaped strings like %%s

		array_walk($args, array(&$this, 'escape_by_ref'));
		return @vsprintf($query, $args);
	}

	private function auto_prepare(&$query = null, array $args = array(), &$output = OBJECT){
		if(is_null($query)) return;
		
		//See if NO_PREP flag is present, return the raw query if so.
		if(in_array(NO_PREP, $args)){
			return $query;
		}

		if(count($args) > 1){
			array_shift($args); //First arg would be the query

			//Check if the output type is the first arg, if so, shift it off to $output
			if(in_array($args[0], array(OBJECT, OBJECT_K, ARRAY_A, ARRAY_N)))
				$output = array_shift($args);

			//Check if the output type is the first arg, if so, shift it off to $output
			if(in_array(end($args), array(OBJECT, OBJECT_K, ARRAY_A, ARRAY_N)))
				$output = array_pop($args);

			//Check if there's only one argument and that it's an array, if so, make it the arugments array
			if(count($args) == 1 && is_array($args[0])){
				$args = $args[0];
			}

			//Now prepare the query with the arguments
			$query = $this->prepare($query, $args);
		}
	}

	public function flush(){
		$this->last_query 	 	= null;
		$this->last_error 	 	= null;
		$this->rows_affected 	= null;
		$this->insert_id  		= null;
		$this->result 	 		= null;
		$this->col_info 	 	= array();
		$this->last_result 	 	= array();
	}

	public function get_error(){
		if($this->last_error)
			return sprintf('Query: "%s" Error: "%s"', $this->last_query, $this->last_error);
	}

	public function print_error(){
		echo $this->get_error();
	}

	public function query($query){
		if(!$this->ready) return false;

		$this->auto_prepare($query, func_get_args());

		$return_val = 0;

		$this->flush();

		$this->last_query = $query;

		$this->result = @mysql_query($query, $this->dbh);

		if($this->last_error = mysql_error($this->dbh))
			return false;

		//Process result based on command
		if(preg_match('/^\s*(create|alter|truncate|drop)\s/i', $query)){
			$return_val = $this->result;
		}elseif(preg_match('/^\s*(insert|delete|update|replace)\s/i', $query)){
			$this->rows_affected = mysql_affected_rows($this->dbh);

			// Take note of the insert_id
			if(preg_match('/^\s*(insert|replace)\s/i', $query)){
				$this->insert_id = mysql_insert_id($this->dbh);
			}

			// Return number of rows affected
			$return_val = $this->rows_affected;
		}else{
			$i = 0;
			while($i < @mysql_num_fields($this->result)){
				$this->col_info[] = @mysql_fetch_field($this->result);
				$i++;
			}

			$num_rows = 0;
			while($row = @mysql_fetch_object($this->result)){
				$this->last_result[] = $row;
				$num_rows++;
			}

			@mysql_free_result($this->result);

			// Log number of rows the query returned
			// and return number of rows selected
			$this->num_rows = $return_val = $num_rows;
		}

		return $return_val;
	}

	private function _insert_replace_helper($table, $data, $format = null, $action = 'INSERT'){
		if(!in_array(strtoupper($type), array('REPLACE', 'INSERT')))
			return false;

		$formats = $format = (array) $format;
		$fields = array_keys($data);
		$formatted_fields = array();

		foreach($fields as $field){
			if(!empty($format))
				$form = ($form = array_shift($formats)) ? $form : $format[0];
			elseif(isset($this->field_types[$field]))
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$formatted_fields[] = $form;
		}

		$query = "$type INTO `$table` (`".implode('`,`', $fields)."`) VALUES ('".implode("','", $formatted_fields)."')";
		return $this->query($query, $data);
	}

	public function insert($table, $data, $format = null){
		return $this->_insert_replace_helper($table, $data, $format, 'INSERT');
	}

	public function replace($table, $data, $format = null){
		return $this->_insert_replace_helper($table, $data, $format, 'REPLACE');
	}

	public function update($table, $data, $where, $format = null, $where_format = null){
		if(!is_array($data) || !is_array($where))
			return false;

		$formats = $format = (array) $format;
		$bits = $wheres = array();

		foreach(array_keys($data) as $field){
			if(!empty($format))
				$form = ($form = array_shift($formats)) ? $form : $format[0];
			elseif(isset($this->field_types[$field]))
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$bits[] = "`$field` = $form";
		}

		$where_formats = $where_format = (array) $where_format;
		foreach((array) array_keys($where) as $field){
			if(!empty($where_format))
				$form = ($form = array_shift($where_formats)) ? $form : $where_format[0];
			elseif(isset($this->field_types[$field]))
				$form = $this->field_types[$field];
			else
				$form = '%s';
			$wheres[] = "`$field` = $form";
		}

		$query = "UPDATE `$table` SET ".implode(', ', $bits).' WHERE '.implode(' AND ',$wheres);
		return $this->query($query, array_merge(array_values($data), array_values($where)));
	}

	function delete($table, $where, $where_format = null){
		if(!is_array($where))
			return false;

		$bits = $wheres = array();

		$where_formats = $where_format = (array) $where_format;

		foreach(array_keys($where) as $field){
			if(!empty($where_format)){
				$form = ($form = array_shift($where_formats)) ? $form : $where_format[0];
			}elseif(isset($this->field_types[$field])){
				$form = $this->field_types[$field];
			}else{
				$form = '%s';
			}

			$wheres[] = "$field = $form";
		}

		$query = "DELETE FROM $table WHERE ".implode(' AND ', $wheres);
		return $this->query($query, $where);
	}

	public function get_results($query){
		$this->auto_prepare($query, func_get_args(), $output);

		if(!$output) $output = OBJECT;

		if($query)
			$this->query($query);
		else
			return null;

		$new_array = array();
		if($output == OBJECT){
			// Return an integer-keyed array of row objects
			return $this->last_result;
		}elseif($output == OBJECT_K){
			// Return an array of row objects with keys from column 1
			// (Duplicates are discarded)
			foreach($this->last_result as $row){
				$var_by_ref = get_object_vars($row);
				$key = array_shift($var_by_ref);
				if(!isset($new_array[$key]))
					$new_array[$key] = $row;
			}
			return $new_array;
		}elseif($output == ARRAY_A || $output == ARRAY_N){
			// Return an integer-keyed array of...
			if($this->last_result){
				foreach((array) $this->last_result as $row){
					if($output == ARRAY_N ){
						// ...integer-keyed row arrays
						$new_array[] = array_values(get_object_vars($row));
					}else{
						// ...column name-keyed row arrays
						$new_array[] = get_object_vars($row);
					}
				}
			}
			return $new_array;
		}
		return null;
	}

	public function get_row($query){
		$this->auto_prepare($query, func_get_args(), $output);

		return $this->get_row_y($query, $output);
	}

	public function get_row_y($query = null, $output = OBJECT, $y = 0){
		if(!is_null($query))
			$this->query($query);

		if(!isset($this->last_result[$y]))
			return null;

		if($output == ARRAY_A){
			return $this->last_result[$y] ? get_object_vars($this->last_result[$y]) : null;
		}elseif($output == ARRAY_N){
			return $this->last_result[$y] ? array_values(get_object_vars($this->last_result[$y])) : null;
		}else{
			return $this->last_result[$y] ? $this->last_result[$y] : null;
		}
	}

	public function get_col($query = null){
		$this->auto_prepare($query, func_get_args());

		return $this->get_col_x($query);
	}

	public function get_col_x($query = null, $x = 0){
		if(!is_null($query))
			$this->query($query);

		$new_array = array();
		// Extract the column values
		for($i = 0; $i < count($this->last_result); $i++){
			$values = array_values(get_object_vars($this->last_result[$i]));
			$new_array[$i] = $values[$x];
		}

		return $new_array;
	}

	public function get_var($query = null){
		$this->auto_prepare($query, func_get_args());

		return $this->get_var_x_y($query);
	}

	public function get_var_x_y($query = null, $x = 0, $y = 0){
		if(!is_null($query))
			$this->query($query);

		if(!isset($this->last_result[$y]))
			return null;

		$values = array_values(get_object_vars($this->last_result[$y]));

		return (isset($values[$x]) && $values[$x] !== '') ? $values[$x] : null;
	}

	public function get_col_info($info_type = 'name', $col_offset = -1){
		$this->load_col_info();

		if(is_array($this->col_info) && $this->col_info){
			if($col_offset == -1){
				$i = 0;
				$new_array = array();
				foreach($this->col_info as $col ){
					$new_array[$i] = $col->{$info_type};
					$i++;
				}
				return $new_array;
			}else{
				return $this->col_info[$col_offset]->{$info_type};
			}
		}
	}
	
	public function table_exists($table){
		return !is_null($this->get_var("SHOW TABLES LIKE %s", $table));
	}
}
