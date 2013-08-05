<?php
/**
 * Excpetion when property not found on Model.
 *
 * This exception is thrown when trying to access a property on a Model object that
 * does not exist or is not accessible on the object.
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
 * class PropertyNotFoundException
 *
 * @since version 1.0
 */
class PropertyNotFoundException extends ApplicationException
{

}
