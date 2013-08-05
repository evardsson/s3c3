<?php
/**
 * InvalidUsageException.
 *
 * InvalidUsageException is thrown whenever a certificate is looked up for a
 * specific purpose, but the certificate is not validated for that purpose.
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
 * class InvalidUsageException
 *
 * @since version 1.0
 */
class InvalidUsageException extends ApplicationException
{

}
