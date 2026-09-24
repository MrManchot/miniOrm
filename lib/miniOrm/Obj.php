<?php

namespace miniOrm;

class Obj
{

    public $id;
    public $relations;
    public $cache_dir;
    public $freeze;
    protected $v = array();
    protected $vDescribe = array();
    protected $vmax = array();
    private $provided = array();
    private $applyingDefaults = false;
    private static $schemaCache = array();
    protected $table;
    protected $key;
    protected static $tableStatic = '';
    protected static $dbStatic = '';

    public function __construct($table = '', $values = array())
    {

        $this->freeze = (defined('_MO_FREEZE_')) ? (bool) _MO_FREEZE_ : false;
        $defaultCacheDir = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'miniorm-' . substr(hash('sha256', __DIR__), 0, 16);
        $cacheDir = (defined('_MO_CACHE_DIR_') && _MO_CACHE_DIR_ !== '') ? _MO_CACHE_DIR_ : $defaultCacheDir;
        $this->cache_dir = rtrim($cacheDir, '/\\') . DIRECTORY_SEPARATOR;

        $this->table = $table ? $table : static::$tableStatic;
        if (!$this->table) {
            $this->table = false;
            return;
        }
        Db::quoteIdentifier($this->table);

        $cacheFile = $this->freeze ? $this->getCacheFilePath() : null;
        $runtimeCacheKey = get_class($this) . '|' . static::$dbStatic . '|' . $this->table;
        $cacheHit = isset(self::$schemaCache[$runtimeCacheKey]);
        $cache = $cacheHit ? self::$schemaCache[$runtimeCacheKey] : ($this->freeze ? $this->readCache($cacheFile) : null);
        $cacheHit = $cacheHit || $this->isValidSchemaCache($cache);

        if ($this->isValidSchemaCache($cache)) {
            $this->v = $cache['v'];
            $this->vDescribe = $cache['vDescribe'];
            $this->key = $cache['key'];
        } else {
            $result_fields = Db::inst(static::$dbStatic)->exec('DESCRIBE ' . Db::quoteIdentifier($this->table));
            while ($row_field = $result_fields->fetch()) {
                $fieldName = $row_field['Field'];
                $fieldType = $row_field['Type'];
                $normalizedFieldType = strtolower($fieldType);
                $this->v[$fieldName] = '';

                if (preg_match('/^([a-z]+)(?:\((.*)\))?/i', $fieldType, $typeParts)) {
                    $this->vDescribe[$fieldName]['type'] = strtolower($typeParts[1]);
                    if (isset($typeParts[2]) && $typeParts[2] !== '') {
                        if ($this->vDescribe[$fieldName]['type'] === 'enum' || $this->vDescribe[$fieldName]['type'] === 'set') {
                            preg_match_all("/'((?:\\\\.|[^'\\\\])*)'/", $typeParts[2], $enumValues);
                            $this->vDescribe[$fieldName]['list'] = isset($enumValues[1]) ? array_map('stripslashes', $enumValues[1]) : array();
                        } else {
                            $this->vDescribe[$fieldName]['size'] = $typeParts[2];
                        }
                    }
                    if (strpos($normalizedFieldType, ' unsigned') !== false) {
                        $this->vDescribe[$fieldName]['unsigned'] = true;
                    }
                } else {
                    $this->vDescribe[$fieldName]['type'] = $normalizedFieldType;
                }

                if (!empty($row_field['Extra'])) {
                    $this->vDescribe[$fieldName]['extra'] = $row_field['Extra'];
                }
                if (!is_null($row_field['Default'])) {
                    $this->vDescribe[$fieldName]['default'] = $row_field['Default'];
                }

                if ($row_field['Key'] == 'PRI') {
                    $this->key = $fieldName;
                    $this->vDescribe[$fieldName]['primary'] = true;
                }
            }
        }
        if (!$this->key) {
            Db::displayError('Table must have a primary key: ' . $this->table);
        }
        self::$schemaCache[$runtimeCacheKey] = array(
            'v' => $this->v,
            'vDescribe' => $this->vDescribe,
            'key' => $this->key
        );
        if ($this->freeze && !$cacheHit) {
            $this->writeCache($cacheFile);
        }
        $this->hydrate($values);
    }

    public function describe()
    {
        return $this->vDescribe;
    }

    public static function create($table, $values)
    {
        $calledClass = get_called_class();
        $obj = new $calledClass($table, $values);
        $obj->insert();
        return $obj;
    }

    public static function find($findme, $table = '')
    {
        if (!$table) {
            $table = static::$tableStatic;
        }
        $objects = array();
        $calledClass = get_called_class();
        $obj = new $calledClass($table);
        if (!$obj->table || !$obj->key) {
            Db::displayError('A table with a primary key is required.');
        }
        $criteria = is_numeric($findme) ? array($obj->key => $findme) : $findme;
        $objectsArray = Db::inst(static::$dbStatic)->getArray('*', $obj->table, $criteria);
        if (!is_array($objectsArray)) {
            return $objects;
        }
        foreach ($objectsArray as $objectArray) {
            $result = new $calledClass($table);
            $result->v = $objectArray;
            $result->id = isset($objectArray[$obj->key]) ? $objectArray[$obj->key] : null;
            $objects[] = $result;
        }
        return $objects;
    }

