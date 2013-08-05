<?php
/**
 * This is the initilization point for the S3C3 bootstrap utility.
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
 * @subpackage bootstrap
 */
namespace s3c3\bootstrap;

ini_set('error_reporting', E_ERROR);
/**
 * Common items needed by all scripts/classes
 * Note that this file is in the global namespace.
 */
if (!defined('S3C3_ROOT')) define('S3C3_ROOT', __DIR__ . '/../');
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

// get the default Configuration
$conf = \s3c3\conf\Config::getInstance();
if (!file_exists(S3C3_CONF . '/conf.yml')) {
    $confarray = array(
        'env' => array(
            'deployment' => 'dev',
            'config' => 'file',
            'local_scheme_name' => null,
            'debug' => array(
                'level' => 5,
                'token' => 's3c3_debug_token'
                )
            ),
        'database' => array(
            'prefix' => null,
            'dbmaster' => array(
                'driver' => 'mysqli',
                'host' => 'localhost', 
                'port' => 3306,
                'user' => 's3c3',
                'password' => null,
                'defaultdb' => 's3c3'
                ),
            'dbslave' => array(
                'driver' => 'mysqli',
                'host' => 'localhost', 
                'port' => 3306,
                'user' => 's3c3',
                'password' => null,
                'defaultdb' => 's3c3'
                )
            ),
        'certificate' => array(
          'store' => '/var/s3c3/certificates',
          'validate' => 1
          ),
        's3c3_local_listener' => array(
            'endpoint' => 'http://localhost/s3c3',
            'internal_token' => null,
            'version' => 1,
            ),
        'logging' => array(
            'file' => array(
                'dir' => '/var/log/s3c3/'
                ),
            'mail' => array(
                'to' => null,
                'from' => null
                ),
            'sms' => array(
                'to' => null,
                'from' => null
                )
            ),
        'token' => array(
            'expire' => 90,
            'length' => 64,
            'strength' => 'maximum',
            'delete_on_load' => 1
            ),
        'scheme' => array(
            'listeners' => array(),
            'clients' => array()
            )
        );
    foreach($confarray as $key => $val) $conf->write($key, $val);
}
