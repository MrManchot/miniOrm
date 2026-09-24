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
            self::displayDatabaseError($e->getMessage());
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

    public static function quoteIdentifier($identifier)
    {
        if (!is_string($identifier) || $identifier === '') {
            self::displayError('Invalid SQL identifier.');
        }
        $parts = explode('.', $identifier);
        foreach ($parts as $part) {
            if ($part === '' || preg_match('/[^A-Za-z0-9_]/', $part)) {
                self::displayError('Invalid SQL identifier.');
            }
        }
        return '`' . implode('`.`', $parts) . '`';
    }

    private function ensureWhereNotEmpty($where, $operation)
    {
        if (is_array($where)) {
            $empty = count($where) === 0;
        } else {
            $empty = !is_string($where) || trim($where) === '';
        }
        if ($empty) {
            self::displayError($operation . ' must have a WHERE clause');
        }
    }

    public static function displayError($error)
    {
        $debug = (defined('_MO_DEBUG_')) ? _MO_DEBUG_ : false;
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

    private static function displayDatabaseError($error)
    {
        if (defined('_MO_DEBUG_') && _MO_DEBUG_) {
            self::displayError($error);
        }
        self::displayError('Database operation failed. Enable _MO_DEBUG_ for details.');
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
        if (in_array($value, $isNotString, true)) {
            return $value;
        } else {
            if (!is_scalar($value)) {
                self::displayError('Only scalar values can be quoted.');
            }
            return $this->link->quote($value);
        }
    }

    private static function getQueryWhere($where)
    {
        $sql = '';
        if (is_array($where)) {
            $first = true;
            foreach ($where as $param) {
                $param = trim($param);
                if ($first) {
                    $sql .= preg_match('/^where\b/i', $param) ? ' ' . $param : ' WHERE ' . $param;
                    $first = false;
                } else {
                    $sql .= ' AND ' . $param;
                }
            }
        } else {
            $where = trim($where);
            return preg_match('/^where\b/i', $where) ? ' ' . $where : ' WHERE ' . $where;
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
                if (!is_string($key) || !preg_match('/^([A-Za-z0-9_]+(?:\\.[A-Za-z0-9_]+)*)(?:\\s*(=|!=|<>|<=|>=|<|>|LIKE|NOT LIKE|IN|NOT IN))?$/i', trim($key), $matches)) {
                    self::displayError('Invalid WHERE field or operator.');
                }
                $identifier = self::quoteIdentifier($matches[1]);
                $operator = isset($matches[2]) && $matches[2] !== '' ? strtoupper($matches[2]) : '=';
                if ($value === null) {
                    if ($operator === '=') {
                        $conditions[] = $identifier . ' IS NULL';
                    } elseif ($operator === '!=' || $operator === '<>') {
                        $conditions[] = $identifier . ' IS NOT NULL';
                    } else {
                        self::displayError('NULL only supports equality checks in WHERE criteria.');
                    }
                } elseif (is_array($value)) {
                    if ($operator !== '=' && $operator !== 'IN' && $operator !== 'NOT IN') {
                        self::displayError('Array values require IN or NOT IN criteria.');
                    }
                    if (!$value) {
                        $conditions[] = $operator === 'NOT IN' ? '1=1' : '1=0';
                    } else {
                        $conditions[] = $identifier . ($operator === 'NOT IN' ? ' NOT IN (' : ' IN (') . implode(', ', array_fill(0, count($value), '?')) . ')';
                        foreach ($value as $item) {
                            $params[] = $item;
                        }
                    }
                } else {
                    if ($operator === 'IN' || $operator === 'NOT IN') {
                        self::displayError('IN criteria require an array of values.');
                    }
                    $conditions[] = $identifier . ' ' . $operator . ' ?';
                    $params[] = $value;
                }
            }
            return $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        } elseif (is_string($where) && trim($where) !== '') {
            return self::getQueryWhere($where);
        } elseif (is_array($where) && $where) {
            foreach ($where as $condition) {
                if (!is_string($condition) || trim($condition) === '') {
                    self::displayError('WHERE expressions must be non-empty strings.');
                }
            }
            return self::getQueryWhere($where);
        }
        return '';
    }

    private function getQuerySelect($select, $from, $where = null, $groupby = null, $orderby = null, $limit = null)
    {
        if (is_array($select)) {
            if (!$select) {
                self::displayError('SELECT fields must not be empty.');
            }
            $select = implode(', ', array_map(array(__CLASS__, 'quoteIdentifier'), $select));
        } elseif (!is_string($select) || trim($select) === '') {
            self::displayError('Invalid SELECT clause.');
        }
        $sql = 'SELECT ' . $select . ' FROM ' . self::quoteIdentifier($from);
        if ($where !== null && $where !== '') {
            $trimmedWhere = ltrim($where);
            if (stripos($trimmedWhere, 'where ') === 0) {
                $sql .= ' ' . trim($trimmedWhere);
            } else {
                $sql .= self::getQueryWhere($where);
            }
        }
        if ($groupby) {
            $groupby = is_array($groupby) ? implode(', ', array_map(array(__CLASS__, 'quoteIdentifier'), $groupby)) : $groupby;
            $sql .= ' GROUP BY ' . $groupby;
        }
        if ($orderby) {
            if (is_array($orderby)) {
                $orders = array();
                foreach ($orderby as $order) {
                    if (!is_string($order) || !preg_match('/^([A-Za-z0-9_]+(?:\\.[A-Za-z0-9_]+)*)(?:\\s+(ASC|DESC))?$/i', trim($order), $orderParts)) {
                        self::displayError('Invalid ORDER BY field.');
                    }
                    $orders[] = self::quoteIdentifier($orderParts[1]) . (isset($orderParts[2]) ? ' ' . strtoupper($orderParts[2]) : '');
                }
                $orderby = implode(', ', $orders);
            }
            $sql .= ' ORDER BY ' . $orderby;
        }
        if ($limit !== null && $limit !== '') {
            if (!preg_match('/^\s*\d+(\s*,\s*\d+)?\s*$/', (string) $limit)) {
                self::displayError('Invalid LIMIT clause.');
            }
            $sql .= ' LIMIT ' . $limit;
        }

        return $sql;
    }

    private function getQueryDelete($table)
    {
        $sql = 'DELETE FROM ' . self::quoteIdentifier($table);
        return $sql;
    }

    private function getQueryInsert($table, $values, $type = 'INSERT')
    {
        if (!is_array($values)) {
            self::displayError('Insert values must be an array.');
        }
        if (!is_string($type)) {
            self::displayError('Invalid insert type.');
        }
        $type = strtoupper(trim($type));
        if (!in_array($type, array('INSERT', 'INSERT IGNORE', 'REPLACE'), true)) {
            self::displayError('Invalid insert type.');
        }
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
            return array($type . ' INTO ' . self::quoteIdentifier($table) . ' () VALUES ()', array());
        }
    }

    private function getQueryUpdate($table, $values)
    {
        if (!is_array($values) || !$values) {
            self::displayError('Update values must not be empty.');
        }
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
        if (!is_string($q) || trim($q) === '') {
            self::displayError('SQL query must be a non-empty string.');
        }
        foreach ($params as $value) {
            if ($value !== null && !is_scalar($value) && !is_resource($value)) {
                self::displayError('SQL parameters must be scalar values or streams.');
            }
        }
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
            self::displayDatabaseError($e->getMessage());
            return false;
        }
    }

    private function queryResult($res)
    {
        try {
            if (!$res) {
                $errorInfo = $this->link->errorInfo();
                $error = isset($errorInfo[2]) && $errorInfo[2] ? $errorInfo[2] : 'Database query failed.';
                throw new Exception($error);
            }
            return $res;
        } catch (Exception $e) {
            self::displayDatabaseError($e->getMessage());
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
                $r[$i++] = $l;
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
            return array();
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
