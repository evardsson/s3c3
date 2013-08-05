<?php
/**
 * InvalidCertificateException.
 *
 * InvalidCertificateException is thrown when a certificate is either:
 * not signed by the known trusted root or has expired
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
 * class InvalidCertificateException
 *
 * @since version 1.0
 */
class InvalidCertificateException extends ApplicationException
{

}
