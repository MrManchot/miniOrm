<?php

class Db {

	private $link;
	private static $mysql;
	public $lastQuery;

	private function __construct($name= _MO_DB_NAME_, $login= _MO_DB_LOGIN_, $mdp= _MO_DB_MDP_, $serveur= _MO_DB_SERVER_) {
		try {
			$this->link= new PDO('mysql:host=' . $serveur . ';dbname=' . $name, $login, $mdp);
		} catch(Exception $e) {
			self::displayError($e->getMessage());
			die();
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