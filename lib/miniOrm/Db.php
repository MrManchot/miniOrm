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
        try {
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

            $charset = $this->resolveCharset($inst);
            $this->link = new PDO(
                'mysql:host=' . constant('_MO_DB_SERVER_' . $inst) . ';dbname=' . constant('_MO_DB_NAME_' . $inst) . ';charset=' . $charset,
                constant('_MO_DB_LOGIN_' . $inst),
                constant('_MO_DB_PASSWORD_' . $inst),
                array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . $charset,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                )
            );
        } catch (Exception $e) {
            self::displayError($e->getMessage());
        }
    }

    private function resolveCharset($inst)
    {
        $charsetConst = '_MO_DB_CHARSET_' . $inst;
        if (defined($charsetConst)) {
            $charset = constant($charsetConst);
        } elseif (defined('_MO_DB_CHARSET_')) {
            $charset = _MO_DB_CHARSET_;
        } else {
            $charset = 'utf8mb4';
        }
        $charset = preg_replace('/[^A-Za-z0-9_]/', '', (string) $charset);
        return $charset ? $charset : 'utf8mb4';
    }

    private static function quoteIdentifier($identifier)
    {
        $parts = explode('.', $identifier);
        foreach ($parts as $part) {
            if ($part === '' || preg_match('/[^A-Za-z0-9_]/', $part)) {
                self::displayError('Invalid identifier : ' . $identifier);
            }
        }
        return '`' . implode('`.`', $parts) . '`';
    }

    private function ensureWhereNotEmpty($where, $operation)
    {
        if ($where === null || $where === '' || (is_array($where) && count($where) === 0)) {
            self::displayError($operation . ' must have a WHERE clause');
        }
    }

    public static function displayError($error)
    {
        $debug = (defined('_MO_DEBUG_')) ? _MO_DEBUG_ : true;
        $message = 'miniOrm Error: ' . $error;
        if ($debug) {
            $traces = debug_backtrace();
            $trace = array();
            foreach ($traces as $trace_line) {
                $file = isset($trace_line['file']) ? $trace_line['file'] : 'unknown file';
                $function = isset($trace_line['function']) ? $trace_line['function'] : 'unknown function';
                $line = isset($trace_line['line']) ? $trace_line['line'] : '?';
                $trace[] = $file . ' (' . $function . ' => ' . $line . ')';
            }
            $message .= ' | Trace: ' . implode(' | ', $trace);
        }
        if ($debug) {
            throw new Exception($message);
        }
        throw new Exception('miniOrm Error: ' . $error);
    }

    public function quote($value)
    {
        $isNotString = array('NOW()');
        if ($value === null) {
            return 'NULL';
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
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

    private static function isAssocArray($array)
    {
        foreach (array_keys($array) as $key) {
            if (!is_int($key)) {
                return true;
            }
        }
        return false;
    }

    private function buildWhereClause($where, array &$params)
    {
        if (is_array($where) && self::isAssocArray($where)) {
            $conditions = array();
            foreach ($where as $key => $value) {
                $conditions[] = self::quoteIdentifier($key) . ' = ?';
                $params[] = $value;
            }
            return $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        } elseif ($where) {
            return self::getQueryWhere($where);
        }
        return '';
    }

    private function getQuerySelect($select, $from, $where = null, $groupby = null, $orderby = null, $limit = null)
    {
        if (is_array($select)) {
            $select = implode(', ', array_map(array(__CLASS__, 'quoteIdentifier'), $select));
        }
        $sql = 'SELECT ' . $select . ' FROM ' . self::quoteIdentifier($from);
        if ($where) {
            $trimmedWhere = ltrim($where);
            if (stripos($trimmedWhere, 'where ') === 0) {
                $sql .= ' ' . trim($trimmedWhere);
            } else {
                $sql .= self::getQueryWhere($where);
            }
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
        $sql = 'DELETE FROM ' . self::quoteIdentifier($table);
        return $sql;
    }

    private function getQueryInsert($table, $values, $type = 'INSERT')
    {
        $array_key = array();
        $array_value = array();
        $array_placeholder = array();
        foreach ($values as $key => $value) {
            $array_key[] = self::quoteIdentifier($key);
            $array_value[] = $value;
            $array_placeholder[] = '?';
        }
        if (!empty($array_value) && !empty($array_key)) {
            return array(
                $type . ' INTO ' . self::quoteIdentifier($table) . ' (' . implode(',', $array_key) . ') VALUES (' . implode(
                    ',',
                    $array_placeholder
                ) . ')',
                $array_value
            );
        } else {
            return array(false, array());
        }
    }

    private function getQueryUpdate($table, $values)
    {
        $array_value = array();
        $array_placeholder_values = array();
        foreach ((array) $values as $key => $value) {
            $array_value[] = self::quoteIdentifier($key) . ' = ?';
            $array_placeholder_values[] = $value;
        }
        return array('UPDATE ' . self::quoteIdentifier($table) . ' SET ' . implode(', ', $array_value), $array_placeholder_values);
    }


    public function exec($q, array $params = array())
    {
        $this->lastQuery = $q;
        try {
            if (!empty($params)) {
                $statement = $this->link->prepare($q);
                if (!$statement) {
                    return $this->queryResult($statement);
                }
                $statement->execute($params);
                return $statement;
            }
            $res = $this->link->query($q);
            return $this->queryResult($res);
        } catch (Exception $e) {
            self::displayError($e->getMessage());
            return false;
        }
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
        $params = array();
        # If only one parameter : first parameter is the full query
        if (is_null($from)) {
            $q = $select;
        } else {
            $whereClause = $this->buildWhereClause($where, $params);
            $q = $this->getQuerySelect($select, $from, $whereClause, $groupby, $orderby, $limit);
        }
        $res = $this->exec($q, $params);
        if (is_object($res)) {
            while ($l = $res->fetch()) {
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
        $r = $this->getArray($select, $from, $where, $groupby, $orderby, '0,1');
        return is_array($r) && array_key_exists(0, $r) ? $r[0] : false;
    }

    public function getValue($select, $from, $where = null, $groupby = null, $orderby = null)
    {
        $r = $this->getArray($select, $from, $where, $groupby, $orderby, '0,1');
        if (!is_array($r) || !array_key_exists(0, $r)) {
            return false;
        }
        $key = key($r[0]);
        return $r[0][$key];
    }

    public function getValueArray($select, $from, $where = null, $groupby = null, $orderby = null, $limit = null)
    {
        $valueArray = array();
        $r = $this->getArray($select, $from, $where, $groupby, $orderby, $limit);
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
        $r = $this->getArray('COUNT(*) as count', $from, $where, $groupby);
        return is_array($r) && array_key_exists(0, $r) ? $r[0]['count'] : 0;
    }

    public function insert($table, $values, $type = 'INSERT')
    {
        list($query, $params) = $this->getQueryInsert($table, $values, $type);
        if (!$query) {
            self::displayError('Insert query is empty for table : ' . $table);
        }
        $this->exec($query, $params);
        return $this->link->lastInsertId();
    }

    public function delete($table, $where)
    {
        $params = array();
        $this->ensureWhereNotEmpty($where, 'Delete');
        $whereClause = $this->buildWhereClause($where, $params);
        return $this->exec($this->getQueryDelete($table) . $whereClause, $params);
    }

    public function update($table, $values, $where)
    {
        $params = array();
        $this->ensureWhereNotEmpty($where, 'Update');
        $whereClause = $this->buildWhereClause($where, $params);
        list($queryUpdate, $updateParams) = $this->getQueryUpdate($table, $values);
        $query = $queryUpdate . $whereClause;
        return $this->exec($query, array_merge($updateParams, $params));
    }

    public static function inst($inst = '')
    {
        if (!array_key_exists($inst, self::$mysql)) {
            self::$mysql[$inst] = new Db($inst);
        }
        return self::$mysql[$inst];
    }
}
