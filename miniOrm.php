<?php

/*
 * miniOrm
 * Version: 1.4.1
 * Copyright : Cédric Mouleyre / @MrManchot
 */

 
# Initialisation
include('miniOrm.config.php');

# Autoload
spl_autoload_register(function ($class) {
	$classFile = __DIR__._MO_CLASS_DIR_ . $class . '.php';
    if(file_exists($classFile)) include($classFile);
});
	

/*** Db ***/
class Db {

	private $link;
	private static $mysql;
	public $lastQuery;

	private function __construct($name= _MO_DB_NAME_, $login= _MO_DB_LOGIN_, $mdp= _MO_DB_MDP_, $serveur= _MO_DB_SERVER_) {
		try {
			$this->link= new PDO('mysql:host=' . $serveur . ';dbname=' . $name, $login, $mdp);
		} catch(Exception $e) {
			self::displayError($e->getMessage());
		}
	}
	
	public static function displayError($error) {
		if(_MO_DEBUG_) echo '<fieldset><legend>miniOrm Error</legend>'.$error.'</fieldset>';
	}

	private function quote($value) {
		$isNotString= array('NOW()');
		if (in_array($value, $isNotString)) {
			return $value;
		} else {
			return $this->link->quote($value);
		}
	}

	private static function getQueryWhere($where) {
		$sql= '';
		if (is_array($where)) {
			foreach ($where as $key => $param) {
				if ($key == 0) {
					$sql.= ' WHERE ' . $param;
				} else {
					$sql.= ' AND ' . $param;
				}
			}
		} else {
			return ' WHERE ' . $where;
		}
		return $sql;
	}

	private function getQuerySelect($select, $from, $where= NULL, $groupby= NULL, $orderby= NULL, $limit= NULL) {
		$sql= 'SELECT ' . $select . ' FROM ' . $from;
		if ($where)
			$sql.= self::getQueryWhere($where);
		if ($groupby)
			$sql.= ' GROUP BY ' . $groupby;
		if ($orderby)
			$sql.= ' ORDER BY ' . $orderby;
		if ($limit)
			$sql.= ' LIMIT ' . $limit;
		return $sql;
	}

	private function getQueryDelete($table, $where= NULL) {
		$sql= 'DELETE FROM ' . $table;
		if ($where)
			$sql.= self::getQueryWhere($where);
		return $sql;
	}

	private function getQueryInsert($table, $values) {
		foreach ($values as $key => $value) {
			if($value) {
				$array_key[]= '`' . $key . '`';
				$array_value[]= $this->quote($value);
			}
		}
		return 'INSERT INTO ' . $table . ' (' . implode(',', $array_key) . ') VALUES (' . implode(',', $array_value) . ')';
	}

	private function getQueryUpdate($table, $values, $where) {
		$array_value= array();
		foreach ($values as $key => $value) {
			$array_value[]= $key . '=' . $this->link->quote($value);
		}
		return 'UPDATE ' . $table . ' SET ' . implode(', ', $array_value) . ' ' . self::getQueryWhere($where);
	}

	public function query($q) {
		$this->lastQuery = $q;
		$res = $this->link->query($q);
		return $this->queryResult($res);
	}


	public function exec($q) {
		$this->lastQuery = $q;
		$res= $this->link->query($q);
		return $this->queryResult($res);
	}
	
		
	private function queryResult($res) {
		try {
			if(!$res) {
				$errorInfo = $this->link->errorInfo();
				throw new Exception('<strong>'.$errorInfo[2].'</strong> : '.$this->lastQuery);
			} 
			return $res;
		} catch(Exception $e) {
			self::displayError($e->getMessage());
		}
	}

	public function getArray($select, $from, $where= NULL, $groupby= NULL, $orderby= NULL, $limit= NULL) {
		$i= 0;
		$r= array();
		$res = self::query(self::getQuerySelect($select, $from, $where, $groupby, $orderby, $limit));
		if(is_object($res)) {
			while ($l= $res->fetch(PDO::FETCH_ASSOC)) {
				foreach ($l as $clef => $valeur)
					$r[$i][$clef]= $valeur;
				$i++;
			}
			return $r;
		} else {
			return false;
		}
	}

	public function getRow($select, $from, $where= NULL, $groupby= NULL, $orderby= NULL) {
		$r= self::getArray($select, $from, $where, $groupby, $orderby, '0,1');
		return $r ? $r[0] : false;
	}

	public function getValue($select, $from, $where= NULL, $groupby= NULL, $orderby= NULL) {
		$r= self::getArray($select, $from, $where, $groupby, $orderby, '0,1');
		return $r[0][$select];
	}

	public function getValueArray($select, $from, $where= NULL, $groupby= NULL, $orderby= NULL, $limit= NULL) {
		$valueArray= array();
		$r= self::getArray($select, $from, $where, $groupby, $orderby, $limit);
		foreach ($r as $v) {
			$valueArray[]= $v[$select];
		}
		return $valueArray;
	}

	public function count($from, $where= NULL, $groupby= NULL) {
		$r= self::getArray('COUNT(*) as count', $from, $where, $groupby);
		return $r[0]['count'];
	}

	public function insert($table, $values) {
		self::exec(self::getQueryInsert($table, $values));
		return $this->link->lastInsertId();
	}

	public function delete($table, $where) {
		self::exec(self::getQueryDelete($table, $where));
	}

	public function update($table, $values, $where) {
		self::exec(self::getQueryUpdate($table, $values, $where));
	}

