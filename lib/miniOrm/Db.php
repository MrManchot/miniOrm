<?php

namespace miniOrm;
use PDO;
use Exception;

class Db {

	private $link;
	private static $mysql;
	public $lastQuery;

	private function __construct($inst) {

		if(!defined('_MO_DB_NAME_'.$inst))
			self::displayError('Please define your database name : _MO_DB_NAME_'.$inst);
		if(!defined('_MO_DB_LOGIN_'.$inst))
			self::displayError('Please define your database login : _MO_DB_LOGIN_'.$inst);
		if(!defined('_MO_DB_PASSWORD_'.$inst))
			self::displayError('Please define your database password : _MO_DB_PASSWORD_'.$inst);
		if(!defined('_MO_DB_SERVER_'.$inst))
			self::displayError('Please define your database server : _MO_DB_SERVER_'.$inst);

		try {
			$this->link = new PDO(
				'mysql:host=' . constant('_MO_DB_SERVER_'.$inst) . ';dbname=' . constant('_MO_DB_NAME_'.$inst),
				constant('_MO_DB_LOGIN_'.$inst),
				constant('_MO_DB_PASSWORD_'.$inst)
			);
		} catch(Exception $e) {
			self::displayError($e->getMessage());
		}
	}
	
	public static function displayError($error) {
		$trace = debug_backtrace();
		$debug = (defined('_MO_DEBUG_')) ? _MO_DEBUG_ : true;
		if($debug)
			die('<fieldset>
				<legend>miniOrm Error</legend>'.
				$error.'<br/><small>'.$trace[0]['file'].' ('.$trace[0]['line'].')</small>
			</fieldset>');
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
		$sql= 'DELETE FROM `' . $table.'`';
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
		if(!empty($array_value) && !empty($array_key))
			return 'INSERT INTO `' . $table . '` (' . implode(',', $array_key) . ') VALUES (' . implode(',', $array_value) . ')';
		else
			return false;
	}

	private function getQueryUpdate($table, $values, $where) {
		$array_value= array();
		foreach ((array)$values as $key => $value) {
			$array_value[]= $key . '=' . $this->link->quote($value);
		}
		return 'UPDATE `' . $table . '` SET ' . implode(', ', $array_value) . ' ' . self::getQueryWhere($where);
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
			return false;
		}
	}

	public function getArray($select, $from=NULL , $where= NULL, $groupby= NULL, $orderby= NULL, $limit= NULL) {
		$i= 0;
		$r= array();
		# If only one parameter : first parameter is the full query
		$q = is_null($from) ? $select : self::getQuerySelect($select, $from, $where, $groupby, $orderby, $limit);
		$res = self::exec($q);
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
		$key = key($r[0]);
		foreach ($r as $v) 
			$valueArray[]= $v[$key];
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
		return self::exec(self::getQueryDelete($table, $where));
	}

	public function update($table, $values, $where) {
		return self::exec(self::getQueryUpdate($table, $values, $where));
	}

	public static function inst($inst = '') {
		if (is_null(self::$mysql[$inst]))
			self::$mysql[$inst]= new Db($inst);
		return self::$mysql[$inst];
	}

}
