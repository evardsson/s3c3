<?php
/**
 * File handler for logging.
 *
 * File handler for Monolog Logging - based on StreamHandler in Monolog.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage utilities
 */
namespace s3c3\util\logger;
use \s3c3\conf\Config;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

/**
 * class LogFileHandler
 *
 * @since version 1.0
 */
class LogFileHandler extends StreamHandler
{

    /**
     * @var string The directory to log all messages
     */
    protected $logDirectory;
    
    /**
     * @var string The log prefix - subclasses should set this to something meaningful
     * for them
     */
    protected $prefix = '';

    /**
     * Constructor - create a new LogFileHandler.
     *
     * @param string  $logDirectory Parent directory for all logs
     * @param int     $level        The minimum logging level at which this handler will be triggered
     * @param bool    $bubble       Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct( $level = Logger::DEBUG, $bubble = true ) {
        $this->logDirectory = $this->getLogDirectory();
        parent::__construct( $this->getLogFile(), $level, $bubble );
    }
    
    /**
     * Determines the log directory.
     *
     * @param array $record
     * @return string
     */
    protected function getLogDirectory() {
        return Config::getInstance()->read('logging.file.dir');
    }

    /**
     * Determines the log file.
     *
     * @param array $record
     * @return string
     */
    private function getLogFile(array $record = array()) {
        $level = (!empty($record['level'])) ? 
            Logger::getLevelName($record['level']) . '_' : '';
        return $this->logDirectory .
            DIRECTORY_SEPARATOR . $this->prefix . $level .
            $this->getDateString().'.log';
    }

    /**
     * Returns a string used to name log files (date).
     *
     * @return string
     */
    private function getDateString() {
        return date( 'Y-m-d' );
    }

    /**
     * Writes a log to file.
     * Switches to a different log file if necessary (based on date/severity).
     *
     * @param array $record
     */
    public function write( array $record ) {
        // Determine where this log should go
        $logFile = $this->getLogFile($record);

        // If this log location differs from the previous log, close the
        // previous log file and set up the new one.
        if( $logFile !== $this->url ) {
            // Close the previous log file and set the new file url
            $this->close();
            $this->url = $logFile;
        }

        // Now that correct network/date log file has been pointed to we can
        // write the message
        parent::write( $record );
    }

}
