<?php
/**
 * Cert class manages certificates for S3C3.
 *
 * The Cert class is the one location for all lookup, verification and retrieval of
 * public SSL certificates and private SSL keys for all of S3C3. Note that any
 * class that wishes to extend this class (to use, for example, GnuPG rather than
 * OpenSSL) must implement most of the methods in this class or it will fail.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage core
 */
namespace s3c3\core\crypto;

use \s3c3\conf\Config;
use \File_X509;
use \s3c3\except\RuntimeException;
use \s3c3\except\InvalidArgumentException;
use \s3c3\except\ObjectNotFoundException;
use \s3c3\except\InvalidCertificateException;
use \s3c3\except\InvalidUsageException;
use \s3c3\except\SchemeNameException;

/**
 * class Cert
 *
 * @since version 1.0
 */
class Cert
{

    /**
     * static array of valid public certificate filenames
     * @var certs
     * @access protected
     */
    protected static $certs;

    /**
     * static array of valid private key certificate filenames
     * @var keys
     * @access protected
     */
    protected static $keys;

    /**
     * string valid root certificate filenamme
     * @var root
     * @access protected
     */
    protected static $root;

    const CLIENT = 1;
    const SERVER = 2;
    const CRL_SIGN = 4;
    const CA = 8;

    /**
     * the string PEM certificate for this instance
     * @var certificate
     * @access protected
     */
    protected $certificate;

    /**
     * the string name for this instance
     * @var name
     * @access protected
     */
    protected $name;

    /**
     * boolean value whether this certificate is a private key certificate
     * @var isPrivate
     * @access protected
     */
    protected $isPrivate;

    /**
     * Constructor
     * Create a new Cert object
     */
    public function __construct($name = null, $purpose = self::SERVER, $isPrivate = false)
    {
        self::scanStore();
        $this->isPrivate = $isPrivate;
        if (!is_null($name)) {
            $this->checkName($name, $purpose);
            $this->setName($name);
            $certstr = $this->findCert($name);
            $this->setCertificate($certstr);
        }
    }

    /**
     * Scan certs and keys from cert store
     *
     * @param boolean force to reload certs/keys
     * @return void
     */
    public static function scanStore($force = false)
    {
        if (is_null(self::$certs) || is_null(self::$keys)
            || is_null(self::$root) || $force)
        {
            $conf = Config::getInstance();
            self::$certs = array();
            self::$keys = array('server' => null, 'client' => null);
            $dir = $conf->read('certificate.store');
            $files = glob("$dir/*.crt");
            if (is_array($files)) foreach ($files as $file) {
                if (preg_match('/(_root|_ca|.ca).crt/', basename($file)))
                    self::$root = $file;
                else
                    self::$certs[] = $file;
            }
            $files = glob("$dir/*.pem");
            $listeners = $conf->read('scheme.listeners');
            if (is_array($files)) foreach ($files as $file) {
                $name = str_replace('.pem','', basename($file));
                if (array_key_exists($name, $listeners)) {
                    self::$keys['server'] = $file;
                } else {
                    self::$keys['client'] = $file;
                }
            }
        }
        if (empty(self::$root))
            throw new RuntimeException('Root CA certificate not found');
        elseif (empty(self::$certs))
            throw new RuntimeException('Server and client certificates not found.');
        elseif (empty(self::$keys))
            throw new RuntimeException('Local keys not found.');
    }

    /**
     * Check the file permissions of the certificate store and set to 0400 if
     * the permissions are incorrect
     *
     * @return boolean success
     */
    public static function checkFilePermissions()
    {
        $dir = Config::getInstance()->read('certificate.store');
        $files = glob("$dir/*.crt");
        if (is_array($files)) foreach ($files as $file) {
            if (substr(sprintf('%o', fileperms($file)), -4) !== '0400') {
                chmod($file, 0400);
            }
        }
        $files = glob("$dir/*.pem");
        if (is_array($files)) foreach ($files as $file) {
            if (substr(sprintf('%o', fileperms($file)), -4) !== '0400') {
                chmod($file, 0400);
            }
        }
    }

