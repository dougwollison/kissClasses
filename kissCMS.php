<?php
/*
kissCMS Class
For building a basic CMS, extends kissMySQL
*/
require_once('kissMySQL.php');

class kissCMS extends kissMySQL{
	public $allowed_actions;
	protected $objects;
	protected $object_relations;
	protected $object_formats;

	public function __construct($user, $pass, $name, $host = 'localhost', $charset = 'utf8', $collate = 'utf8_general_ci'){
		parent::__construct($user, $pass, $name, $host, $charset, $collate);
	}

	/**
	 * ==============
	 * Object Methods
	 * ==============
	 */

	public function add_object($object, $data, $meta = null, $replace = false){
		$inserts = array();
		$formats = array();

		if(isset($this->object_formats[$object])){
			foreach($data as $key => $value){
				if(array_key_exists($key, $this->object_formats[$object])){
					$inserts[$key] = $value;
					if(isset($this->object_formats[$object][$key])){
						$formats[] = $this->object_formats[$object][$key];
					}else{
						$formats[] = '%s';
					}
				}
			}
		}else{
			foreach($data as $key => $value){
				$inserts[$key] = $value;
				$formats[] = '%s';
			}
		}

		$action = $replace ? 'replace' : 'insert';

		$this->$action($object.'s', $inserts, $formats);
		$id = $this->insert_id;

		if(!$id) return false;

		if(is_array($meta)){
			foreach((array) $meta as $key => $value){
				$this->add_meta_data($object, $id, $key, $value);
			}
		}

		return $id;
	}

	public function get_object($type, $id, $fields = '*', $meta = null){
		return $this->get_object_by($type, 'ID', $id, $fields, $meta);
	}

	public function get_object_by($type, $field, $value, $fields = '*', $meta = null){
		if(is_array($fields)) $fields = implode(',', $fields);

		$f = '%s';
		if(is_numeric($value)) $f = '%d';

		if(is_null($fields)) $fields = '*';

		$object = $this->get_row("SELECT $fields FROM {$type}s WHERE $field = $f", $value);

		if(!$object) return false;

		if(!is_null($meta) && $this->table_exists($type.'_meta')){
			$object->meta = array();
			if($meta !== true){
				if(is_string($meta)){
					$meta = preg_split('/[\s,]+/', $meta, null, PREG_SPLIT_NO_EMPTY);
				}

				$meta = implode("','", $meta);
				$where = "AND meta_key IN ('$meta')";
			}

			$meta = $this->get_results("SELECT meta_key, meta_value FROM {$type}_meta WHERE {$type}_id = %d $where", $object->ID);

			foreach($meta as $m){
				$object->meta[$m->meta_key] = $m->meta_value;
			}
		}

		return $object;
	}

	public function edit_object($object, $id, $data = null, $meta = null){
		$query = '';

		$changes = array();
		$formats = array();

		if(is_array($id)){
			$data = $id;
			$id = $data['ID'];
		}

		if(isset($this->object_formats[$object])){
			foreach($data as $key => $value){
				if(array_key_exists($key, $this->object_formats[$object])){
					$changes[$key] = $value;
					if(isset($this->object_formats[$object][$key])){
						$formats[] = $this->object_formats[$object][$key];
					}else{
						$formats[] = '%s';
					}
				}
			}
		}else{
			foreach($data as $key => $value){
				$changes[$key] = $value;
				$formats[] = '%s';
			}
		}

		$this->update(
			$object.'s',
			$changes,
			array('ID' => $id),
			$formats,
			array('%d')
		);

		if(is_array($meta)){
			foreach($meta as $key => $value){
				$this->update_object_meta($object, $id, $key, $value);
			}
		}
	}

	public function delete_object($object, $id){
		if(is_array($id)) $id = $id['ID'];

		$this->query("DELETE FROM {$object}s WHERE ID = %d", $id);

		if($this->table_exists($type.'_meta')){
			$this->query("DELETE FROM {$object}_meta WHERE {$object}_id = %d", $id);
		}

		if($this->table_exists($type.'_relationships')){
			$this->query("DELETE FROM {$object}_relationships WHERE {$object}_id = %d", $id);
		}

		if(method_exists($this, 'delete_object_extra')){
			$this->delete_object_extra($object, $id);
		}
	}

