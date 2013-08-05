<?php
/**
 * Exception used when class not found by class loader.
 *
 * Exception thrown by S3C3 class loader indicating the the requested class can not
 * be found.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage exceptions
 */
namespace s3c3\except;

/**
 * class ClassNotFoundException
 *
 * @since version 1.0
 */
class ClassNotFoundException extends ApplicationException
{

}
