<?php

namespace miniOrm;

use PDO;
use Exception;

class Db
{

    private $link;
    private static $mysql = array();
    public $lastQuery;

    private function __construct($inst)
    {

        if (!defined('_MO_DB_NAME_' . $inst)) {
            self::displayError('Please define your database name : _MO_DB_NAME_' . $inst);
        }
        if (!defined('_MO_DB_LOGIN_' . $inst)) {
            self::displayError('Please define your database login : _MO_DB_LOGIN_' . $inst);
        }
        if (!defined('_MO_DB_PASSWORD_' . $inst)) {
            self::displayError('Please define your database password : _MO_DB_PASSWORD_' . $inst);
        }
        if (!defined('_MO_DB_SERVER_' . $inst)) {
            self::displayError('Please define your database server : _MO_DB_SERVER_' . $inst);
        }

        try {
            $this->link = new PDO(
                'mysql:host=' . constant('_MO_DB_SERVER_' . $inst) . ';dbname=' . constant('_MO_DB_NAME_' . $inst),
                constant('_MO_DB_LOGIN_' . $inst),
                constant('_MO_DB_PASSWORD_' . $inst),
                array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
            );
        } catch (Exception $e) {
            self::displayError($e->getMessage());
        }
    }

    public static function displayError($error)
    {
        $traces = debug_backtrace();
        $debug = (defined('_MO_DEBUG_')) ? _MO_DEBUG_ : true;
        if ($debug) {
            $trace = '';
            foreach ($traces as $trace_line) {
                $trace .= '<small>' . $trace_line['file'] . ' (' . $trace_line['function'] . ' => ' . $trace_line['line'] . ')</small><br/>';
            }
            die('<fieldset><legend>miniOrm Error</legend>' . $error . '<br/>' . $trace . '</fieldset>');
        }
    }

    public function quote($value)
    {
        $isNotString = array('NOW()');
        if (in_array($value, $isNotString)) {
            return $value;
        } else {
            return $this->link->quote($value);
        }
    }

    private static function getQueryWhere($where)
    {
        $sql = '';
        if (is_array($where)) {
            foreach ($where as $key => $param) {
                if ($key == 0) {
                    $sql .= ' WHERE ' . $param;
                } else {
                    $sql .= ' AND ' . $param;
                }
            }
        } else {
            return ' WHERE ' . $where;
        }
        return $sql;
    }

    private function getQuerySelect($select, $from, $where = null, $groupby = null, $orderby = null, $limit = null)
    {
        if (is_array($select)) {
            $select = implode(', ', $select);
        }
        $sql = 'SELECT ' . $select . ' FROM ' . $from;
        if ($where) {
            $sql .= self::getQueryWhere($where);
        }
        if ($groupby) {
            $sql .= ' GROUP BY ' . $groupby;
        }
        if ($orderby) {
            $sql .= ' ORDER BY ' . $orderby;
        }
        if ($limit) {
            $sql .= ' LIMIT ' . $limit;
        }

        return $sql;
    }

    private function getQueryDelete($table, $where = null)
    {
        $sql = 'DELETE FROM `' . $table . '`';
        if ($where) {
            $sql .= self::getQueryWhere($where);
        }
        return $sql;
    }

    private function getQueryInsert($table, $values, $type = 'INSERT')
    {
        foreach ($values as $key => $value) {
            if ($value != '') {
                $array_key[] = '`' . $key . '`';
                $array_value[] = $this->quote($value);
            }
        }
        if (!empty($array_value) && !empty($array_key)) {
            return $type . ' INTO `' . $table . '` (' . implode(',', $array_key) . ') VALUES (' . implode(',',
                    $array_value) . ')';
        } else {
            return false;
        }
    }

    private function getQueryUpdate($table, $values, $where)
    {
        $array_value = array();
        foreach ((array)$values as $key => $value) {
            $array_value[] = $key . '=' . $this->link->quote($value);
        }
        return 'UPDATE `' . $table . '` SET ' . implode(', ', $array_value) . ' ' . self::getQueryWhere($where);
    }


    public function exec($q)
    {
        $this->lastQuery = $q;
        $res = $this->link->query($q);
        return $this->queryResult($res);
    }

    private function queryResult($res)
    {
        try {
            if (!$res) {
                $errorInfo = $this->link->errorInfo();
                throw new Exception('<strong>' . $errorInfo[2] . '</strong> : ' . $this->lastQuery);
            }
            return $res;
        } catch (Exception $e) {
            self::displayError($e->getMessage());
            return false;
        }
    }

    public function getArray($select, $from = null, $where = null, $groupby = null, $orderby = null, $limit = null)
    {
        $i = 0;
        $r = array();
        # If only one parameter : first parameter is the full query
        $q = is_null($from) ? $select : self::getQuerySelect($select, $from, $where, $groupby, $orderby, $limit);
        $res = self::exec($q);
        if (is_object($res)) {
            while ($l = $res->fetch(PDO::FETCH_ASSOC)) {
                foreach ($l as $clef => $valeur) {
                    $r[$i][$clef] = $valeur;
                }
                $i++;
            }
            return $r;
        } else {
            return false;
        }
    }

    public function getRow($select, $from, $where = null, $groupby = null, $orderby = null)
    {
        $r = self::getArray($select, $from, $where, $groupby, $orderby, '0,1');
        return is_array($r) && array_key_exists(0, $r) ? $r[0] : false;
    }

    public function getValue($select, $from, $where = null, $groupby = null, $orderby = null)
    {
        $r = self::getArray($select, $from, $where, $groupby, $orderby, '0,1');
        if (!is_array($r) || !array_key_exists(0, $r)) {
            return false;
        }
        $key = key($r[0]);
        return $r[0][$key];
    }

    public function getValueArray($select, $from, $where = null, $groupby = null, $orderby = null, $limit = null)
    {
        $valueArray = array();
        $r = self::getArray($select, $from, $where, $groupby, $orderby, $limit);
        if (!is_array($r) || !array_key_exists(0, $r)) {
            return false;
        }
        $key = key($r[0]);
        foreach ($r as $v) {
            $valueArray[] = $v[$key];
        }
        return $valueArray;
    }

    public function count($from, $where = null, $groupby = null)
    {
        $r = self::getArray('COUNT(*) as count', $from, $where, $groupby);
        return is_array($r) && array_key_exists(0, $r) ? $r[0]['count'] : 0;
    }

    public function insert($table, $values, $type = 'INSERT')
    {
        self::exec(self::getQueryInsert($table, $values, $type));
        return $this->link->lastInsertId();
    }

    public function delete($table, $where)
    {
        return self::exec(self::getQueryDelete($table, $where));
    }

    public function update($table, $values, $where)
    {
        return self::exec(self::getQueryUpdate($table, $values, $where));
    }

    public static function inst($inst = '')
    {
        if (!array_key_exists($inst, self::$mysql)) {
            self::$mysql[$inst] = new Db($inst);
        }
        return self::$mysql[$inst];
    }

}