	public static function inst() {
		if (is_null(self::$mysql))
			self::$mysql= new Db();
		return self::$mysql;
	}

}

/*** Obj ***/

class Obj {

	public $id;
	public $relations;
	protected $v= array();
	protected $vDescribe= array();
	protected $vmax= array();
	protected $table;
	protected $key;
	protected static $tableStatic = ''; // Use when extends Obj

	public function __construct($table='', $values = array()) {
		$this->table = $table ? _MO_DB_PREFIX_.$table : _MO_DB_PREFIX_.static::$tableStatic;
		$cacheFile= __DIR__.'/'._MO_CACHE_DIR_ . _MO_CACHE_FILE_;
		if (file_exists($cacheFile)) {
			$cacheContent= file_get_contents($cacheFile);
			$cache= unserialize($cacheContent);
		}
		if (_MO_FREEZE_) {
			$this->v= $cache[$table]->v;
			$this->vDescribe= $cache[$table]->vDescribe;
			$this->key= $cache[$table]->key;
		} else {
			$result_fields= Db::inst()->query('DESCRIBE ' . $this->table);
			while ($row_field= $result_fields->fetch()) {
				$this->v[$row_field['Field']]= '';
				preg_match('#\(+(.*)\)+#', $row_field['Type'], $result);
				$size = '';
				if (array_key_exists(1, $result)) {
					$size = (int)$result[1];
					$this->vDescribe[$row_field['Field']]['size']= $size;
				}
				
				$this->vDescribe[$row_field['Field']]['type'] = $size ? str_replace("(".$size.")", "", $row_field['Type']) : $row_field['Type'];

				if ($row_field['Key'] == 'PRI') {
					$this->key= $row_field['Field'];
					$this->vDescribe[$row_field['Field']]['primary'] = true;
				}
			}
			$cache[$table]= $this;
			if(is_writable(dirname($cacheFile))) {
				file_put_contents($cacheFile, serialize($cache));
				chmod($cacheFile, 0777);
			} else {
				Db::displayError('Can\'t write : '.$cacheFile);
			}

		}
		$this->hydrate($values);
		return $this->key ? true : false;
	}

	public function describe() {
		return $this->vDescribe;	
	}

	public static function create($table, $values) {
		$calledClass= get_called_class();
		$obj= new $calledClass($table, $values);
		$obj->insert();
		return $obj;
	}
	
	public static function find($findme, $table='') {
		if(!$table) $table = static::$tableStatic;
		$objects = array();
		$obj = new self($table);
		$objectsArray = Db::inst()->getArray('*', $obj->table, $findme);
		foreach($objectsArray as $objectArray) {
			$objects[] = self::load($objectArray[$obj->key], $table);
		}
		return $objects;
	}

	public static function load($findme, $table='') {
		if(!$table) $table = static::$tableStatic;
		$calledClass= get_called_class();
		$obj= new $calledClass($table);
		$params= is_numeric($findme) ? $obj->key . '=' . $findme : $findme;
		$obj->v= Db::inst()->getRow('*', $obj->table, $params);
		$obj->id= $obj->v[$obj->key];
		$obj->refreshRelation();
		return $obj;
	}

	public function refreshRelation() {
		if (!empty($this->relations)) {
			foreach ($this->relations as $relation) {
				$this->vmax[$relation['field']]= $relation['table']::load($this->__get($relation['field']));
			}
		}
	}

	public function insert() {
		$this->id= Db::inst()->insert($this->table, $this->v);
	}

	public function update() {
		Db::inst()->update($this->table, $this->v, $this->key . '=' . $this->id);
	}

	public function delete() {
		Db::inst()->delete($this->table, $this->key . '=' . $this->id);
	}

	public function save() {
		return $this->id ? $this->update() : $this->insert();
	}

	public function __set($key, $value) {
		$numericTypes= array('float', 'int', 'tinyint');
		$intTypes= array('int', 'tinyint');
		$dateTypes= array('date', 'datetime');
		$testMethod= 'set' . ucfirst($key);
		$calledClass= get_called_class();
		if (method_exists($calledClass, $testMethod))
			$value= $calledClass::$testMethod($value);
		try {
			if (strlen($value) > $this->vDescribe[$key]['size'] && $this->vDescribe[$key]['size']) {
				throw new Exception('"' . $key . '" value is too long (' . $this->vDescribe[$key]['size'] . ')');
			}
			if (in_array($this->vDescribe[$key]['type'], $numericTypes)) {
				if (!is_numeric($value)) {
					throw new Exception('"' . $key . '" value should be numeric');
				} elseif (!is_int($value) && in_array($this->vDescribe[$key]['type'], $intTypes)) {
					throw new Exception('"' . $key . '" value should be int');
				}
			}
			if (in_array($this->vDescribe[$key]['type'], $dateTypes) && mb_substr_count($value, "-") != 2) {
				throw new Exception('"' . $key . '" value should be date');
			}
		} catch(Exception $e) {
			Db::displayError($e->getMessage());
		}
		$this->v[$key]= $value;
	}

	public function __get($key) {
		$value= isset($this->vmax) && array_key_exists($key, $this->vmax) ? $this->vmax[$key] : $this->v[$key];
		$testMethod= 'get' . ucfirst($key);
		if (method_exists(get_called_class(), $testMethod))
			$value= self::$testMethod($value);
		return $value;
	}
	
	public function __isset($key) {
		return array_key_exists($key, $this->v);
	}

	public function hydrate($values) {
		foreach ($values as $key => $value) {
			$this->__set($key, $value);
		}
	}

}
