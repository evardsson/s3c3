<?php
/**
 * This is the initilization point for all parts of S3C3.
 *
 * This file sets up definitions and prepares the class loader. This file is placed
 * in the s3c3 namespace to avoid conflicts with other projects that may be installed
 * along side it.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 */
namespace s3c3;

/**
 * Common items needed by all scripts/classes
 * Note that this file is in the global namespace.
 */
if (!defined('S3C3_ROOT')) define('S3C3_ROOT', __DIR__);
if (!defined('S3C3_APP')) define('S3C3_APP', S3C3_ROOT . '/app');
if (!defined('S3C3_BOOTSTRAP')) define('S3C3_BOOTSTRAP', S3C3_ROOT . '/bootstrap');
if (!defined('S3C3_CONF')) define('S3C3_CONF', S3C3_APP . '/conf');
if (!defined('S3C3_UNITTEST')) define('S3C3_UNITTEST', false);
if (!defined('S3C3_VENDORS')) define('S3C3_VENDORS', S3C3_ROOT . '/composer/vendors');
if (!defined('S3C3_VERSION')) define('S3C3_VERSION', '1.0');

// get the composer autoload
include_once S3C3_VENDORS . '/autoload.php';

// and the local class loader
include_once S3C3_APP . '/ClassLoader.php';

// need to add other namespaces? add them here
// NOTE: ClassLoader expects namespaces to match directory names unless explicitly
// defined otherwise - like bootstrap - these should go BEFORE s3c3 root namespace
\s3c3\ClassLoader::addNamespace('s3c3\\bootstrap', S3C3_BOOTSTRAP . '/');
\s3c3\ClassLoader::addNamespace('s3c3', S3C3_APP . '/');
\s3c3\ClassLoader::addDefaultNamespace();


// unittest autoloader
if (S3C3_UNITTEST) include_once S3C3_ROOT . '/unittest/BaseUnit.php';//autoload.php';

if (!S3C3_UNITTEST) {
    set_exception_handler(array('\s3c3\ErrorHandler', 'handleException'));
    set_error_handler(array('\s3c3\ErrorHandler', 'handleError'));
    $handlers = array();
    switch (\s3c3\conf\Config::getInstance()->read('env.deployment')) {
        case 'dev':
            error_reporting(E_ALL & ~E_NOTICE);
            ini_set('display_errors', 'on');
            $handlers[] = new \s3c3\util\logger\LogFileHandler(\Monolog\Logger::DEBUG);
            break;
        case 'staging':
            $handlers[] = new \s3c3\util\logger\LogFileHandler(\Monolog\Logger::DEBUG);
            $handlers[] = new \s3c3\util\logger\LogMailHandler(\Monolog\Logger::ERROR);
            break;
        case 'prod':
            $handlers[] = new \s3c3\util\logger\LogFileHandler(\Monolog\Logger::ERROR);
            $handlers[] = new \s3c3\util\logger\LogMailHandler(\Monolog\Logger::CRITICAL);
            $handlers[] = new \s3c3\util\logger\LogMailSmsHandler(\Monolog\Logger::ALERT);
            break;
    }
    /**
     * initiate logging
     */
    $logger = \s3c3\LoggerManager::getLogger('s3c3');
    foreach ($handlers as $handler) {
        $logger->pushHandler($handler);
    }
}
if (\s3c3\conf\Config::getInstance()->read('env.config') == 'db') {
    \s3c3\core\model\ConfigItem::readConfigItems(true);
}
