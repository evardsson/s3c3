<?php
/**
 * Example shim for tieing a process to the Client.
 *
 * This example (test) shim creates its own data to send to the listener on the
 * other end of the connection. In a real scenario the data to be sent would be
 * passed from an outside application to the shim, or an outside application would
 * take advantage of the S3C3 classes in the same way this does.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage example
 */
namespace s3c3\example;

use \s3c3\client\Client;
use \s3c3\client\LocalClient;


/**
 * class ClientShim
 *
 * @since version 1.0
 */
class ClientShim
{
    /**
     * mixed data
     * @var data
     * @access private
     */
    private $data;
    
    /**
     * string target (remote listener) name
     * @var target
     * @access private
     */
    private $target;
    
    /**
     * Client object
     * @var client
     * @access private
     */
    private $client;
    
    /**
     * Constructor
     * Create a new ClientShim
     *
     * @param array data
     * @param object Client object
     */
    public function __construct($data, $target)
    {
        $this->data = $data;
        $this->target = $target;
        $this->client = new Client();
    }
    
    /**
     * Send the request and get back the result
     * Normally the determination to send more or not would be made by the calling
     * class, but in this case we are going to simulate for the example by looping
     * through our data array
     *
     * @return array
     */
    public function send()
    {
        $results = array();
        $max = count($this->data) - 1;
        for ($i = 0; $i <= $max; $i++) {
            $more = $i < $max;
            $results[] = $this->client->sendData($this->data[$i], $this->target, $more);
        }
        return $results;
    }
    
    /**
     * Generate a request
     *
     * @return void
     */
    protected static function generate($error = false)
    {
        $oots = 'Roy Greenhilt|Durkon Thundershield|Vaarsuvius|Haley Starshine|'.
            'Elan|Belkar Bitterleaf|Xykon|Redcloak|Monster in the Darkness|Tsukiko|'.
            'Nale|Thog|Sabine|Hilgya Firehelm|Zz\'dtri|Yikyik|Pompey|Leeky Windstaff|'.
            'Yokyok|Yukyuk|Samantha|Daimyo Kubota|Qarr|Therkla|Miko Miyazaki|Hinjo|'.
            'Lien|O-Chul|Shojo|Mr. Scruffy|Thanh|Eric Greenhilt|Eugene Greenhilt|'.
            'Horace Greenhilt|Julia Greenhilt|Sara Greenhilt|Soon Kim|Lirian|Dorukan|'.
            'Girard Draketooth|Serini Toormuck|Kraagor|Loki|Thor|Banjo the Clown|'.
            'The Snarl|The Dark One|Guildmaster Bozzok|Crystal|Hank|'.
            'Hieronymus Grubwiggler|Old Blind Pete|The Loki Cleric|The Empress of Blood|'.
            'General Tarquin|Minister Malack|Gannji|Enor|General Chang|Kazumi Kato|'.
            'Daigo|Sangwaan|Blackwing|Celia|Julio Scoundrél|Mr. Jones|Mr. Phil Rodriguez|Rich Burlew';
        $names = explode('|', $oots);
        $namelen = count($names) -1;
        $name = $names[mt_rand(0, $namelen)];
        $arr = array(
            'dividend' => mt_rand(0, 1000),
            'divisor'  => mt_rand(1, 100),
            'name'     => $name,
            'password' => strrev($name)
            );
        if ($error) {
            switch (mt_rand(0, 9)) {
                case 0:
                case 2:
                    unset($arr['dividend']); break;
                case 1:
                case 3:
                    unset($arr['name']); break;
                case 4:
                case 6:
                case 8:
                    $arr['divisor'] = 0; break;
                case 5:
                case 7:
                case 9:
                    $arr['password'] .= 'X';break;
            }
        }
        return $arr;
    }
    
    /**
     * Return a list of listeners
     *
     * @return array
     */
    public static function getListeners()
    {
        $client = new LocalClient();
        $ret = $client->showListeners();
        $data = json_decode($ret['data'], true);
        $certs = $data['listeners'];
        return $certs;
    }
    
    /**
     * Run requests in sequential fashion
     *
     * @param string target name
     * @param int requests - number of requests to make
     * @param boolean errors - introduce errors
     * @return array of responses
     */
    public static function run($target, $requests = 1, $errors = false)
    {
        $data = array();
        if ($errors) {
            if ($requests > 1) {
                $data[] = self::generate();
                $data[] = self::generate(true);
                $requests -= 2;
            } else {
                $data[] = self::generate(true);
                $requests = 0;
            }
        }
        for ($i = 0; $i < $requests; $i++) {
            $err = false;
            if ($errors) $err = mt_rand(0, 9) % 2 === 0;
            $data[] = self::generate($err);
        }
        $shim = new self($data, $target);
        return $shim->send();
    }

}
