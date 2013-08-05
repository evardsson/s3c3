<?php
/**
 * S3C3 Listener handles incoming requests.
 *
 * This class handles incoming requests, verifies the authenticity of the sender
 * and passes the unencrypted data on to the proper callable. The results sent back
 * by the callable are then encrypted and sent back to the requesting sender.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage listener
 */
namespace s3c3\listener;

use \s3c3\conf\Config;
use \s3c3\core\Token;
use \s3c3\core\model\Context;
use \s3c3\core\crypto\Crypto;

/**
 * class Listener
 *
 * @since version 1.0
 */
class Listener
{
    
    /**
     * string remote ip
     * @var remoteIp
     * @access private
     */
    private $remoteip;
    
    /**
     * array post data
     * @var post
     * @access private
     */
    private $post;
    
    /**
     * array get data
     * @var get
     * @access private
     */
    private $get;
    
    /**
     * Context object
     * @var context
     * @access private
     */
    private $context;
    
    /**
     * mixed data to return
     * @var outdata
     * @access private
     */
    private $outdata;
    
    /**
     * mixed data coming in
     * @var indata
     * @access private
     */
    private $indata;
    
    /**
     * Crypto object
     * @var crypto
     * @access private
     */
    private $crypto;
    
    /**
     * Token object
     * @var token
     * @access private
     */
    private $token;
    
    /**
     * int http status
     * @var status
     * @access private
     */
    protected $status = 200;
    
    /**
     * array http status messages
     * @var status_messages
     * @access private
     */
    protected $status_messages = array(
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-Status',
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy (Unused)',
        307 => 'Temporary Redirect',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required (Unused)',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Timeout',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Long',
        415 => 'Unsupported Media Type',
        416 => 'Requested Range Not Satisfiable',
        417 => 'Expectation Failed',
        418 => ' I\'m a teapot',
        420 => 'Enhance your calm',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Method Failure',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        429 => 'Too Many Requests',
        449 => 'Retry With',
        450 => 'Blocked by Windows Parental Controls',
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout',
        505 => 'HTTP Version Not Supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        509 => 'Bandwidth Limit Exceeded',
        510 => 'Not Extended'
        );

    /**
     * Constructor
     * Create a new Listener
     */
    private function __construct()
    {
        $this->post = $_POST;
        $this->get = $_GET;
        $this->remoteip = (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) ?
            $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
        if ($_SERVER['REQUEST_METHOD'] == 'GET' && $this->remoteip != '127.0.0.1'
            && $this->remoteip != '::1')
        {
            $this->status = 405;
            $this->respond(); 
        } elseif ($this->remoteip == '127.0.0.1' || $this->remoteip == '::1') {
            LocalListener::handleRequest();
        }
    }
    
    /**
     * This creates the Listener object, processes the request and returns the
     * data based on the request
     */
    public static function handleRequest($callout)
    {
        $l = new self($callout);
        $l->processRequest();
        $l->callOut($callout);
        $message = $l->finalizeResponse();
        $l->respond($message, 'application/json');
    }
    
    /**
     * Process the incoming request
     */
    private function processRequest()
    {
        if (empty($this->post['token']) ||
            empty($this->post['payload']) ||
            empty($this->post['hash']))
        {
            $this->status = 400;
            $this->respond();
        }
        // re:timing attacks and the pseudo-random usleep -
        // this is NOT the proper way to do it, but this should muck up attempts at
        // timing attacks nonetheless
        $this->crypto = new Crypto();
        try { // check the token first
            $tokstr = $this->crypto->decryptLocal($this->post['token']);
            $this->token = Token::parseRequest($tokstr, true);
            $this->crypto->setKeys($this->token->getClient());
            if ($this->token->getType() !== Token::TBEGIN) {
                $context = Context::findContextByToken(
                    $this->token->getTokenString(), $this->token->getClient());
            }
        } catch (\Exception $e) {die(print_r($tokenArr, true) . "$tokstr\n$e");
            usleep(mt_rand(6000, 9000));
            $this->status = 400;
            $this->respond();
        }
        try { // now check the hash
            $hashOk = $this->crypto->verifyHash($this->post['hash'],
                $this->post['payload'], $this->post['token']);
        } catch (\Exception $e) {
            usleep(mt_rand(3000, 6000));
            $this->status = 400;
            $this->respond();
        }
        if (!$hashOk) {
            usleep(mt_rand(3000, 6000));
            $this->status = 400;
            $this->respond();
        }
        if ($this->token->getType() != Token::TBEGIN) {
            $this->indata = $this->crypto->decryptLocal($_POST['payload']);
        }
    }
    
    /**
     * Call out the callable requested by index.php
     *
     * @param string callout
     */
    private function callOut($callout)
    {
        if ($this->token->getType() != Token::TBEGIN) {
            try {
                $ret = call_user_func_array($callout, array($this->indata));
            } catch (\Exception $e) {
                $ret = array('error' => (string)$e);
            }
        } else {
            $this->outdata = $this->crypto->generateRandomPayload();
        }
        $this->outdata = $ret;
    }
    
    /**
     * Prepare the response for sending back to client
     */
    private function finalizeResponse()
    {
        $response = array();
        $tresponse = $this->token->createResponse($this->token->getType());
        if ($this->token->getType() != Token::TEND) 
            $context = Context::createContextFor($this->token);
        $response['token'] = $this->crypto->encryptRemote($tresponse);
        $response['payload'] = $this->crypto->encryptRemote($this->outdata);
        $response['hash'] = $this->crypto->createHash($response['payload'],$response['token']);
        return json_encode($response);
    }
    
    /**
     * Send the response to the client
     *
     * @param string message
     * @param string contentType
     */
    protected function respond($message = null, $contentType = null)
    {
        header('HTTP/1.1 '.$this->status.' '.$this->status_messages[$this->status]);
        if (!empty($contentType)) header('Content-type: ' . $contentType);
        if (!empty($message)) print $message;
        exit;
    }
    
}
