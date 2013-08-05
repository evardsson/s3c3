<?php
/**
 * Model class for database objects.
 *
 * Model class for database objects - ties a row in the database to an object.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage core
 */
namespace s3c3\core;
use \s3c3\core\db\Database as DB;
use \s3c3\conf\Config;
use \s3c3\except\ObjectNotFoundException;
use \s3c3\except\PropertyNotFoundException;
use \s3c3\except\RuntimeException;

/**
 * class Model
 * Model classes map to a table in the database, with object instances mapping to
 * a row in that table.
 * Model uses "magic" getters/setters, allowing access to the fields directly by
 * calling $object->field_name, and each model class has the ability to map
 * nicknames or alternate names to the field, so it could map (for instance) the
 * field called first_name to firstName or the field called ckey to key
 *
 * @since version 1.0
 */
class Model
{

    /**
     * array of valid fields
     * @var fields
     * @access protected
     */
    protected $fields = array();
    
    /**
     * array of fieldname aliases
     * @var aliases
     * @access protected
     */
    protected $aliases = array();
    
    /**
     * string table name
     * @var table
     * @access protected
     */
    protected $table;
    
    /**
     * string classname
     * @var classname
     * @access protected
     */
    protected $classname;
    
    /**
     * Constructor
     * Create a new Model object
     * When called with null args this creates an empty model object
     * When called with an integer this creates an object and hydrates it from the
     * database row with that id. If that id does not exist in the database this
     * throws an ObjectNotFoundException
     * When called with an associative array this will hydrate from the array and
     * will not attempt to load from the database
     *
     * @param mixed $args
     * @throws ObjectNotFoundException
     */
    public function __construct($args = null)
    {
        $this->classname = get_called_class();
        if (is_array($args)) {
            foreach ($args as $key => $val) {
                if (in_array($key, $this->fields)) $this->$key = $val;
            }
        } else if (!is_null($args)) {
            $this->id = $args;
            $this->load();
        }
    }
    
    /**
     * Load this object from the database
     *
     * @return void
     * @throws ObjectNotFoundException
     */
    protected function load()
    {
        $res = DB::getRow($this->table, array('id'=>array(DB::EQ, $this->id)));
        if (empty($res)) {
            throw new ObjectNotFoundException(
                "No object type {$this->classname} found with id {$this->id}");
        }
        foreach ($res as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Actions to take before saving to database
     *
     * @return void
     */
    public function beforeSave()
    {
    }
    
    /**
     * Save this object to the database
     *
     * @return int id
     */
    public function save()
    {
        $this->beforeSave();
        $dtime = date('Y-m-d H:i:s');
        if (in_array('updated', $this->fields)) $this->updated = $dtime;
        $data = array();
        foreach ($this->fields as $fld) {
            $data[$fld] = $this->$fld;
        }
        if (empty($data['id'])) {
            if (in_array('created', $this->fields)) $data['created'] = $this->created = $dtime;
            try{
                $retval = DB::insert($this->table, $data);
            } catch (\Exception $e) {
                throw new RuntimeException("Insert of {$this->classname} failed.", 0, $e);
            }
            $this->id = $retval;
        } else {
            try {
                $retval = DB::update($this->table, $data['id'], $data);
            } catch (\Exception $e) {
                throw new RuntimeException("Update of {$this->classname} failed.", 0 , $e);
            }
        }
        return $retval;
    }
    
    /**
     * Delete this object from the database
     *
     * @return boolean
     */
    public function delete()
    {
        return DB::delete($this->table, $this->id) > 0;
    }
    
    /**
     * Find an object in the database
     *
     * @param array args to search: example:
     *     array (
     *        'id' => array(Database::NIN ,array(1,2,4,7,8)),
     *        'city' => array(Database::EQ, 'Cleveland'),
     *        'date' => array(Database::BTW, '2012-12-31','2013-07-31'),
     *        'last_name' => array(Database::IN, array('Smith', 'Jones')),
     *        'first_name' => array(Database::LIKE, 'Mic%'),
     *        'income' => array(Database::LTE, 100000));
     *     Resulting search clause:
     *        WHERE id NOT IN (?, ?, ?, ?, ?) AND city = ? AND date BETWEEN ? AND ?
     *        AND last_name IN (?, ?) AND first_name LIKE ? AND income <= ?
     *     Resulting values:
     *        array(1,2,4,5,8,'Cleveland','2012-12-31','2013-07-31',
     *              'Smith', 'Jones', 'Mic%', 100000);
     * @param array order to sort results example:
     *     array ( 'last_name' => 'ASC', 'id' => 'DESC');
     * @return void
     */
    public function find($args, $order = false, $limit = false)
    {
        $res = DB::getAll($this->table, $args, $order, $limit);
        $retval = array();
        foreach ($res as $row) {
            $retval[] = new $this->classname($row);
            
        }
        return $retval;
    }
    
    /**
     * Find all types of this object in the database
     *
     * @return array of model objects
     */
    public function findAll()
    {
        return $this->find(null);
    }
    
    /**
     * Find one object in the database
     *
     * @param array args to search: example:
     *     array(
     *        'id' => array(Database::NIN ,array(1,2,4,7,8)),
     *        'city' => array(Database::EQ, 'Cleveland'),
     *        'date' => array(Database::BTW, '2012-12-31','2013-07-31'),
     *        'last_name' => array(Database::IN, array('Smith', 'Jones')),
     *        'first_name' => array(Database::LIKE, 'Mic%'),
     *        'income' => array(Database::LTE, 100000));
     *     Resulting search clause:
     *        WHERE id NOT IN (?, ?, ?, ?, ?) AND city = ? AND date BETWEEN ? AND ?
     *        AND last_name IN (?, ?) AND first_name LIKE ? AND income <= ?
     *     Resulting values:
     *        array(1,2,4,5,8,'Cleveland','2012-12-31','2013-07-31',
     *              'Smith', 'Jones', 'Mic%', 100000);
     * @return object or null
     */
    public function findOne($args)
    {
        $ret = DB::getRow($this->table, $args);
        return !empty($ret) ? new $this->classname($ret) : null;
    }
    
    /**
     * Magic getter
     * This only allows for getting items that are in the field names or aliases
     * 
     * @param string key to get
     * @return mixed
     * @throws PropertyNotFoundException
     */
    public function __get($key)
    {
        $kkey = $key;
        if (array_key_exists($key, $this->aliases)) $kkey = $this->aliases[$key];
        if (in_array($kkey, $this->fields)) return $this->$kkey;
        throw new PropertyNotFoundException("Property $key not found on {$this->classname}");
    }

    /**
     * Magic getter
     * This only allows for setting items that are in the field names or aliases
     * 
     * @param string key to set
     * @param mixed  value to set to the selected key
     * @return void
     * @throws PropertyNotFoundException
     */
    public function __set($key, $value)
    {
        $kkey = $key;
        if (array_key_exists($key, $this->aliases)) $kkey = $this->aliases[$key];
        if (!in_array($kkey, $this->fields))
            throw new PropertyNotFoundException("Property $key not found on {$this->classname}");
        $this->$kkey = $value;

    }

}
