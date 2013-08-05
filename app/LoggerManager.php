<?php
/**
 * This is the logger manager for S3C3.
 *
 * class \s3c3\LoggerManager provides Monolog/Loggers
 * Allows for using loggers in different namespaces. The default namespace to use
 * is 's3c3'
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 */
namespace s3c3;
use \Monolog\Logger;

/**
 * class \s3c3\LoggerManager
 *
 * @since version 0.1
 */
class LoggerManager
{
    /**
     * array of loggers
     * @var array $loggers
     */
    private static $loggers;
    
    /**
     * Constructor - private, unused
     */
    private function __construct()
    {
    }
    
    /**
     * Get a Monolog/Logger by name
     *
     * @param string $name
     * @return object Monolog/Logger
     */
    public static function getLogger($name = 's3c3')
    {
        if (!is_array(self::$loggers))
            self::$loggers = array();
        if (empty(self::$loggers[$name])) {
            self::$loggers[$name] = new Logger($name);
        }
        return self::$loggers[$name];
    }
    
}
