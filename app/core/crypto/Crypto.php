<?php
/**
 * Crypto class encapsulates all encryption/decryption for S3C3.
 *
 * The Crypto class is the one location for all encryption, decryption and message
 * hashing/hash verification for all of S3C3. Note that any class that wishes to
 * extend this class (to use, for example, GnuPG rather than OpenSSL) must
 * implement all the methods in this class or it will fail.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage core
 */
namespace s3c3\core\crypto;

/**
 * class Crypto
 *
 * @since version 1.0
 */
class Crypto
{
    /**
     * string local key
     * @var localKey
     * @access private
     */
    private $localKey;
    
    /**
     * string remote certificate
     * @var remoteKey
     * @access private
     */
    private $remoteKey;
    
    /**
     * boolean flag for local is client
     * @var localIsClient
     * @access private
     */
    private $localIsClient;

    /**
     * Constructor
     * Create a new Crypto object. 
     *
     * @param string  remoteName the name of the remote server with whom we will be
     *     communicating
     * @param boolean localIsClient Set to true if this server is the client, by
     *     default this server is the listener
     */
    public function __construct($remoteName, $localIsClient = false)
    {
        $this->localIsClient = $localIsClient;
        if ($this->localIsClient) $this->localKey = Cert::getLocalClientKey();
        else $this->localKey = Cert::getLocalServerKey();
        if (!is_null($remoteName))
            $this->setKeys($remoteName);
    }
    
    /**
     * Set the keys for this Crypto object
     *
     * @param string  remoteName the name of the remote server with whom we will be
     *     communicating
     */
    public function setKeys($remoteName)
    {
        $purpose = $this->localIsClient ? Cert::SERVER : Cert::CLIENT;
        $this->remoteKey = Cert::getCertificateFromStore($remoteName, $purpose, true, false);
    }
    
    /**
     * Get the local (private) key
     *
     * @return object Cert object
     */
    public function getLocalKey()
    {
        return $this->localKey;
    }

    /**
     * Get the remote (public) key
     *
     * @return object Cert object
     */
    public function getRemoteKey()
    {
        return $this->remoteKey;
    }

    /**
     * Encrypt a message with the local (private) key
     *
     * @param string message
     * @return string encrypted (base64 encoded)
     */
    public function encryptLocal($message)
    {
        $pkey = openssl_pkey_get_private($this->localKey);
        $crypted = null;
        openssl_private_encrypt($message, $crypted, $pkey);
        return base64_encode($crypted);
    }

    /**
     * Decrypt a message with the local (private) key
     *
     * @param string message encrypted
     * @return string plaintext
     */
    public function decryptLocal($message)
    {
        $pkey = openssl_pkey_get_private($this->localKey);
        $plain = null;
        openssl_private_decrypt(base64_decode($message), $plain, $pkey);
        return $plain;
    }

    /**
     * Encrypt a message with the remote (public) key
     *
     * @param string message
     * @return string encrypted (base64 encoded)
     */
    public function encryptRemote($message)
    {
        $pkey = openssl_pkey_get_public($this->remoteKey);
        $crypted = null;
        openssl_public_encrypt($message, $crypted, $pkey);
        return base64_encode($crypted);
    }

    /**
     * Decrypt a message with the remote (public) key
     *
     * @param string message encrypted
     * @return string plaintext
     */
    public function decryptRemote($message)
    {
        $pkey = openssl_pkey_get_public($this->remoteKey);
        $plain = null;
        openssl_public_decrypt(base64_decode($message), $plain, $pkey);
        return $plain;
    }

    /**
     * Create a hash for a message and token and encrypt it
     *
     * @param string message
     * @param string token
     * @return string encrypted hash (base64 encoded)
     */
    public function createHash($message, $token)
    {
        $hash = hash('sha256', $message.$token);
        return $this->encryptLocal($hash);
    }

    /**
     * Check a hash for a message and token
     *
     * @param string hash to check
     * @param string message
     * @param string token
     * @return boolean matches
     */
    public function verifyHash($hash, $message, $token)
    {
        $myhash = hash('sha256', $message.$token);
        return $this->decryptRemote($hash) === $myhash;
    }

    /**
     * Generate a random payload for use in token begin requests
     *
     * @return string
     */
    public function generateRandomPayload()
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $clen  = strlen($chars) - 1;
        $outlen = mt_rand(113, 227);
        $outstr = '';
        while(strlen($outstr) < $outlen) {
            $outstr .= $chars{mt_rand(0, $clen)};
        }
        return $outstr;
    }
}
