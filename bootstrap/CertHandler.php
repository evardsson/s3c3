<?php
/**
 * Helper class for Cert.
 *
 * This class handles adding certificates to the store, removing certificates from
 * the store, and may be extended to implement applying CRLs by finding the
 * certificates in the store that have been revoked and removing them. Note: to
 * replace a certificate in the store with a newer version it is currently required
 * to first remove the old certificate and then add the new one. Also, adding a new
 * server certificate with no matching listener name in the current scheme will add
 * a new listener to the scheme with a url of '?' - it is up to the administrator
 * to correct the configs after adding new listener names. It is similarly up to
 * the administrator to add new client names when needed. This class does not do that.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage bootstrap
 * @todo implement CRL functionality
 */
namespace s3c3\bootstrap;

use \s3c3\conf\Config;
use \s3c3\except\InvalidArgumentException;
use \s3c3\except\RuntimeException;

/**
 * class CertHandler
 *
 * @since version 1.0
 */
class CertHandler extends \s3c3\core\crypto\Cert
{

    /**
     * Static method to load a new PEM certificate into the scheme
     *
     * @param string name to give certificate in scheme
     * @param int    purpose for certificate (SERVER or CLIENT only)
     * @param string certificate to load
     * @return void
     */
    public static function loadCertificate($name, $purpose, $certstring)
    {
        if ($purpose != parent::CLIENT && $purpose != parent::SERVER) {
            throw new InvalidArgumentException('Only client or server certificates can be loaded.');
        }
        $cert = new self($name, $purpose);
        $certx = $cert->getCertificate();
        if (!empty($certx)) {
            throw new InvalidArgumentException("Certificate for name $name already exists in store.");
        }
        $cert->setCertificate($certstring);
        $fname = Config::getInstance()->read('certificate.store');
        $fname .= "/$name.crt";
        file_put_contents($fname, $certstring);
        chmod($fname, 0400);
        return $cert;
    }

    /**
     * Write the current certificate to the certificate store
     *
     * @return boolean success
     */
    public function writeToStore()
    {
        $dir = Config::getInstance()->read('certificate.store');
        $fname = $dir . '/' . $this->name . '.crt';
        if (file_put_contents($fname, $this->certificate) !== false) {
            chmod($fname, 0400);
            parent::scanStore(true);
        }
        return ($this->findCert($this->name) === $this->certificate);
    }

    /**
     * Remove a certificate from the store
     * This will not remove private certificates (with a .pem extension)
     *
     * @return boolean success
     */
    public static function removeFromStore($name)
    {
        $dir = Config::getInstance()->read('certificate.store');
        $fname = $dir . '/' . $name . '.crt';
        if (file_exists($fname) && chmod($fname, 0600)) {
            // overwrite the file with a single empty string first to be sure
            if (file_put_contents($fname, str_repeat('0', max(filesize($fname), 1024))) !== false) {
                if (@unlink($fname)) return true;
            }
        }
        return false;
    }

    /**
     * Take in a CRL, verify the signature and date and then take needed action
     * @param string crl - the CRL to apply
     */
    public static function applyCRL($crl)
    {
        throw new RuntimeException("CertHandler::applyCRL not implemented");
    }
}