    public static function load($findme, $table = '')
    {
        if (!$table) {
            $table = static::$tableStatic;
        }
        $calledClass = get_called_class();
        $obj = new $calledClass($table);
        if (!$obj->table || !$obj->key) {
            Db::displayError('A table with a primary key is required.');
        }
        $params = is_array($findme) ? $findme : array($obj->key => $findme);
        $obj->v = Db::inst(static::$dbStatic)->getRow('*', $obj->table, $params);
        if (empty($obj->v)) {
            Db::displayError('Object not found in table: ' . $table);
        }
        $obj->id = $obj->v[$obj->key];
        return $obj;
    }

    public function getRelations()
    {
        if (!empty($this->relations)) {
            foreach ($this->relations as $relation) {
                $id = $this->__get($relation['field']);
                if ($id !== '' && $id !== null) {
                    $this->vmax[$relation['obj']] = static::load($id, $relation['obj']);
                }
            }
        }
    }

    public function insert()
    {
        if (!$this->table || !$this->key) {
            Db::displayError('Cannot insert an object without a table and primary key.');
        }
        $values = array();
        foreach ($this->v as $field => $value) {
            if (isset($this->provided[$field])) {
                $values[$field] = $value;
            }
        }
        $autoIncrement = isset($this->vDescribe[$this->key]['extra']) && stripos($this->vDescribe[$this->key]['extra'], 'auto_increment') !== false;
        if (!$autoIncrement && (!isset($values[$this->key]) || $values[$this->key] === '' || $values[$this->key] === null)) {
            Db::displayError('A value is required for the primary key: ' . $this->key);
        }
        if ($autoIncrement && (!isset($values[$this->key]) || $values[$this->key] === '' || $values[$this->key] === null)) {
            unset($values[$this->key]);
        }
        $insertId = Db::inst(static::$dbStatic)->insert($this->table, $values);
        $this->id = $autoIncrement ? $insertId : (isset($values[$this->key]) ? $values[$this->key] : $insertId);
        if ($this->key && array_key_exists($this->key, $this->v)) {
            $this->v[$this->key] = $this->id;
            $this->provided[$this->key] = true;
        }
        return $this->id;
    }

    public function update()
    {
        $id = $this->id !== null && $this->id !== '' ? $this->id : (isset($this->v[$this->key]) ? $this->v[$this->key] : null);
        if ($id === null || $id === '') {
            Db::displayError('Cannot update an object without a primary key.');
        }
        return Db::inst(static::$dbStatic)->update($this->table, $this->v, array($this->key => $id));
    }

    public function delete()
    {
        $id = $this->id !== null && $this->id !== '' ? $this->id : (isset($this->v[$this->key]) ? $this->v[$this->key] : null);
        if ($id === null || $id === '') {
            Db::displayError('Cannot delete an object without a primary key.');
        }
        return Db::inst(static::$dbStatic)->delete($this->table, array($this->key => $id));
    }

    public function save()
    {
        return ($this->id !== null && $this->id !== '') ? $this->update() : $this->insert();
    }

    public function __set($key, $value)
    {
        $testMethod = 'set_' . $key;
        if (is_callable(array($this, $testMethod))) {
            $value = $this->$testMethod($value);
        }
        if (is_array($this->v) && array_key_exists($key, $this->v)) {
            if (is_array($value)) {
                $value = serialize($value);
            } elseif ($value instanceof \DateTime && in_array($this->vDescribe[$key]['type'], array('date', 'datetime', 'timestamp'), true)) {
                $format = ($this->vDescribe[$key]['type'] === 'date') ? 'Y-m-d' : 'Y-m-d H:i:s';
                $value = $value->format($format);
            } elseif ($value !== null && !is_scalar($value)) {
                Db::displayError('Unsupported value type for field: ' . $key);
            }
            $this->validateFieldValue($key, $value);
            $this->v[$key] = $value;
            if (!$this->applyingDefaults) {
                $this->provided[$key] = true;
            }
        } else {
            $this->vmax[$key] = $value;
        }
    }

    public function __get($key)
    {
        $testMethod = 'get_' . $key;
        if (is_callable(array($this, $testMethod))) {
            return $this->$testMethod();
        } elseif (isset($this->vmax) && array_key_exists($key, $this->vmax)) {
            return $this->vmax[$key];
        } elseif (isset($this->v) && array_key_exists($key, $this->v)) {
            return $this->v[$key];
        } elseif ($key == 'all') {
            return array_merge($this->v, $this->vmax);
        } else {
            return "";
        }
    }

    public function __isset($key)
    {
        return isset($this->v[$key]) || isset($this->vmax[$key]);
    }

