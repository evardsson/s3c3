<?php
/**
 * SMS (via Email) handler for logging.
 *
 * SMS (via Email) handler for Monolog Logging - based on NativeMailHandler in Monolog.
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
use Monolog\Handler\NativeMailHandler;
use Monolog\Logger;

/**
 * class LogMailSmsHandler
 *
 * @since version 1.0
 */
class LogMailSmsHandler extends NativeMailHandler
{

    /**
     * Constructor - create a new LogMailSmsHandler.
     *
     * @param string|array $to      The receiver of the mail
     * @param string       $subject The subject of the mail
     * @param string       $from    The sender of the mail
     * @param integer      $level   The minimum logging level at which this handler will be triggered
     * @param boolean      $bubble  Whether the messages that are handled can bubble up the stack or not
     */
    public function __construct($level = Logger::ERROR, $bubble = true)
    {
        $to = Config::getInstance()->read('logging.sms.to');
        $subject = 'ERROR FROM S3C3 '.Config::getInstance()->read('env.deployment');
        $from = Config::getInstance()->read('logging.sms.from');
        parent::__construct($to, $subject, $from, $level, $bubble);
    }

}