	public function get_meta_data($type, $oid, $key, $single = true){
		$table = $type.'_meta';
		if(!$this->table_exists($table)) return;

		if(is_object($oid)){
			if(is_array($oid->meta) && isset($oid->meta[$key])){
				return $oid->meta[$key];
			}
		}

		$data = $this->get_col("SELECT meta_value FROM $table WHERE {$type}_id = %d AND meta_key = %s", $oid, $key);

		if(!is_array($data)) return;

		foreach($data as &$entry){
			$entry = maybe_unserialize($entry);
		}

		if($single && $data){
			$values = array_values($data);
			return $values[0];
		}elseif($single){
			return null;
		}

		return $data;
	}

	public function add_meta_data($type, $oid, $key, $value){
		return $this->update_meta_data($type, $oid, $key, $value);
	}

	public function update_meta_data($type, $oid, $key, $value, $prev_value = null){
		$table = $type.'_meta';
		if(!$this->table_exists($table)) return;

		if(!is_null($prev_value)){
			return $this->query("UPDATE $table SET meta_value = %s WHERE {$type}_id = %d AND meta_key = %s AND meta_value = %s", maybe_serialize($value), $oid, $key, $prev_value);
		}if($this->get_var("SELECT ID FROM $table WHERE {$type}_id = %d AND meta_key = %s", $oid, $key)){
			return $this->query("UPDATE $table SET meta_value = %s WHERE {$type}_id = %d AND meta_key = %s", maybe_serialize($value), $oid, $key);
		}else{
			return $this->query("INSERT INTO {$type}_meta ({$type}_id, meta_key, meta_value) VALUES (%d, %s, %s)", $oid, $key, maybe_serialize($value));
		}
	}

	public function delete_meta_data($type, $oid, $key, $value = null){
		$table = $type.'_meta';
		if(!$this->table_exists($table)) return;

		$and = '';
		if(!is_null($value)) $and = "AND meta_value = %s";

		return $this->query("DELETE FROM $table WHERE {$type}_id = %d AND meta_key = %s $and", $oid, $key, $value);
	}

	public function get_object_field($type, $id, $field){
		if(is_object($id)){
			return $id->$field;
		}

		return $this->get_var("SELECT $field FROM {$type}s WHERE ID = %d", $id);
	}

	public function update_object_field($type, $id, $field, $value){
		$format = is_numeric($value) ? '%d' : '%s';

		return $this->update($type.'s', array($field => $value), array('ID' => $id), array($format), array('%d'));
	}

	private function object_relation_helper($object_id, $object_type, $target_id, $target_type, $relation = null){
		if(isset($this->object_relationships[$target_type])){
			$relationship = $this->object_relationships[$target_type];
			if(is_array($relationship) && in_array($object_type, $relationship)){
				$data = array(
					$target_type.'_id' => $target_id,
					'object_id' => $object_id,
					'object_type' => $object_type
				);
				$format = array('%d','%d','%s');
			}elseif(is_string($relationship) && $relationship == $object_type){
				$data = array(
					$target_type.'_id' => $target_id,
					$object_type.'_id' => $object_id
				);
				$format = array('%d','%d');
			}else{
				return false;
			}
		}else{
			return false;
		}

		if(!is_null($relation)){
			$data['relation'] = $relation;
			$format[] = '%s';
		}

		return array(
			'data' => $data,
			'format' => $format
		);
	}

	public function add_object_relation($object_id, $object_type, $target_id, $target_type, $relation = null){
		$table = $target_type.'_relationships';
		if(!$this->table_exists($table)) return;

		$query = $this->object_relation_helper($object_id, $object_type, $target_id, $target_type, $relation);

		return $this->replace(
			$table,
			$query['data'],
			$query['format']
		);
	}

	public function update_object_relation($object_id, $object_type, $target_id, $target_type, $relation){
		return $this->add_object_relation($object_id, $object_type, $target_id, $target_type, $relation);
		/*
		$table = $target_type.'_relationships';
		if(!$this->table_exists($table)) return;

		$query = $this->object_relation_helper($object_id, $object_type, $target_id, $target_type, $relation);

		return $this->update(
			$table,
			array(
				'relation' => $relation
			),
			$query['data'],
			array('%s'),
			$query['format']
		);
		*/
	}