    public function hydrate($values)
    {
        if (!is_array($values)) {
            Db::displayError('Hydration values must be an array.');
        }
        foreach ($values as $key => $value) {
            if (array_key_exists($key, $this->vDescribe)) {
                $this->__set($key, $value);
            }
        }
        foreach ($this->vDescribe as $fieldKey => $field) {
            if (array_key_exists('default', $field) && $this->__get($fieldKey) === '') {
                $this->applyingDefaults = true;
                $defaultValue = $field['default'];
                if (is_string($defaultValue) && preg_match('/^CURRENT_TIMESTAMP(?:\\(\\))?$/i', trim($defaultValue))) {
                    $defaultValue = date('Y-m-d H:i:s');
                }
                $this->__set($fieldKey, $defaultValue);
                $this->applyingDefaults = false;
            }
        }
    }

    private function validateFieldValue($key, $value)
    {
        if ($value === null || $value === '') {
            return;
        }

        $description = $this->vDescribe[$key];
        $type = $description['type'];
        $integerTypes = array('tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint', 'year', 'bit');
        $numericTypes = array_merge($integerTypes, array('decimal', 'numeric', 'float', 'double', 'real'));

        if (in_array($type, $numericTypes, true)) {
            if (!is_numeric($value)) {
                Db::displayError('Field must be numeric: ' . $key);
            }
            if (in_array($type, $integerTypes, true) && !preg_match('/^-?\\d+$/', (string) $value)) {
                Db::displayError('Field must contain an integer: ' . $key);
            }
            if (!empty($description['unsigned']) && (float) $value < 0) {
                Db::displayError('Field must be unsigned: ' . $key);
            }
            return;
        }

        if (isset($description['size']) && $description['size'] !== '') {
            $limit = (int) $description['size'];
            if ($limit > 0 && $this->stringLength((string) $value) > $limit) {
                Db::displayError('Field value is too long: ' . $key . ' (maximum ' . $limit . ').');
            }
        }
    }

    private function stringLength($value)
    {
        if (function_exists('mb_strlen')) {
            $length = @mb_strlen($value, 'UTF-8');
            if ($length !== false) {
                return $length;
            }
        }
        $length = @preg_match_all('/./us', $value, $matches);
        return $length === false ? strlen($value) : $length;
    }

    private function getCacheFilePath()
    {
        $cache_key = array(
            $this->getDbConfigValue('SERVER'),
            $this->getDbConfigValue('NAME'),
            $this->table
        );
        return $this->cache_dir . hash('sha256', serialize($cache_key)) . '.json';
    }

    private function getDbConfigValue($key)
    {
        $const = '_MO_DB_' . $key . '_' . static::$dbStatic;
        if (!defined($const)) {
            Db::displayError('Please define your database ' . strtolower($key) . ' : ' . $const);
        }
        return constant($const);
    }

    private function ensureCacheDir()
    {
        if (!is_dir($this->cache_dir)) {
            if (!@mkdir($this->cache_dir, 0700, true) && !is_dir($this->cache_dir)) {
                return false;
            }
        }
        return is_writable($this->cache_dir);
    }

    private function readCache($cacheFile)
    {
        if (!is_file($cacheFile) || is_link($cacheFile)) {
            return null;
        }
        $cacheContent = @file_get_contents($cacheFile);
        if ($cacheContent === false) {
            return null;
        }
        $cache = json_decode($cacheContent, true);
        return is_array($cache) ? $cache : null;
    }

    private function isValidSchemaCache($cache)
    {
        if (!is_array($cache)
            || !isset($cache['v']) || !is_array($cache['v'])
            || !isset($cache['vDescribe']) || !is_array($cache['vDescribe'])
            || !isset($cache['key']) || !is_string($cache['key'])
            || !array_key_exists($cache['key'], $cache['v'])) {
            return false;
        }
        foreach ($cache['v'] as $field => $value) {
            if (!is_string($field) || !isset($cache['vDescribe'][$field]) || !is_array($cache['vDescribe'][$field])
                || !isset($cache['vDescribe'][$field]['type']) || !is_string($cache['vDescribe'][$field]['type'])) {
                return false;
            }
        }
        return true;
    }

    private function writeCache($cacheFile)
    {
        if (!$this->ensureCacheDir()) {
            return;
        }
        $cache = array(
            'v' => $this->v,
            'vDescribe' => $this->vDescribe,
            'key' => $this->key
        );
        $contents = json_encode($cache);
        if ($contents === false) {
            return;
        }
        $temporaryFile = @tempnam($this->cache_dir, '.miniorm-');
        if ($temporaryFile === false) {
            return;
        }
        @chmod($temporaryFile, 0600);
        $written = @file_put_contents($temporaryFile, $contents, LOCK_EX);
        if ($written !== strlen($contents) || !@rename($temporaryFile, $cacheFile)) {
            @unlink($temporaryFile);
        }
    }

}
