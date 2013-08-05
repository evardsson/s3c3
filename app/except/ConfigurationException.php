<?php
/**
 * Exception indicating a configuration error.
 *
 * Configuration errors are fatal - this exception being raised should halt the
 * process and notify the user immediately
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage except
 */
namespace s3c3\except;

/**
 * class ConfigurationException
 *
 * @since version 1.0
 */
class ConfigurationException extends ApplicationException
{

}
