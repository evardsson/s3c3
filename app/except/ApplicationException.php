<?php
/**
 * Base exception - adds severity
 *
 * Note that severity types available match RFC 5424 6.2.1 severity levels and use
 * Monolog\Logger constant values for codes
 *  EMERGENCY: system is unusable
 *  ALERT: action must be taken immediately
 *  CRITICAL: critical conditions
 *  ERROR: error conditions
 *  WARNING: warning conditions
 * --- the following levels are supported, but why throw exceptions for these?
 *  NOTICE: normal but significant condition
 *  INFO: informational messages
 *  DEBUG: debug-level messages
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage exceptions
 */
namespace s3c3\except;
use \Monolog\Logger;

/**
 * class ApplicationException
 *
 * @since version 1.0
 */
class ApplicationException extends \Exception
{
    
    const EMERGENCY = Logger::EMERGENCY;
    const ALERT = Logger::ALERT;
    const CRITICAL = Logger::CRITICAL;
    const ERROR = Logger::ERROR;
    const WARNING = Logger::WARNING;
    const NOTICE = Logger::NOTICE;
    const INFO = Logger::INFO;
    const DEBUG = Logger::DEBUG;

    /**
     * int severity number for this exception
     * @var severity
     * @access protected
     */
    protected $severity = 0;
    
    /**
     * Constructor
     *
     * @param string $message to set to Exception
     * @param int $code default 0
     * @param Exception $previous default null
     * @param int $severity default self::ERROR
     */
    public function __construct($message = "", $code = 0, $previous = null, $severity = self::ERROR)
    {
        $this->severity = $severity;
        parent::__construct($message, $code, $previous);
    }
    
    /**
     * Get the severity number for this exception (from Monolog\Logger)
     * This allows for easy tie-in with logging/alerts/etc.
     *
     * @return int
     */
    public function getSeverity()
    {
        return $this->severity;
    }

    /**
     * Get the severity name for this exception (from Monolog\Logger)
     * This allows for easy tie-in with logging/alerts/etc.
     *
     * @return int
     */
    public function getSeverityName()
    {
        return Logger::getLevelName($this->severity);
    }

}