	public function remove_object_relation($object_id, $object_type, $target_id, $target_type, $relation = null){
		$table = $target_type.'_relationships';
		if(!$this->table_exists($table)) return;

		$query = $this->object_relation_helper($object_id, $object_type, $target_id, $target_type, $relation);

		return $this->delete(
			$table,
			$query['data'],
			$query['format']
		);
	}

	public function get_object_relation($object_type, $target_id, $target_type, $relation = null, $search_frist_col = false, $fields = null, $where_extra = ''){
		$table = $object_type.'_relationships';
		if(!$this->table_exists($table)) return;

		if($search_frist_col){
			$join_type = $target_type;
			$where_type = $object_type;
		}else{
			$join_type = $object_type;
			$where_type = $target_type;
		}

		if(is_null($fields)){
			$fields = 'o.*';
		}

		$where = '';
		if($relation){
			$compare = '=';
			if(is_array($relation)){
				$compare = $relation[1];
				$relation = $relation[0];
			}
			$where = "AND r.relation $compare %s";
		}

		$where_id = implode(',', (array) $target_id);

		return $this->get_results("
			SELECT
				$fields
			FROM
				{$join_type}s AS o
				LEFT JOIN $table AS r ON (o.ID = r.{$join_type}_id)
			WHERE
				(r.{$where_type}_id IN ($where_id) $where)
				$where_extra
			GROUP BY
				r.{$join_type}_id
		", $relation);
	}

	/**
	 * ============
	 * Alias Method
	 * ============
	 */

	public function __call($name, $arguments){
		for($i = 0; $i < 10; $i++){
			if(!isset($arguments[$i]))
				$arguments[$i] = null;
		}

		//Meta Data Aliases
		if(preg_match('/^get_(\w+)_meta$/', $name, $matches)){
			list($id, $key) = $arguments;
			return $this->get_meta_data($matches[1], $id, $key);
		}
		if(preg_match('/^add_(\w+)_meta$/', $name, $matches)){
			list($id, $key, $value) = $arguments;
			return $this->add_meta_data($matches[1], $id, $key, $value);
		}
		if(preg_match('/^update_(\w+)_meta$/', $name, $matches)){
			list($id, $key, $value) = $arguments;
			return $this->update_meta_data($matches[1], $id, $key, $value);
		}
		if(preg_match('/^delete_(\w+)_meta$/', $name, $matches)){
			list($id, $key) = $arguments;
			return $this->delete_meta_data($matches[1], $id, $key);
		}

		//Object Relation Aliases
		if(preg_match('/^add_(\w+?)_to_(\w+)$/', $name, $matches)){
			list($object_id, $target_id, $relation) = $arguments;
			list($null, $object_type, $target_type) = $matches;
			return $this->add_object_relation($object_id, $object_type, $target_id, $target_type, $relation);
		}
		if(preg_match('/^update_(\w+?)_(?:in|of)_(\w+)$/', $name, $matches)){
			list($object_id, $target_id, $relation) = $arguments;
			list($null, $object_type, $target_type) = $matches;
			return $this->update_object_relation($object_id, $object_type, $target_id, $target_type, $relation);
		}
		if(preg_match('/^remove_(\w+?)_from_(\w+)$/', $name, $matches)){
			list($object_id, $target_id, $relation) = $arguments;
			list($null, $object_type, $target_type) = $matches;
			return $this->remove_object_relation($object_id, $object_type, $target_id, $target_type, $relation);
		}
		if(preg_match('/^get_(\w+?)s_from_(\w+)$/', $name, $matches)){
			list($target_id, $relation, $fields, $where_extra) = $arguments;
			list($null, $target_type, $table) = $matches;
			return $this->get_object_relation($table, $target_id, $target_type, $relation, true, $fields, $where_extra);
		}
		if(preg_match('/^get_(\w+?)s_for_(\w+)$/', $name, $matches)){
			list($target_id, $relation, $fields, $where_extra) = $arguments;
			list(, $table, $target_type) = $matches;
			return $this->get_object_relation($table, $target_id, $target_type, $relation, false, $fields, $where_extra);
		}

		//Object by Aliases
		if(preg_match('/^get_(\w+?)_by_(\w+)$/', $name, $matches)){
			list($id, $fields, $meta) = $arguments;
			list($null, $type, $field) = $matches;
			return $this->get_object_by($type, $field, $id, $fields, $meta);
		}
		if(preg_match('/^get_(\w+?)_by$/', $name, $matches)){
			list($field, $id, $fields, $meta) = $arguments;
			return $this->get_object_by($matches[1], $field, $id, $fields, $meta);
		}

		//Object Field Aliass
		if(preg_match('/^(?:get|is)_(\w+?)_(\w+)$/', $name, $matches)){
			list($null, $type, $field) = $matches;
			return $this->get_object_field($type, $arguments[0], $field);
		}
		if(preg_match('/^update_(\w+?)_(\w+)$/', $name, $matches)){
			list($id, $value) = $arguments;
			list($null, $type, $field) = $matches;
			return $this->update_object_field($type, $id, $field, $value);
		}

		//Object Aliases
		if(preg_match('/^get_(\w+)$/', $name, $matches)){
			list($id, $fields, $meta) = $arguments;
			return $this->get_object($matches[1], $id, $fields, $meta);
		}
		if(preg_match('/^edit_(\w+)$/', $name, $matches)){
			list($id, $changes) = $arguments;
			return $this->edit_object($matches[1], $id, $changes);
		}
		if(preg_match('/^delete_(\w+)$/', $name, $matches)){
			return $this->delete_object($matches[1], $arguments[0]);
		}

		return null;
	}
}

/**
 * =================
 * Utility Functions
 * =================
 */

function is_serialized($data){
	if(! is_string($data))
		return false;
	$data = trim($data);
	if('N;' == $data)
		return true;
	$length = strlen($data);
	if($length < 4)
		return false;
	if(':' !== $data[1])
		return false;
	$lastc = $data[$length-1];
	if(';' !== $lastc && '}' !== $lastc)
		return false;
	$token = $data[0];
	switch($token){
		case 's':
			if('"' !== $data[$length-2])
				return false;
		case 'a':
		case 'O':
			return(bool) preg_match("/^{$token}:[0-9]+:/s", $data);
		case 'b':
		case 'i':
		case 'd':
			return(bool) preg_match("/^{$token}:[0-9.E-]+;\$/", $data);
	}
	return false;
}

function maybe_unserialize($original){
	if(is_serialized($original))
		return @unserialize($original);
	return $original;
}

function maybe_serialize($data){
	if(is_array($data) || is_object($data))
		return serialize($data);

	return $data;
}

function hashword($pass, $salt){
	//SHA256 the salt and password
	$salt = hash('sha256', $salt);
	$pass = hash('sha256', $pass);

	//Get the decimal value of the first 2 characters of the salt hash
	$sR = hexdec(substr($salt, 0, 2));

	//Get the decimal value of the first 2 characters of the password hash
	$pR = hexdec(substr($pass, 0, 2));

	//Check if either value is greater than 32, if so, divide it by 8
	if($sR > 32) $sR = ceil($sR / 8);
	if($pR > 32) $pR = ceil($pR / 8);

	//"Revolve" the salt and password, based on the $pR and $sR, respectively
	$salt = substr($salt, $pR).substr($salt, 0, $pR);
	$pass = substr($pass, $sR).substr($pass, 0, $sR);

	//Split the password and salt into individual characters
	$pass = preg_split('//', $pass, null, PREG_SPLIT_NO_EMPTY);
	$salt = preg_split('//', $salt, null, PREG_SPLIT_NO_EMPTY);

	//Run through each character of the password and salt hashes, alternating between them to build the result
	$result = '';
	for($c = 0; $c < 64; $c++){
		//Switch up how the characters are appended, based on what position we're on
		if($c % 3 == 0){
			$result .= $salt[$c] . $pass[$c];
		}else{
			$result .= $pass[$c] . $salt[$c];
		}
	}

	return $result;
}
