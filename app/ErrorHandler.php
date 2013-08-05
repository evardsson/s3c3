<?php
/**
 * This is the error handler for S3C3.
 *
 * This error handler converts non-fatal PHP Errors to Exceptions and handles
 * logging Exceptions
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 */
namespace s3c3;
use \s3c3\conf\Config;
use \Monolog\Logger;

/**
 * class ErrorHandler
 *
 * @since version 1.0
 */
class ErrorHandler
{
    /**
     * Exception handler
     * @param Exception $e
     */
    public static function handleException(\Exception $e)
    {
        if (Config::getInstance()->read('env.deployment') == 'dev'
            && Config::getInstance()->read('env.debug.level') >= 3) {
            $stderr = fopen('php://stderr', 'w');
            fwrite($stderr,$e."\n"); 
            fclose($stderr);
        }
        $logger = LoggerManager::getLogger('s3c3');
        $level = Logger::ERROR;
        if ($e instanceof \s3c3\except\ApplicationException) {
            $level = $e->getSeverity();
        }
        $message = "Exception: ".$e->getMessage()."\n";
        $message .= $e->getTraceAsString();
        $logger->addRecord($level, $message);
    }
    
    /**
     * Error handler (converts an error into an exception) to be handled by exceptionHandler
     * @param int $errno the error number
     * @param string $errstr the error message
     * @param string $errfile the name of the file with an error
     * @param int $errline the line number (approx) of the error
     */
    public static function handleError($errno, $errstr, $errfile, $errline)
    {
        $e = new \s3c3\except\ErrorException($errstr, 0, $errno, $errfile, $errline, null);
        self::handleException($e);
    }

}
