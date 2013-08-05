<?php
/**
 * S3C3 LocalClient handles outgoing requests to the local listener.
 *
 * This class handles outgoing requests to the local listener. It does not use any
 * tokens or encryption and the local listener will not respond to any requests
 * that come from an ip other than 127.0.0.1 or ::1
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage client
 */
namespace s3c3\client;

use \s3c3\conf\Config;
use \s3c3\core\Token;
use \s3c3\core\crypto\Cert;
use \s3c3\core\crypto\Crypto;
use \s3c3\except\RuntimeException;
use \s3c3\except\SecurityViolationException;

/**
 * class Client
 *
 * @since version 1.0
 */
class LocalClient extends Client
{
    
    /**
     * \HttpRequest
     * @var request
     * @access private
     */
    private $request;

    /**
     * string url
     * @var url
     * @access private
     */
    private $url;

    /**
     * array get params
     * @var params
     * @access private
     */
    private $params = false;

    /**
     * array post data
     * @var post
     * @access private
     */
    private $post;

    /**
     * string action
     * @var action
     * @access private
     */
    private $action;

    /**
     * string client name
     * @var clientName
     * @access private
     */
    private $clientName;

    /**
     * string local "token" from s3c3_local_listener.internal_token
     * @var localToken
     * @access private
     */
    private $localToken;

    /**
     * Constructor
     * Create a new Client
     */
    public function __construct()
    {
        $conf = Config::getInstance();
        $this->url = $conf->read('s3c3_local_listener.endpoint');
        $this->localToken = $conf->read('s3c3_local_listener.internal_token');
        $this->clientName = $conf->read('env.local_scheme_name');
        $this->request = new \HttpRequest($this->url);
        $this->request->setOptions(array('redirect' => 10));
    }
    
    /**
     * Show all the listeners in the scheme that we know about
     *
     * @return array
     */
    public static function showListeners()
    {
        $serverlist = Config::getInstance()->read('scheme.listeners');
        return array(
            'status'  => 200,
            'message' => 'OK',
            'data'    => json_encode(array('listeners' => array_keys($serverlist)))
            );
    }
    
    /**
     * Does the actual sending and parsing of the response. Since these requests
     * are not encrypted this is much simpler than the parent class version
     *
     * @param string action
     * @param array params (GET query parameters)
     * @param array post (POST parameters)
     */
    private function send($action, $params = null, $post = null)
    {
        if (is_null($params)) $params = array();
        $params['action'] = $action;
        $params['internal_token'] = $this->localToken;
        $this->request->setQueryData($params);
        if (!empty($post)) {
            $this->request->setPostFields($post);
            $this->request->setMethod(HTTP_METH_POST);
        } else {
            $this->request->setPostFields(null);
            $this->request->setMethod(HTTP_METH_GET);
        }
        $this->request->send();
        $retval = array(
            'status'  => $this->request->getResponseCode(),
            'message' => $this->request->getResponseStatus(),
            'data'    => $this->request->getResponseBody()
            );
        return $retval;
    }
    
    /**
     * Add a server or client certificate to the local scheme
     *
     * @param string name (scheme name)
     * @param string type (server or client)
     * @param string cert the actual certificate
     * @return array (data will be json_encoded)
     */
    public function addCertificate($name, $type, $cert)
    {
        if (strtoupper($type) != 'SERVER' && strtoupper($type) != 'CLIENT') {
                throw new RuntimeError('Certificate type ' . $type . ' not allowed');
        }
        $post = array (
            'name' => $name,
            'type' => $type,
            'certificate' => $cert
            );
        return $this->send('addcert', null, $post);
    }
    
    /**
     * Remove a server or client certificate from the local scheme
     *
     * @param string name (scheme name)
     * @return array (data will be json_encoded)
     */
    public function removeCertificate($name)
    {
        $params = array('name' => $name);
        return $this->send('removecert', $params);
    }
    
    /**
     * Verify that the log dir is writable by the web process
     *
     * @return array (data will be json_encoded)
     */
    public function verifyLogDirectory()
    {
        return $this->send('verifylogdir');
    }
    
    /**
     * Verify that the certificate store is writable by the web process
     *
     * @return array (data will be json_encoded)
     */
    public function verifyCertificateStore()
    {
        return $this->send('verifycertdir');
    }
    
    /**
     * Get a list of all public key certificates in the local store,
     * including client certs
     *
     * @return array (data will be json_encoded)
     */
    public function getKnownCertificates()
    {
        return $this->send('list');
    }
    
    /**
     * Get the public key certificate for a specific server or client
     *
     * @param string target the scheme name to look up
     * @return array (data will be json_encoded)
     */
    public function getCertificateFor($target)
    {
        return $this->send('cert', array('target' => $target));
    }
    
}
