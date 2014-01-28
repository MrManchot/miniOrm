<?php

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
		$cacheFile= _MO_DIR_._MO_CACHE_DIR_ . _MO_CACHE_FILE_;
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
				if (array_key_exists(1, $result)) {
					$param = $result[1];		
					$this->vDescribe[$row_field['Field']]['type'] = str_replace("(".$param.")", "", $row_field['Type']);
					if($this->vDescribe[$row_field['Field']]['type']=='enum') {
						$list = explode(',', $param);
						foreach($list as &$listElem) {
							$listElem = substr($listElem, 1 , -1);
						}
						$this->vDescribe[$row_field['Field']]['list']= $list;
					} else {
						$this->vDescribe[$row_field['Field']]['size']= $param;
					}
				} else {
					$this->vDescribe[$row_field['Field']]['type'] = $row_field['Type'];
				}

				if($row_field['Extra']) $this->vDescribe[$row_field['Field']]['extra'] = $row_field['Extra'];
				if($row_field['Default']) $this->vDescribe[$row_field['Field']]['default'] = $row_field['Default'];

				if ($row_field['Key'] == 'PRI') {
					$this->key= $row_field['Field'];
					$this->vDescribe[$row_field['Field']]['primary'] = true;
				}
			}
			$cache[$table]= $this;
			if(is_writable(dirname($cacheFile))) {
				file_put_contents($cacheFile, serialize($cache));
				@chmod($cacheFile, 0777);
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
				$alias = isset($relation['alias']) ? $relation['alias'] : $relation['field'];
				$this->vmax[$alias]= $relation['obj']::load($this->__get($relation['field']));
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
		$testMethod= 'set_' . $key;
		$calledClass= get_called_class();
		if (method_exists($calledClass, $testMethod))
			$value= $calledClass::$testMethod($value);
		try {
			if(array_key_exists('size', $this->vDescribe[$key])) {
				if (strlen($value) > $this->vDescribe[$key]['size'] && $this->vDescribe[$key]['size']) {
					throw new Exception('"' . $key . '" value is too long (' . $this->vDescribe[$key]['size'] . ')');
				}
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
		if(array_key_exists($key, $this->v)) {
			$this->v[$key]= $value;
		} else {
			$this->vmax[$key]= $value;
		}
	}

	public function __get($key) {
		$value= isset($this->vmax) && array_key_exists($key, $this->vmax) ? $this->vmax[$key] : $this->v[$key];
		$testMethod= 'get_' . $key;
		$calledClass= get_called_class();
		if (method_exists($calledClass, $testMethod))
			$value= $calledClass::$testMethod($value);
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
