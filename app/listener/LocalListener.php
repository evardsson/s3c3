<?php
/**
 * S3C3 LocalListener handles only local requests.
 *
 * For requests sent by the bootstrap module, or the local client module (to obtain
 * certificates, etc) this class takes over from the Listener. This class does NOT
 * utilize encryption, and will only respond to requests that come from the local
 * machine as determined by ip = 127.0.0.1 or ip = ::1
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage listener
 */
namespace s3c3\listener;

use s3c3\conf\Config;
use s3c3\core\crypto\Cert;
use s3c3\bootstrap\CertHandler;

/**
 * class LocalListener
 *
 * @since version 1.0
 */
class LocalListener extends Listener
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
     * int http status
     * @var status
     * @access private
     */
    protected $status = 200;

    /**
     * string local "token" from s3c3_local_listener.internal_token
     * @var localToken
     * @access private
     */
    private $localToken;

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
        if (empty($this->get) || ($this->remoteip != '127.0.0.1' 
            && $this->remoteip != '::1'))
        {
            $this->respond(400);
        }
        $this->localToken =
            Config::getInstance()->read('s3c3_local_listener.internal_token');
    }
    
    /**
     * This creates the Listener object, processes the request and returns the
     * data based on the request
     */
    public static function handleRequest()
    {
        $l = new self();
        $l->processRequest();
        $message = $l->finalizeResponse();
        $l->respond($message, 'application/json');
    }
    
    /**
     * Do the actual request processing
     *
     * @return string json
     */
    private function processRequest()
    {
        if (empty($this->get['action'])) {
            $this->status  = 400;
            $this->outdata = array('error' => 'Parameter \'action\' not set');
            return;
        }
        if (empty($this->get['internal_token'])
            || $this->get['internal_token'] !== $this->localToken)
        {
            $this->status = 400;
            $this->outdata = array('error' => 'internal_token mismatch');
            return;
        }
        switch ($this->get['action']) {
            case 'list':
                $this->getCertList();
                break;
            case 'cert':
                if (empty($this->get['target'])) {
                    $this->status = 400;
                    $this->outdata = array('error' => 'Target for action \'cert\' not set');
                } else {
                    $this->getCert($this->get['target']);
                }
                break;
            case 'addcert':
                $name = !empty($this->post['name']) ? $this->post['name'] : null;
                $type = !empty($this->post['type']) ? $this->post['type'] : null;
                $cert = !empty($this->post['certificate']) ? $this->post['certificate'] : null;
                if (strtoupper($type) == 'SERVER') $purpose = Cert::SERVER;
                elseif (strtoupper($type) == 'CLIENT') $purpose = Cert::CLIENT;
                if (empty($name) || empty($purpose) || empty($cert)) {
                    $this->status  = 400;
                    $this->outdata = array('error' => "Method 'addcert' missing arguments");
                } else {
                    $this->addCert($name, $purpose, $cert);
                }
                break;
            case 'removecert':
                if (empty($this->get['name'])) {
                    $this->status  = 400;
                    $this->outdata = array('error' => "Method 'addcert' missing arguments");
                }
                $this->removeCert($this->get['name']);
                break;
            case 'verifylogdir':
                $this->verifyDir('LOG');
                break;
            case 'verifycertdir':
                $this->verifyDir('CERT');
                break;
            case 'addcrl':
                $this->status  = 400;
                $this->outdata = array('error' => "Method '{$this->get['action']}' not yet implemented");
                break;
            default:
                $this->status  = 400;
                $this->outdata = array('error' => "Unknown action '{$this->get['action']}'");
                break;
        }
    }
    
    /**
     * Add a certificiate
     *
     * @param string name
     * @param string purpose
     * @param string certstring
     */
    private function addCert($name, $purpose, $certstring)
    {
        try {
            $ret = CertHandler::loadCertificate($name, $purpose, $certstring);
            $this->outdata = array('result' => $ret ? 'certificate added' : 'certificate not added');
            if (!$ret) $this->status = 400;
        } catch (\Exception $e) {
            $this->status = 400;
            $this->outdata = array('error' => (string)$e);
        }
    }
    
    /**
     * Remove a certificiate
     *
     * @param string name
     */
    private function removeCert($name)
    {
        $res = CertHandler::removeFromStore($name);
        $this->outdata = array('result' => $res ? 'success' : 'nothing removed');
        if (!$res) $this->status = 400;
    }
    
    /**
     * Verify a directory
     *
     * @param string vdir
     */
    private function verifyDir($vdir)
    {
        if ($vdir == 'LOG') {
            $dir = Config::getInstance()->read('logging.file.dir');
        } else {
            Cert::checkFilePermissions();
            $dir = Config::getInstance()->read('certificate.store');
        }
        $res = file_exists($dir) && is_writable($dir);
        $this->outdata = array(
            'results' => $res ? "$vdir directory ok ($dir)" : "$vdir directory does not exist or is not writable! ($dir)" 
            );
        if (!$res) $this->status = 400;
    }
    
    /**
     * Get a list of known certificiates
     */
    private function getCertList()
    {
        Cert::scanStore();
        $this->outdata = array('known_certificates' => Cert::getKnownCertificates());
    }
    
    /**
     * Get a certificiate
     *
     * @param string target name
     */
    private function getCert($target)
    {
        $this->outdata = array(
            'name'        => $target,
            'certificate' => Cert::getCertificateFromStore($target, Cert::SERVER)
            );
    }
    
    /**
     * Prepare the response for sending back to client
     */
    private function finalizeResponse()
    {
        return json_encode($this->outdata);
    }
    
}