    /**
     * List all known certificates for this scheme
     *
     * @return array
     */
    public function getKnownCertificates()
    {
        self::scanStore();
        $retval = array();
        foreach (self::$certs as $cert) {
            $retval[] = str_replace('.crt', '', basename($cert));
        }
        // put them alphabetical and reset the keys
        asort($retval);
        return array_values($retval);
    }

    /**
     * Get our root CA
     *
     * @param boolean asObj Cert object default false
     * @return string certificate or Cert object
     */
    public static function getRoot($asObj = false)
    {
        self::scanStore();
        $cstr = file_get_contents(self::$root);
        if (!$asObj) return $cstr;
        $name = str_replace('.crt','',basename(self::$root));
        $cert = new self();
        $cert->setName($name);
        $cert->setCertificate($cstr);
        return $cert;
    }

    /**
     * Get our local server key
     *
     * @param boolean asObj Cert object default false
     * @return string certificate or Cert object
     */
    public static function getLocalServerKey($asObj = false)
    {
        self::scanStore();
        $cstr = file_get_contents(self::$keys['server']);
        if (!$asObj) return $cstr;
        $name = str_replace('.pem','',basename(self::$keys['server']));
        $cert = new self();
        $cert->setName($name);
        $cert->setCertificate($cstr);
        return $cert;
    }

    /**
     * Get our local client key
     *
     * @param boolean asObj Cert object default false
     * @return string certificate or Cert object
     */
    public static function getLocalClientKey($asObj = false)
    {
        self::scanStore();
        $cstr = file_get_contents(self::$keys['client']);
        if (!$asObj) return $cstr;
        $name = str_replace('.pem','',basename(self::$keys['client']));
        $cert = new self();
        $cert->setName($name);
        $cert->setCertificate($cstr);
        return $cert;
    }

    /**
     * Get our current certificate
     *
     * @return string certificate
     */
    public function getCertificate()
    {
        return $this->certificate;
    }

    /**
     * Set our current certificate name
     *
     * @param string name
     * @return void
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * Set our certificate text
     *
     * @param string certificate (PEM encoded)
     * @return void
     */
    public function setCertificate($certificate)
    {
        $this->certificate = $certificate;
    }

    /**
     * Get a cert for a server pool or specfic client
     *
     * @param string name to search for
     * @param int purpose (self::CLIENT || self::SERVER)
     * @param boolean verify (default true)
     * @param boolean isPrivate (default false)
     * @return string certificate
     * @throws ObjectNotFoundException
     * @throws InvalidUsageException
     * @throws InvalidCertificateException
     * @throws InvalidArgumentException
     */
    public static function getCertificateFromStore($name, $purpose = self::CLIENT,
        $validate = true, $isPrivate = false)
    {
        if (!in_array($purpose, array(1,2,4,8))) {
            throw new InvalidArgumentException("Unknown purpose type passed to getCert()");
        }
        $cert = new self($name, $isPrivate);
        $certstr = $cert->getCertificate();
        if (empty($certstr)) {
            throw new ObjectNotFoundException(
                "No certificate for $name found in certificate store.");
        }
        if ($validate && Config::getInstance()->read('certificate.validate')) {
            $valid = $cert->isValid($purpose, false);
        }
        return $certstr;
    }

