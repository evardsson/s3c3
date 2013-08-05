<?php
/**
 * ConfigItem class.
 *
 * The ConfigItem class maps to rows in the configs database for using database
 * sourced configuration for token and scheme.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage core
 */
namespace s3c3\core\model;

use \s3c3\core\Model;
use \s3c3\core\db\Database as DB;

/**
 * class Configuration
 *
 * @since version 1.0
 */
class ConfigItem extends Model
{

    /**
     * array of valid fields
     * @var fields
     * @access protected
     */
    protected $fields = array('id', 'ckey', 'cval');

    /**
     * array of fieldname aliases
     * @var aliases
     * @access protected
     */
    protected $aliases = array(
        'key'   => 'ckey',
        'value' => 'cval'
        );
    
    /**
     * string table name
     * @var table
     * @access protected
     */
    protected $table = 'configs';

    /**
     * integer object id
     * @var id
     * @access protected
     */
    protected $id;

    /**
     * string key name
     * @var ckey
     * @access protected
     */
    protected $ckey;

    /**
     * string value
     * @var cval
     * @access protected
     */
    protected $cval;
    
    /**
     * boolean value is serialized
     * @var cval
     * @access protected
     */
    protected $is_serial;
    
    /**
     * Write a configItem back to the db with a new value
     *
     * @param string key of the config item
     * @param mixed value to write out
     * @return boolean success
     */
    public static function writeConfigItem($key, $value, $create = false)
    {
        $conf = new self();
        $obj = $conf->findOne(array('ckey' => array(DB::EQ, $key)));
        if (is_null($obj)) {
            if (!$create) return false;
            $obj = $conf;
            $obj->ckey = $key;
        }
        $obj->cval = $value;
        $res = $obj->save();
        return $res ? true : false;
    }

    /**
     * Pull all config items and stuff into the Config
     *
     * @return array
     */
    public static function readConfigItems($writeConfig = false)
    {
        $res = DB::getAll('configs', null, array('ckey', 'cval'));
        $retval = array();
        foreach ($res as $row) {
            $tmp =& $retval;
            $fullkey = self::parseKey($row['ckey']);
            foreach ($fullkey as $part) {
                if (!isset($tmp[$part])) $tmp[$part] = array();
                $tmp =& $tmp[$part];
            }
            $tmp = $row['cval'];
        }
        //return $retval;
        if ($writeConfig) {
            foreach ($retval as $key => $value) \s3c3\conf\Config::getInstance()->write($key, $value);
        }
        return $retval;
    }

    /**
     * Parse a key and return the valid portion of $this->conf for the key
     */
    private static function parseKey($key)
    {
        if (strpos($key, '.') !== false) {
            return explode('.', $key);
        }
        return array($key);
    } 

}
