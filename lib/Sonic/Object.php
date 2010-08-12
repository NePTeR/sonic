<?php
namespace Sonic;
use Sonic\Database\Query;
use Sonic\Database\QueryCached;
use Sonic\App;

/**
 * Object
 *
 * @package Sonic
 * @subpackage Object
 * @author Craig Campbell
 */
abstract class Object
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var array
     */
    protected $_object_vars;

    /**
     * @var array
     */
    protected $_updates = array();

    /**
     * @var array
     */
    protected static $_unique_properties;

    /**
     * gets a property for this object
     *
     * @param string $property
     * @return mixed
     */
    public function __get($property)
    {
        $this->_verifyProperty($property);
        return $this->$property;
    }

    /**
     * sets a property of this object
     *
     * @param string $property
     * @param mixed $value
     * @return void
     */
    public function __set($property, $value)
    {
        $this->_verifyProperty($property);
        $current_value = $this->$property;

        if ($value === $current_value) {
            return;
        }

        $this->$property = $value;

        if (!in_array($property, $this->_updates)) {
            $this->_updates[] = $property;
        }
    }

    /**
     * verifies a property exists or throws an exception
     *
     * @param string $property
     * @return void
     */
    protected final function _verifyProperty($property)
    {
        if (!$this->_propertyExists($property)) {
            throw new Object\Exception('property ' . $property . ' does not exist in object: ' . get_class($this));
        }
    }

    /**
     * gets object vars
     *
     * @return array
     */
    protected final function _getObjectVars()
    {
        if ($this->_object_vars === null) {
            $this->_object_vars = get_object_vars($this);
        }
        return $this->_object_vars;
    }

    /**
     * checks if a property exists in this object
     *
     * @param string $property name of property
     * @return bool
     */
    protected final function _propertyExists($property)
    {
        return array_key_exists($property, $this->_getObjectVars());
    }

    /**
     * gets the definition for this object
     *
     * @return array
     */
    public static function getDefinition()
    {
        $class = get_called_class();
        return Object\DefinitionFactory::getDefinition($class);
    }

    /**
     * gets unique indexed properties for this object
     *
     * @return array
     */
    public final static function getUniqueProperties()
    {
        if (self::$_unique_properties !== null) {
            return self::$_unique_properties;
        }

        $unique = array();
        $definition = self::getDefinition();
        foreach ($definition['columns'] as $key => $column) {
            if (!isset($column['indexed'])) {
                continue;
            }

            if (!isset($column['unique'])) {
                continue;
            }

            if ($column['indexed'] && $column['unique']) {
                $unique[] = $key;
            }
        }
        self::$_unique_properties = $unique;

        return self::$_unique_properties;
    }

    /**
     * loads an object or a bunch of objects
     *
     * @param mixed $id value to load
     * @param string $column column to load that value from
     * @return Object
     */
    public final static function load($id, $column = 'id')
    {
        if (!is_array($id)) {
            return self::_loadSingle($id, $column);
        }

        if ($column != 'id') {
            throw new Object\Exception('you can only multiget an object by id');
        }

        return self::_loadMultiple($id);
    }

    /**
     * loads multiple objects by ids
     *
     * @todo implement
     */
    protected final static function _loadMultiple(array $ids)
    {
        return array();
    }

    /**
     * loads a single object
     *
     * @param mixed $value
     * @param string $column
     * @return Object
     */
    protected final static function _loadSingle($value, $column)
    {
        $class = get_called_class();
        $definition = self::getDefinition($class);

        // get these exceptions out of the way
        if ($column != 'id' && !isset($definition['columns'][$column])) {
            throw new Object\Exception('object of class: ' . $class . ' does not have a property called: ' . $column);
        }

        if ($column != 'id' && !$definition['columns'][$column]['indexed'] && !$definition['columns'][$column]['unique']) {
            throw new Object\Exception('column: ' . $column . ' in class ' . $class . ' has to be unique and indexed to load using it!');
        }

        // preliminary query to get the id if we are selecting based on another column
        if ($column != 'id') {
            $sql = "SELECT `id` FROM `" . $definition['table'] . '` WHERE `' . $column . '` = :' . $column . ' LIMIT 1';
            $query = new QueryCached($sql, self::_getCacheKey($column, $value), '1 week', $definition['schema']);
            $query->bindValue(':' . $column, $value);
            $id = $query->fetchValue();

            // now we can select it as usual
            $column = 'id';
            $value = $id;
        }

        // valid select
        $sql = "SELECT `id`, `" . implode('`, `', array_keys($definition['columns'])) . '` FROM `' . $definition['table'] . '` WHERE `' . $column . '` = ' . ':' . $column;
        $query = new QueryCached($sql, self::_getCacheKey($column, $value), '1 week', $definition['schema']);
        $query->bindValue(':' . $column, $value);

        $object =  $query->fetchObject($class);

        if (!$object) {
            return null;
        }

        return $object;
    }

    /**
     * saves or updates an object
     *
     * @return void
     */
    public function save()
    {
        if ($this->id && count($this->_updates) == 0) {
            return;
        }

        $definition = $this->getDefinition();

        // set default values
        foreach ($definition['columns'] as $property => $column) {

            // if this column is set to NOW we need to put the date in for cache
            if ($this->$property === 'NOW()') {
                $this->$property = date('Y-m-d H:i:s');
                continue;
            }

            // no default
            if (!isset($column['default'])) {
                continue;
            }

            // property already set
            if (in_array($property, $this->_updates)) {
                continue;
            }

            if (!$this->$property) {
                $this->$property = $column['default'];
            }
        }

        if (!$this->id || in_array('id', $this->_updates)) {
            $this->_add();
            $this->_reset();
            $this->_cache();
            return;
        }
        $this->_update();
        $this->_reset();
        $this->_cache();
    }

    /**
     * resets object properties that shouldn't be set in cache
     *
     * @return void
     */
    protected function _reset()
    {
        $definition = $this->getDefinition();
        $columns = array_keys($definition['columns']);
        foreach ($this->_getObjectVars() as $var => $value) {
            var_dump($var);
            if ($var != 'id' && !in_array($var, $columns)) {
                $this->$var = null;
            }
        }
        $this->_updates = array();
    }

    /**
     * adds an object to the database
     *
     * @return void
     */
    protected final function _add()
    {
        $definition = $this->getDefinition();
        $sql = 'INSERT INTO `' . $definition['table'] . '` (`' . implode('`, `', $this->_updates) . '`) VALUES (:' . implode(', :', $this->_updates) . ')';
        $query = new Database\Query($sql, $definition['schema']);
        foreach ($this->_updates as $column) {
            $query->bindValue(':' . $column, $this->$column);
        }
        $query->execute();

        $this->id = $query->lastInsertId();

        // go through all the unique properties and link them in cache to this id
        foreach ($this->getUniqueProperties() as $property) {
            App::getMemcache()->set(self::_getCacheKey($property, $this->$property), $this->id, '1 week');
        }
    }

    /**
     * updates an object in the database
     *
     * @return bool
     */
    protected final function _update()
    {
        if (in_array('id', $this->_updates)) {
            throw new Object\Exception('you cannot update an id after you create an object');
        }

        $definition = $this->getDefinition();
        $sql = "UPDATE `" . $definition['table'] . '` SET ';
        foreach ($this->_updates as $key => $column) {
            if ($key > 0) {
                $sql .= ', ';
            }
            $sql .= '`' . $column . '` = :' . $column;
        }
        $sql .= ' WHERE id = :current_id';
        $query = new Query($sql, $definition['schema']);
        foreach ($this->_updates as $column) {
            $query->bindValue(':' . $column, $this->$column);
        }
        $query->bindValue(':current_id', $this->id);

        foreach ($this->_updates as $update) {
            if (in_array($update, $this->getUniqueProperties())) {
                App::getMemcache()->set(self::_getCacheKey($update, $this->$update), $this->id, '1 week');
            }
        }

        return $query->execute();
    }

    /**
     * gets cache key for this object
     *
     * @param string $column
     * @param mixed $value
     * @return string
     */
    protected final static function _getCacheKey($column, $value)
    {
        $definition = self::getDefinition();
        return $definition['table'] . '_' . $column . ':' . $value;
    }

    /**
     * puts this object into cache
     *
     * @return void
     */
    protected final function _cache()
    {
        $cache_key = self::_getCacheKey('id', $this->id);
        App::getMemcache()->set($cache_key, $this, '1 week');
    }
}