    /**
     * Is this a valid cert (signed by our root and for the purpose set?)
     *
     * @param int purpose
     * @return boolean
     * @throws InvalidUsageException
     * @throws InvalidCertificateException
     */
    public function isValid($purpose = self::CLIENT, $hideExceptions = true)
    {
        if (!Config::getInstance()->read('certificate.validate')) return true;
        if (!$this->verifyCertificateDate()) {
            if ($hideExceptions) return false;
            $ext = $this->isPrivate ? 'pem' : 'crt';
            throw new InvalidCertificateException(
                "Certificate {$this->name}.$ext is not valid for this date.");
        }
        if (!$this->verifyCertificateSignature()) {
            if ($hideExceptions) return false;
            throw new InvalidCertificateException(
                "Certificate {$this->name}.crt not signed by known S3C3 root.");
        }
        if (!$this->verifyCertificateUse($purpose)) {
            if ($hideExceptions) return false;
            switch ($purpose) {
                case self::CLIENT: $use = 'Client'; break;
                case self::SERVER: $use = 'Server'; break;
                case self::CRL_SIGN: $use = 'CRL Signing'; break;
                case self::CA: $use = 'Certificate Authority'; break;
            }
            throw new InvalidUsageException(
                "Certificate {$this->name}.crt not valid for use as $use Certificate.");
        }
        return true;
    }

    /**
     * Check a certificate name against our configured scheme
     *
     * @param string name to check
     * @param int    purpose
     * @return void
     * @throws SchemeNameException
     */
    protected function checkName($name, $purpose)
    {
        switch($purpose) {
            case self::CLIENT:
                $clients = Config::getInstance()->read('scheme.clients');
                if ($clients != 'all' && !in_array($name, $clients)) {
                    throw new SchemeNameException(
                        "Invalid client name $name");
                }
                break;
            case self::SERVER:
                $listeners = Config::getInstance()->read('scheme.listeners');
                if (!array_key_exists($name, $listeners)) {
                    throw new SchemeNameException(
                        "Invalid listener name $name");
                }
                break;
            case self::CA:
            case self::CRL_SIGN:
                if ($name != str_replace('.crt', '', basename(self::$root))) {
                    throw new SchemeNameException(
                        "Invalid root certificate name $name");
                }
                break;
        }
    }

    /**
     * Find a certificate by name
     *
     * @param string name
     * @param boolean isPrivate - if true looks for private key rather than public
     * @return string certificate or null
     */
    protected function findCert($name)
    {
        if (preg_match('/(_root|_ca|.ca).crt/', $name) || $name == 'root') return self::getRoot(false);
        $certstr = null;
        $certarray = $this->isPrivate ? self::$keys : self::$certs;
        foreach($certarray as $cert) {
            if (basename($cert) == $name . '.crt') {
                $certstr = file_get_contents($cert);
                break;
            }
        }
        return $certstr;
    }

    /**
     * Verify that a cert is currently valid by date
     *
     * @param string date defaults to now
     * @return boolean
     */
    protected final function verifyCertificateDate($date = null)
    {
        $time = (is_null($date)) ? time() : strtotime($date);
        $x509 = new File_X509();
        $cert = $x509->loadX509($this->certificate);
        return $x509->validateDate($time);
    }

    /**
     * Verify that a cert is signed by our known root ca
     *
     * @return boolean
     */
    protected final function verifyCertificateSignature()
    {
        $x509 = new File_X509();
        $x509->loadCA(self::getRoot());
        $cert = $x509->loadX509($this->certificate);
        return $x509->validateSignature(FILE_X509_VALIDATE_SIGNATURE_BY_CA);
    }

    /**
     * Verify that a cert is valid for the selected purpose
     *
     * @param int purpose (self::SERVER, self::CLIENT, self::CRL_SIGN, self::CA)
     * @param string certificate
     * @return boolean
     */
    protected final function verifyCertificateUse($purpose = self::CLIENT)
    {
        $ver = X509_PURPOSE_SSL_CLIENT;
        switch ($purpose) {
            case self::SERVER: $ver = X509_PURPOSE_SSL_SERVER; break;
            case self::CA:
            case self::CRL_SIGN: $ver = X509_PURPOSE_CRL_SIGN; break;
        }
        $x509_cert = openssl_x509_read($this->certificate);
        $ret = openssl_x509_checkpurpose($x509_cert, $ver, array(self::$root));
        return $ret;
    }

}
