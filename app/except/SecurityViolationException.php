<?php
/**
 * SecurityViolationException.
 *
 * SecurityViolationException provides an exception which should raise flags in the
 * log files, especially if it comes up repeatedly for one client or listener.
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
 * class SecurityViolationException
 *
 * @since version 1.0
 */
class SecurityViolationException extends ApplicationException
{

}
