<?php
/**
 * Convert a PHP Error into an ApplicationException.
 *
 * This converts PHP Errors (other than FATAL) into an ApplicationException of the
 * same level
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
 * class ErrorException
 *
 * @since version 1.0
 */
class ErrorException extends ApplicationException
{

    /**
     * Override __construct to deal with Errors
     */
    public function __construct($message = "", $code = 0, $errno, $filename = __FILE__,
        $lineno = __LINE__, $previous = null)
    {
        switch ( $errno ) {
            case E_USER_ERROR:
            case E_ERROR:
                $level = Logger::CRITICAL;
                break;
            case E_USER_WARNING:
            case E_WARNING:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                $level = Logger::WARNING;
                break;
            case E_USER_NOTICE:
            case E_NOTICE:
            case E_STRICT:
                $level = Logger::NOTICE;
                break;
            case E_RECOVERABLE_ERROR:
            default:
                $level = Logger::ERROR;
                break;
        }
        $this->filename = $filename;
        $this->lineno = $lineno;
        parent::__construct($message, $code, $previous, $level);
    }

}
