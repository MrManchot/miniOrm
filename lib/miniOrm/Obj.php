<?php

namespace miniOrm;
use Exception;

class Obj {

	public $id;
	public $relations;
	public $cache_dir;
	public $freeze;
	protected $v= array();
	protected $vDescribe= array();
	protected $vmax= array();
	protected $table;
	protected $key;
	protected static $tableStatic = '';

	public function __construct($table='', $values = array()) {

		# Freeze option permit to add to cache your database configuration.
		# Once activated, you can't access to new table dynamically : just active it in production.
		$this->freeze = (defined('_MO_FREEZE_')) ? _MO_FREEZE_ : false;

		$this->cache_dir = (defined('_MO_CACHE_DIR_')) ? _MO_CACHE_DIR_ : __DIR__.'/../../cache/';

		$this->table = $table ? $table : static::$tableStatic;
		if(!$this->table) {
			$this->table = false;
			return false;
		}
		
		$cacheFile= $this->cache_dir . 'miniOrm.tmp';
		if (file_exists($cacheFile)) {
			$cacheContent= file_get_contents($cacheFile);
			$cache= unserialize($cacheContent);
		}
		if (isset($cache) && $this->freeze) {
			$this->v= $cache[$table]->v;
			$this->vDescribe= $cache[$table]->vDescribe;
			$this->key= $cache[$table]->key;
		} else {
			$result_fields= Db::inst()->exec('DESCRIBE `' . $this->table . '`');
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
				if(!is_null($row_field['Default'])) $this->vDescribe[$row_field['Field']]['default'] = $row_field['Default'];

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
		if(empty($obj->v)) Db::displayError('Not found : '.$table.' : '.$findme);
		$obj->id= $obj->v[$obj->key];
		return $obj;
	}

	public function getRelations() {
		if (!empty($this->relations)) {
			foreach ($this->relations as $relation) {
				if($id = $this->__get($relation['field']))
					$this->vmax[$relation['obj']]= self::load($id, $relation['obj']);
			}
		}
	}

	public function insert() {
		$this->id= Db::inst()->insert($this->table, $this->v);
		$identifier = $this->key;
		$this->$identifier = $this->id;
		return $this->id;
	}

	public function update() {
		return Db::inst()->update($this->table, $this->v, $this->key . '=' . $this->id);
	}

	public function delete() {
		Db::inst()->delete($this->table, $this->key . '=' . $this->id);
	}

	public function save() {
		return $this->id ? $this->update() : $this->insert();
	}

	public function __set($key, $value) {
		$numericTypes= array('float', 'int', 'tinyint');
		$testMethod= 'set_' . $key;
		$calledClass= get_called_class();
		if (method_exists($calledClass, $testMethod))
			$value= $calledClass::$testMethod($value);
		try {
			if(array_key_exists($key, $this->vDescribe) && $value) {
				if(array_key_exists('size', $this->vDescribe[$key])) {
					if (strlen($value) > $this->vDescribe[$key]['size'] && $this->vDescribe[$key]['size']) {
						throw new Exception('"' . $key . '" value is too long (' . $this->vDescribe[$key]['size'] . ') : '.$value);
					}
				}
				if (in_array($this->vDescribe[$key]['type'], $numericTypes)) {
					if (!is_numeric($value)) {
						throw new Exception('"' . $key . '" value should be numeric : '.$value);
					} 
				}
			}
		} catch(Exception $e) {
			Db::displayError($e->getMessage());
		}
		if(is_array($this->v) && array_key_exists($key, $this->v)) {
			$this->v[$key]= $value;
		} else {
			$this->vmax[$key]= $value;
		}
	}

	public function __get($key) {
		$testMethod= 'get_' . $key;
		$calledClass= get_called_class();
		if (method_exists($calledClass, $testMethod)) {
			return $calledClass::$testMethod();
		} elseif(isset($this->vmax) && array_key_exists($key, $this->vmax)) {
			return $this->vmax[$key];
		} elseif(isset($this->v) && array_key_exists($key, $this->v)) {
			return $this->v[$key];
		} else {
			return "";
		}
	}
	
	public function __isset($key) {
		return array_key_exists($key, $this->v);
	}

	public function hydrate($values) {
		foreach ($values as $key => $value) {
			if(array_key_exists($key, $this->vDescribe))
				$this->__set($key, $value);
		}
		foreach ($this->vDescribe as $fieldKey => $field) {
			if(array_key_exists('default', $field) && !$this->__get($fieldKey)) {
				if($field['default']=='CURRENT_TIMESTAMP') {
					$this->__set($fieldKey, date('Y-m-d H:i:s'));
				} else {
					$this->__set($fieldKey, $field['default']);
				}
			}
		}
	}

}
