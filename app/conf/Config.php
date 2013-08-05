<?php
/**
 * Config holds the configuration for S3C3.
 *
 * The Config class reads in the conf.yml and any database configurations to
 * provide configuration support to the application.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 */
namespace s3c3\conf;

use \s3c3\core\model\ConfigItem;
use \Symfony\Component\Yaml\Yaml;

/**
 * class Config
 *
 * @since version 1.0
 */
class Config
{
    /**
     * @var instance Config
     * Internal instance container
     */
    private static $instance = null;

    /**
     * @var conf
     * array of configuration data
     */
    private $conf = null;
    
    /**
     * No public construct
     */
    private function __construct()
    {
        $this->conf = array();
        if (file_exists(S3C3_CONF . '/conf.yml')) $this->writeFromYaml();
    }
    
    /**
     * Get the Config instance
     *
     * @return Covfig object
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) self::$instance = new self();
        return self::$instance;
    }
    
    /**
     * Checks if the passed namespace is set.
     * @param string $namespace The namespace to check
     * @return boolean
     */
    public function exists($key)
    {
        $fullkey = $this->parseKey($key);
        $tmp = $this->conf;
        foreach ($fullkey as $keypart) {
            if (isset($tmp[$keypart])) {
                $tmp = $tmp[$keypart];
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Delete a key from the config array
     * @param string
     * @return boolean
     */
    public function delete($key = null)
    {
        if (is_null($key)) {
            $this->conf = array();
        } else {
            $fullkey = $this->parseKey($key);
            $tmp =& $this->conf;
            foreach ($fullkey as $keypart) {
                if (isset($tmp[$keypart])) {
                    $tmp =& $tmp[$keypart];
                } else {
                    return false;
                }
            }
            $tmp = null;
        }
        return true;
    }
    
    /**
     * Reads the value of the config item for the key. Returns null if not found
     * @param string key the key to search for, if null returns entire config array
     * @return mixed
     */
    public function read($key = null)
    {
        if (is_null($key)) {
            return $this->conf;
        } else {
            $fullkey = $this->parseKey($key);
            $tmp = $this->conf;
            foreach ($fullkey as $keypart) {
                if (isset($tmp[$keypart])) {
                    $tmp = $tmp[$keypart];
                } else {
                    return null;
                }
            }
            return $tmp;
        }
    }

    /**
     * Set the value of a config item
     * @param string key The key to write
     * @param mixed value The value to write
     * @return void
     */
    public function write($key, $value)
    {
        $fullkey = $this->parseKey($key);
        $isarray = $numkey = false;
        if (is_numeric($fullkey[count($fullkey)-1])) {
            $isarray = true;
            $numkey = array_pop($fullkey);
        }
        $tmp =& $this->conf;
        foreach ($fullkey as $keypart) {
            if (!isset($tmp[$keypart])) $tmp[$keypart] = array();
            $tmp =& $tmp[$keypart];
        }
        if ($isarray) {
            if (!is_array($tmp)) {
                if (empty($tmp)) $tmp = array();
                else $tmp = array($tmp);
            }
            $tmp[$numkey] = $value;
        }
        $tmp = $value;
    }
    
    /**
     * Write the configuration from the deployment namespace yaml file in conf dir.
     * @param string $stage - one of 'dev', 'staging' or 'prod'
     */
    public function writeFromYaml($filename = null) {
        if (is_null($filename)) $filename = S3C3_CONF . '/conf.yml';
        $confarray = Yaml::parse($filename);
        foreach ($confarray as $key => $value) {
            $this->write($key, $value);
        }
        if (!defined('S3C3_PREFIX')) define('S3C3_PREFIX', $this->read('database.prefix'));
    }

    /**
     * Parse a key and return the valid portion of $this->conf for the key
     */
    private function parseKey($key)
    {
        if (strpos($key, '.') !== false) {
            return explode('.', $key);
        }
        return array($key);
    } 

}
