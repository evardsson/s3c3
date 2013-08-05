<?php
/**
 * S3C3 Client handles outgoing requests.
 *
 * This class handles outgoing requests, gets a single use token for each request
 * and passes the encrypted data on to the listener. The response from the listener
 * is then decrypted and passed back to the caller.
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
class Client
{
    
    /**
     * HttpRequest object
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
     * array request parameters
     * @var params
     * @access private
     */
    private $params;
    
    /**
     * Crypto object
     * @var crypto
     * @access private
     */
    private $crypto;
    
    /**
     * array list of listeners/urls
     * @var serverlist
     * @access private
     */
    private $serverlist;
    
    /**
     * Token object
     * @var token
     * @access private
     */
    private $token;
    
    /**
     * string client name
     * @var clientName
     * @access private
     */
    private $clientName;

    /**
     * string listener name
     * @var listener
     * @access private
     */
    private $listener;

    /**
     * string call
     * @var call
     * @access private
     */
    private $call = 'default';
    
    /**
     * Constructor
     * Create a new Client
     */
    public function __construct()
    {
        $this->crypto = new Crypto(null, true);
        $this->serverlist = Config::getInstance()->read('scheme.listeners');
        $this->clientName = Config::getInstance()->read('env.local_scheme_name');
    }
    
    /**
     * Send data
     *
     * @param mixed data to send
     * @param string listener name
     * @param boolean more (if there are more requests to follow to the same listener)
     */
    public function sendData($data, $listener, $more = false)
    {
        if (!$this->isNameValid($listener)) {
            throw new RuntimeException("$listener is not a valid listener name for this scheme");
        }
        $this->listener = $listener;
        $this->crypto->setKeys($this->listener);
        $this->setUrl($this->listener);
        $this->request = new \HttpRequest($this->url);
        $this->request->setMethod(HTTP_METH_POST);
        $this->request->setOptions(array('connecttimeout'=>30, 'timeout'=>300));
        if (empty($this->token) || strtotime($this->token->getExpires()) < time()) {
            $this->beginTokenRequest($this->listener);
        }
        $ttype = $more ? Token::TCONTINUE : Token::TEND;
        $this->setRequest($data, $ttype);
        $this->request->send();
        return $this->parseResponse();
    }
    
    /**
     * Set the call parameter
     *
     * @param string call parameter
     */
    public function setCall($call)
    {
        $this->call = $call;
    }
    
    /**
     * Show all the listeners in the scheme that we know about
     *
     * @return array
     */
    public static function showListeners()
    {
        $serverlist = Config::getInstance()->read('scheme.listeners');
        return array_keys($serverlist);
    }
    
    /**
     * Parse the response
     *
     * @param boolean isBegin
     */
    private function parseResponse($isBegin = false)
    {
        $arr = json_decode($this->request->getResponseBody(), true);
        if ($this->crypto->verifyHash($arr['hash'], $arr['payload'], $arr['token'])) {
            $tokstr = $this->crypto->decryptLocal($arr['token']);
            $fh = fopen('/Users/sjan/Dropbox/Capstone/s3c3/dumpout', 'a');
            $this->token = Token::parseResponse($tokstr, $this->clientName);
            fwrite($fh, "tokbak= $tokstr\n\n".var_export($this->token, true)."\n\n\n");fclose($fh);
            if ($isBegin) {
                return;
            } else {
                $retval = array(
                    'status'  => $this->request->getResponseCode(),
                    'message' => $this->request->getResponseStatus(),
                    'data'    => $this->crypto->decryptLocal($arr['payload'])
                    );
                return $retval;
            }
        } else {
            var_dump($this->request->getResponseBody());
            var_dump($this->request->getResponseCode());
            var_dump($this->crypto->decryptRemote($arr['hash']));
            print_r($arr);exit;
            throw new SecurityViolationException("Invalid hash returned from $this->listener");
        }
    }
    
    /**
     * Set the request data
     *
     * @param mixed data
     * @param string type
     */
    private function setRequest($data, $type)
    {
        if (is_array($data) || is_object($data)) $data = json_encode($data);
        $tokst = $this->token->createRequest($type);
        $token = $this->crypto->encryptRemote($tokst);
        $payload = $this->crypto->encryptRemote($data);
        $hash = $this->crypto->createHash($payload, $token);
        $params = array(
            'token'   => $token,
            'payload' => $payload,
            'hash'    => $hash
            );
        if ($this->call != 'default') $params['call'] = $this->call;
        $this->request->setPostFields($params);
    }
    
    /**
     * Do a token "begin" request
     *
     * @param string listner name
     */
    private function beginTokenRequest($listener)
    {
        $this->token = new Token($this->clientName);
        $payload = $this->crypto->generateRandomPayload();
        $this->setRequest($payload, Token::TBEGIN);
        $this->request->send();
        $this->parseResponse(true);
    }
    
    /**
     * Set the url based on the listener name
     *
     * @param string servername
     */
    private function setUrl($servername)
    {
        $url = $this->serverlist[$servername];
        if (substr($url, -1) !== '/' && substr($url, -4) !== '.php') $url .= '/';
        $this->url = $url;
    }
    
    /**
     * Check that a listener name is valid for this scheme
     *
     * @param string name to check
     * @return boolean
     */
    private function isNameValid($name)
    {
        return array_key_exists($name, $this->serverlist);
    }

}
