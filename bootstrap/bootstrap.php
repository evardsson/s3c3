<?php                                    
/**
 * Command line script for bootstrap
 *
 * This file attempts to ease installation/maintenance of S3C3. 
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage bootstrap
 */
namespace s3c3\bootstrap;
if (!defined('IN_BOOTSTRAP')) define('IN_BOOTSTRAP', 1);
require_once __DIR__ . '/_init_bootstrap.php';

use \evardsson\ansi\ANSI;
use \Symfony\Component\Yaml\Yaml;
use \s3c3\conf\Config;
use \s3c3\core\db\Database as DB;
use \s3c3\client\Client;
use \s3c3\client\LocalClient;

/**
 * Bootstrap class handles installation/maintenance assistance
 * @since version 1.0
 */
class Bootstrap
{
    /**
     * boolean use color?
     * @var color
     * @access private
     */
    private $color;

    /**
     * string config file
     * @var conffile
     * @access private
     */
    private $conffile;

    /**
     * boolean run on autopilot?
     * @var auto
     * @access private
     */
    private $auto;

    /**
     * array ANSI class objects
     * @var colors
     * @access private
     */
    private $colors;

    /**
     * boolean is this a windows machine?
     * @var isWin
     * @access private
     */
    private $isWin;

    /**
     * array of configuration items
     * @var config
     * @access private
     */
    private $config;

    /**
     * Create a new Bootstrap
     *
     * @param boolean color
     * @param string conffile
     * @param boolean auto
     */
    public function __construct($color = false, $conffile = null, $auto = false)
    {
        $this->color = $color;
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->color = false;
            $this->isWin = true;
        }
        $this->conffile = $conffile;
        if (!empty($this->conffile)) Config::getInstance()->writeFromYaml($conffile);
        $this->auto = $auto;
        $this->config = Config::getInstance()->read();
        if ($this->color) $this->setupColors();
    }

    /**
     * Run the bootstrapper
     */
    public function run()
    {
        $this->clearScreen();
        $this->pline(str_repeat('-',80), 'info');
        $this->pline('S3C3 Configuration Bootstrap', 'info', 80);
        $this->pline(str_repeat('-',80), 'info');
        if (file_exists(S3C3_CONF . '/conf.yml') && empty($this->conffile)) {
            $this->menu = $this->promptYN('Conf.yml already exists, would you like the update menu?','Y');
        }
        if ($this->auto) $this->auto();
        else if ($this->menu) $this->menu();
        else $this->wizard();
        exit;
    }

    /**
     * Bootstrap menu mode
     */
    private function menu()
    {
        $this->clearScreen();
        $this->pline(str_repeat('-',80), 'info');
        $this->pline('S3C3 Configuration Bootstrap Menu', 'info', 80);
        $this->pline(str_repeat('-',80), 'info');
        echo PHP_EOL;
        $menu = array(
            '1:  Environment',
            '2:  Database',
            '3:  Certificates',
            '4:  Local Listener',
            '5:  Logging',
            '6:  Tokens',
            '7:  Scheme',
            '8:  Add Certificate', // or CRL',
            '9:  Remove Certificate',
            '10: Verify Certificate Store',
            '11: Verify Logging Directory',
            'W:  Write Configs',
            'X:  Exit'
            );
        foreach ($menu as $item) $this->pline($item ,'info' , 80, STR_PAD_RIGHT);
        $choice = $this->prompt("Please select an action from the menu above", null,
            array(1,2,3,4,5,6,7,8,9,10,11,'W','w','X','x'));
        switch ($choice) {
            case 1:
                $keyspace = 'env';
                break;
            case 2:
                $keyspace = 'database';
                break;
            case 3:
                $keyspace = 'certificate';
                break;
            case 4:
                $keyspace = 's3c3_local_listener';
                break;
            case 5:
                $keyspace = 'logging';
                break;
            case 6:
                $keyspace = 'token';
                break;
            case 7:
                $keyspace = 'scheme';
                break;
            case 8:
                $this->addCertificate();
                $this->menu();
                break;
            case 9:
                $this->removeCertificate();
                $this->menu();
                break;
            case 10:
                $this->verifyCertificateStore();
                $this->menu();
                break;
            case 11:
                $this->verifyLogDirectory();
                $this->menu();
                break;
            case 'W':
            case 'w':
                $this->prepareDB();
                $this->writeConfigs();
            case 'X':
            case 'x':
                echo PHP_EOL;
                exit;
            default:
                $this->menu();
        }
        $methname = "setUp".ucfirst($keyspace);
        $edit = true;
        while($edit) {
            $output = Yaml::dump(array($keyspace => $this->config[$keyspace]), 6);
            $lines = explode(PHP_EOL, $output);
            foreach ($lines as $line) {
                $this->pline(str_pad($line, 80, ' ', STR_PAD_RIGHT), 'info');
            }
            $nok = $this->promptYN("Edit this section?",'Y');
            if ($nok) {
                $this->$methname();
            } else {
                $edit = false;
            }
        }
        $this->menu();
    }

    /**
     * Bootstrap autopilot mode
     */
    private function auto()
    {
        $this->pline(str_pad('Reading ' . $this->conffile, 80, ' ', STR_PAD_BOTH), 'info');
        $this->config = Yaml::parse($this->conffile);
        $this->prepareDB();
        $this->writeConfigs();
    }

    /**
     * Prepare the database
     */
    private function prepareDB()
    {
        $this->pline(str_pad('Building database tables', 80, ' ', STR_PAD_BOTH), 'info');
        try {
            if (DB::getPrefix() !== $this->config['database']['prefix']) {
                DB::updatePrefix($this->config['database']['prefix']);
            }
            DB::buildTables();
        } catch (\Exception $e) {
            $this->pline(str_pad($e->getMessage(), 80, ' ', STR_PAD_BOTH), 'error');
        }
    }

    /**
     * Write out the configs
     */
    private function writeConfigs()
    {
        if (file_exists(S3C3_CONF . '/conf.yml')) {
            $this->pline(str_pad('Backing up existing conf.yml', 80, ' ', STR_PAD_BOTH), 'info');
            copy(S3C3_CONF . '/conf.yml', S3C3_CONF . '/conf.yml.bak');
        }
        $output = Yaml::dump($this->config, 6);
        file_put_contents(S3C3_CONF . '/conf.yml', $output);
        if ($this->config['env']['config'] == 'db') {
            $confarray = array (
                'token'  => $this->config['token'],
                'scheme' => $this->config['scheme']
                );
        
            $this->storeToDB($confarray);
        }
    }

    /**
     * Bootstrap wizard mode
     */
    private function wizard()
    {
        // set up env:
        $this->subHead('Environment');
        $this->setUpEnv();
        $this->subHead('Database', true);
        $this->setUpDatabase();
        $this->subHead('Certificates', true);
        $this->setUpCertificate();
        $this->subHead('Local Listener', true);
        $this->setUpS3c3_local_listener();
        $this->subHead('Logging', true);
        $this->setUpLogging();
        $this->subHead('Tokens', true);
        $this->setUpToken();
        $this->subHead('Scheme', true);
        $this->setUpScheme();
        $this->subHead('Preparing to save', true);
        foreach ($this->config as $keyspace => $vals) {
            $methname = "setUp".ucfirst($keyspace);
            $ok = false;
            do {
                $output = Yaml::dump(array($keyspace => $vals), 6);
                $lines = explode(PHP_EOL, $output);
                foreach ($lines as $line) {
                    $this->pline(str_pad($line, 80, ' ', STR_PAD_RIGHT), 'info');
                }
                $nok = $this->promptYN("Does this section look correct?",'Y');
                if (!$nok) {
                    $this->$methname();
                } else {
                    $ok = true;
                }
            } while(!$ok);
        }
    }

    /**
     * Add a certificate
     */
    private function addCertificate()
    {
        $type = $this->prompt("Is this a (C)lient or (S)erver certificate?",
            'S', array('C','c','S','s'));
        if (strtoupper($type) == 'S') {
            $purpose = 'server';
            $name = $this->prompt("What is the scheme name for this certificate?",
                null, '/[a-z]$/', false, 'Server scheme names cannot end in a number');
        } else {
            $purpose = 'client';
            $name = $this->prompt("What is the scheme name for this certificate?",
                null, '/[0-9]$/', false, 'Client scheme names must end in a number');
        }
        $found = false;
        while (!$found) {
            $certfile = $this->prompt("Where is the certificate file?");
            if (file_exists($certfile) && is_readable($certfile)) {
                $certstr = file_get_contents($certfile);
                $found = true;
            } else {
                $this->pline($certfile . ' not found or not readable', 'error', 80, STR_PAD_RIGHT);
            }
        }
        if ($purpose == Cert::CLIENT && !in_array($name, $this->config['scheme']['clients']))
            $this->config['scheme']['clients'][] = $name;
        else if (!array_key_exists($name, $this->config['scheme']['listeners']))
            $this->config['scheme']['listeners'][$name] = '?';
        $this->updateConf('scheme');
        $client = new LocalClient();
        $ret = $client->addCertificate($name, $purpose, $certstr);
        $data = json_decode($ret['data'], true);
        if ($ret['status'] != 200) {
            $this->pline('Certificate not added. Server responded with: '.$ret['status'], 'warning', 80, STR_PAD_RIGHT);
            echo $ret['message'], PHP_EOL, $data['error'], PHP_EOL;
        } else {
            $this->pline('Certificate added.', 'success', 80, STR_PAD_RIGHT);
        }
        $hold = $this->prompt("Enter to continue", null, null, true);
    
    }

    /**
     * Remove a certificate
     */
    private function removeCertificate()
    {
        $client = new LocalClient();
        $ret = $client->getKnownCertificates();
        $data = json_decode($ret['data'], true);
        $certs = $data['known_certificates'];
        foreach($certs as $num => $cert) {
            $this->pline("$num: $cert", 'info', 80, STR_PAD_RIGHT);
        }
        $this->pline("X: Cancel and go back", 'info', 80, STR_PAD_RIGHT);
        $choice = $this->prompt("Please select a certificate to remove from the menu above", null,
            array_merge(array_keys($certs), array('x','X')));
        if (strtoupper($choice) == 'X') return;
        $name = $certs[$choice];
        $ret = $client->removeCertificate($name);
        $data = json_decode($ret['data']);
        if ($ret['status'] != 200) {
            $this->pline('Certificate not removed. Server responded with: '.$ret['status'], 'warning', 80, STR_PAD_RIGHT);
            echo $ret['message'], PHP_EOL, $data['error'], PHP_EOL;
        } else {
            $this->pline('Certificate removed.', 'success', 80, STR_PAD_RIGHT);
        }
        $hold = $this->prompt("Enter to continue", null, null, true);
    }

    /**
     * Verify the certificate store
     */
    private function verifyCertificateStore()
    {
        $client = new LocalClient();
        $ret = $client->verifyCertificateStore();
        $data = json_decode($ret['data'], true);
        if ($ret['status'] != 200) {
            $this->pline('Certificate store not ok. Server responded with: '.$ret['status'], 'warning', 80, STR_PAD_RIGHT);
            echo $ret['message'], PHP_EOL, $data['error'], PHP_EOL;
        } else {
            $this->pline('Certificate store  OK', 'success', 80, STR_PAD_RIGHT);
        }
        $hold = $this->prompt("Enter to continue", null, null, true);
    }

    /**
     * Verify the log directory
     */
    private function verifyLogDirectory()
    {
        $client = new LocalClient();
        $ret = $client->verifyLogDirectory();
        $data = json_decode($ret['data'], true);
        if ($ret['status'] != 200) {
            $this->pline('Log directory not ok. Server responded with: '.$ret['status'], 'warning', 80, STR_PAD_RIGHT);
            echo $ret['message'], PHP_EOL, $data['error'], PHP_EOL;
        } else {
            $this->pline('Log directory OK', 'success', 80, STR_PAD_RIGHT);
        }
        $hold = $this->prompt("Enter to continue", null, null, true);
    }

    /**
     * Print out a sub-heading
     *
     * @param string string
     * @param boolean footfirst - add a footer for the previous item first
     */
    private function subHead($string, $footfirst = false)
    {
        if ($footfirst) $this->subFoot();
        else echo PHP_EOL, PHP_EOL;
        $this->pline(str_pad(" $string ", 80, ':', STR_PAD_BOTH), 'green');
        echo PHP_EOL;
    }

    /**
     * Print out a sub-footer
     */
    private function subFoot()
    {
        echo PHP_EOL;
        $this->pline(str_repeat('-',80), 'green');
        echo PHP_EOL, PHP_EOL;
    }

    /**
     * Set up the environment configs
     */
    private function setUpEnv()
    {
        // deployment type
        $deploy = $this->prompt("Deployment type (dev, staging, prod)",
            $this->config['env']['deployment'], array('dev','staging', 'prod'));
        $this->config['env']['deployment'] = $deploy;
        if ($deploy == 'dev') {
            $debug = $this->prompt("Debug level (0-5)",
                $this->config['env']['debug']['level'], array(0,1,2,3,4,5));
            $this->config['env']['debug']['level'] = $debug;
        }
        // get local machine scheme name
        $sname = $this->prompt("Local machine scheme name",
            $this->config['env']['local_scheme_name'], '/.+\d$/', false,
            'Error: local machine scheme name must be numbered (polo1, gold2, etc)');
        $this->config['env']['local_scheme_name'] = $sname;
        // is the local machine part of a pool?
        $ispool = $this->promptYN("Is this machine part of a server pool?", 'N');
        if ($ispool) {
            $this->config['env']['config'] = 'db';
        } else {
        // not part of pool? db or file config?
            $configtype = $this->prompt("Configuration type (file or db)", 
                $this->config['env']['config'], array('file','db'));
            $this->config['env']['config'] = $configtype;
        }
        $this->updateConf('env');
    }

    /**
     * Set up the database configs
     */
    private function setUpDatabase()
    {
        // database type
        $dbtype = $this->prompt("Database type: (M)ySQL (P)ostgreSQL or (S)qlite",
            'M', array('M','m','P','p','S','s'));
        switch (strtoupper($dbtype)) {
            case 'M':
                $this->config['database']['dbmaster']['driver'] = 'mysqli';
                $this->config['database']['dbslave']['driver'] = 'mysqli';
                break;
            case 'P':
                $this->config['database']['dbmaster']['driver'] = 'postgres';
                $this->config['database']['dbslave']['driver'] = 'postgres';
                break;
            case 'S':
                $this->config['database']['dbmaster']['driver'] = 'sqlite';
                $this->config['database']['dbslave']['driver'] = 'sqlite';
                break;
        }
        // database host
        $dbhost = $this->prompt("Database host", 
            $this->config['database']['dbmaster']['host']);
        $this->config['database']['dbmaster']['host'] = $dbhost;
        $this->config['database']['dbslave']['host'] = $dbhost;
        // database username
        $dbuser = $this->prompt("Database username", 
            $this->config['database']['dbmaster']['user']);
        $this->config['database']['dbmaster']['user'] = $dbuser;
        $this->config['database']['dbslave']['user'] = $dbuser;
        // database password
        $dbpass = $this->prompt("Database user password", 
            $this->config['database']['dbmaster']['password']);
        $this->config['database']['dbmaster']['password'] = $dbpass;
        $this->config['database']['dbslave']['password'] = $dbpass;
        // database database
        $dbdb = $this->prompt("Database to use", 
            $this->config['database']['dbmaster']['defaultdb']);
        $this->config['database']['dbmaster']['defaultdb'] = $dbdb;
        $this->config['database']['dbslave']['defaultdb'] = $dbdb;
        // database table prefix
        $dbpfx = $this->prompt("Prefix for database tables", 
            $this->config['database']['prefix'], null, true);
        $this->config['database']['prefix'] = $dbpfx;
        $dbslv = $this->promptYN("Is there a slave (read-only) database to configure?", 'N');
        if ($dbslv) {
            // slave db? slave host
            $dbhost = $this->prompt("Slave database host", 
                $this->config['database']['dbslave']['host']);
            $this->config['database']['dbslave']['host'] = $dbhost;
            // slave db? slave username
            $dbuser = $this->prompt("Slave database username", 
                $this->config['database']['dbslave']['user']);
            $this->config['database']['dbslave']['user'] = $dbuser;
            // slave db? slave password
            $dbpass = $this->prompt("Slave database user password", 
                $this->config['database']['dbslave']['password']);
            $this->config['database']['dbslave']['password'] = $dbpass;
        }
        $this->updateConf('env');
        try {
            $db = DB::getMaster();
        } catch (\Exception $e) {
            $this->pline('Unable to connect to master database! Make sure server is running and database exists before continuing.', 'error');
            return $this->setUpDatabase();
        }
        if ($dbslv) {
            try {
                $db = DB::getSlave();
            } catch (\Exception $e) {
                $this->pline('Unable to connect to slave database! Make sure server is running and database exists before continuing.', 'error');
                return $this->setUpDatabase();
            }
        }
    }

    /**
     * Set up the certificate configs
     */
    private function setUpCertificate()
    {
        $cstore = $this->prompt("Where is the certificate store",
            $this->config['certificate']['store']);
        $this->config['certificate']['store'] = $cstore;
        $cval = $this->promptYN("Verify certificates are signed by our known root?", 'Y');
        $this->config['certificate']['validate'] = $cval;
        $this->updateConf('certificate');
    }

    /**
     * Set up the local listener configs
     */
    private function setUpS3c3_local_listener()
    {
        $lurl = $this->prompt("What is the local listener url (for use by " .
            "bootstrap and client, NOT the primary app url)?",
            $this->config['s3c3_local_listener']['endpoint'], null, false,
            'Error: please enter a url on the local host only');
        $this->config['s3c3_local_listener']['endpoint'] = $lurl;
        $this->config['s3c3_local_listener']['internal_token'] = $this->generateKey();
        $this->updateConf('s3c3_local_listener');
    }

    /**
     * Set up the logging configs
     */
    private function setUpLogging()
    {
        $lfile = $this->prompt("Logging directory for S3C3",
            $this->config['logging']['file']['dir']);
        $this->config['logging']['file']['dir'] = $lfile;
        $ismail = $this->promptYN("Set up mail logging?", 'N');
        if ($ismail) {
            $mfrom = $this->prompt("FROM email address for log emails",
                $this->config['logging']['mail']['from']);
            $this->config['logging']['mail']['from'] = $mfrom;
            $mto = $this->prompt("Email (or comma-separated list of emails) to send logs to",
                $this->config['logging']['mail']['to']);
            $this->config['logging']['mail']['to'] = $mto;
        } else {
            $this->config['logging']['mail']['from'] = null;
            $this->config['logging']['mail']['to'] = null;
        }
        $issms = $this->promptYN("Set up SMS email alerts?", 'N');
        if ($issms) {
            $sfrom = $this->prompt("FROM email address for SMS alerts",
                $this->config['logging']['sms']['from']);
            $this->config['logging']['sms']['from'] = $mfrom;
            $mto = $this->prompt("SMS email to send alerts to",
                $this->config['logging']['sms']['to']);
            $this->config['logging']['sms']['to'] = $mto;
        } else {
            $this->config['logging']['sms']['from'] = null;
            $this->config['logging']['sms']['to'] = null;
        }
        $this->updateConf('logging');    
    }

    /**
     * Set up the token configs
     */
    private function setUpToken()
    {
        $texp = $this->prompt("Token expiration time (in seconds)",
            $this->config['token']['expire'], '/[0-9]+/', false,
            'Error: you must enter an expiration time in seconds (no decimal) only');
        $tlen = 0;
        while($tlen < 32 || $tlen > 64) {
            $tlen = $this->prompt("Token length in characters, longer is better, (32 - 64)",
                $this->config['token']['length'], '/[0-9]+/', false,
                'Error: length must be in numbers only');
            if ($tlen >= 32 && $tlen <= 64) {
                $this->config['token']['length'] = $tlen;
            } else {
                $this->pline('Error: length must be no less than 32 and no more than 64');
            }
        }
        $tstren = $this->prompt("Token strength (weak, medium, strong, maximum)",
            $this->config['token']['strength'], array('weak', 'medium', 'strong', 'maximum'));
        $this->config['token']['strength'] = $tstren;
        $tdel = $this->promptYN("Delete expired tokens on loading of other tokens?", 'Y');
        $this->config['token']['delete_on_load'] = $tdel;
        $this->updateConf('token');
    }

    /**
     * Set up the scheme configs
     */
    private function setUpScheme()
    {
        if (!empty($this->config['scheme']['listeners'])) {
            $this->pline(str_pad("Current Scheme:", 80, ' ', STR_PAD_RIGHT), 'info');
            $this->pline(str_pad("    Listeners:", 80, ' ', STR_PAD_RIGHT), 'info');
            foreach ($this->config['scheme']['listeners'] as $lis => $url) {
                $this->pline(str_pad("        $lis : $url", 80, ' ', STR_PAD_RIGHT), 'info');
            }
            $this->pline(str_pad("    Clients:", 80, ' ', STR_PAD_RIGHT), 'info');
            foreach ($this->config['scheme']['clients'] as $c) {
                $this->pline(str_pad("        $c", 80, ' ', STR_PAD_RIGHT), 'info');
            }
            $delscheme = $this->promptYN("Delete the currently configured listeners and clients?",'N');
            if (!$delscheme) {
                $keepscheme = $this->promptYN("Keep the currently configured scheme with no changes?", 'N');
                if ($keepscheme) {
                    $this->updateConf('scheme');
                    return;
                }
                $this->editScheme();
            }
        }
        do {
            $sname = $this->prompt("Scheme listener name ([Enter] to quit scheme setup)",
                null, null, true);
            if (!empty($sname)) {
                $surl = $this->prompt("URL for listener $sname");
                $this->config['scheme']['listeners'][$sname] = $surl;
                $sclients = $this->prompt("Client numbers to add for $sname (comma-separated)",
                    null, '/^[0-9 ,]+$/',false,
                    'Error: enter only a comma-separated list of client numbers to add eg: 1,3,4,8,10,22');
                $cnums = explode(',', $sclients);
                foreach($cnums as $cnum) {
                    $this->config['scheme']['clients'][] = $sname . trim($cnum);
                }
            }
        } while (!empty($sname));
        $this->updateConf('scheme');
    }

    /**
     * Edit the scheme configs
     */
    private function editScheme()
    {
        foreach ($this->config['scheme']['listeners'] as $lis => $url) {
            $dellist = $this->promptYN("Delete the listener $lis?",'N');
            if ($dellist) {
                unset($this->config['scheme']['listeners'][$lis]);
                $count = count($this->config['scheme']['clients']);
                for ($i = 0; $i < count; $i++) {
                    if (substr($this->config['scheme']['clients'][$i], 0, strlen($lis)) == $lis) {
                        unset($this->config['scheme']['clients'][$i]);
                    }
                }
            }
            $newurl = $this->prompt("URL for this listener", $url);
            if ($newurl != $url) {
                $this->config['scheme']['listeners'][$lis] = $url;
            }
            $addclients = $this->promptYN("Add clients for this listener?", 'Y');
            if ($addclients) {
                $sclients = $this->prompt("Client numbers to add for $lis (comma-separated)",
                    null, '/^[0-9 ,]+$/',false,
                    'Error: enter only a comma-separated list of client numbers to add eg: 1,3,4,8,10,22');
                $cnums = explode(',', $sclients);
                foreach($cnums as $cnum) {
                    $this->config['scheme']['clients'][] = $lis . trim($cnum);
                }
            }
        }
    }

    /**
     * Update the global config with this object's configs
     */
    private function updateConf($fldName)
    {
        Config::getInstance()->write($fldName, $this->config[$fldName]);
    }

    /**
     * Store the configs to the database
     */
    private function storeToDB($confarray, $key = null, $parent = null)
    {
        if (!is_null($parent)) {
            $key = is_null($key) ? $parent : "$parent.$key";
        }
        foreach ($confarray as $ckey => $value) {
            if (is_array($value)) {
               $this->storeToDB($value, $ckey, $key);
            } else {
                \s3c3\core\model\ConfigItem::writeConfigItem("$key.$ckey", $value, true);
                //echo "$key.$ckey = $value\n";
            }
        }
    }

    /**
     * Generate a key for local listener
     */
    private function generateKey()
    {
        $key = '';
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $cx = strlen($chars) - 1;
        while (strlen($key) < 24) {
            $key .= $chars{mt_rand(0, $cx)};
        }
        return $key;
    }

    /**
     * prompt for user input
     * @param string text
     * @param string default
     * @param mixed allowed values
     * @param boolean null is ok
     * @param string error message
     * @return string
     */
    private function prompt($text, $default = null, $allowed = null,
        $nullok = false, $errmessage = null)
    {
        $pr = (!empty($default)) ? " [$default] > " : ' > ';
        $this->p($text);
        echo $pr;
        $handle = fopen ("php://stdin","r");
        $line = fgets($handle);
        $ret = trim($line);
        fclose($handle);
        if (empty($ret) && $default) return $default;
        if (empty($ret) && $nullok) return null;
        if (!empty($allowed) && is_array($allowed)) {
            if (in_array($ret, $allowed)) return $ret;
            else {
                if (empty($errmessage)) {
                    $allowstr = '';
                    foreach ($allowed as $allow) $allowstr .= " $allow";
                    $errmessage = 'Error: allowed values are: ' . $allowstr;
                }
                $this->pline($errmessage, 'error', 80, STR_PAD_RIGHT);
                return $this->prompt($text, $default, $allowed, $nullok, $errmessage);
            }
        } elseif (!empty($allowed) && is_string($allowed)) {
            if (preg_match($allowed, $ret)) return $ret;
            else {
                $this->pline($errmessage, 'error', 80, STR_PAD_RIGHT);
                return $this->prompt($text, $default, $allowed, $nullok, $errmessage);
            }
        }
        return $ret;
    }

    /**
     * prompt for user input (limited to Y/N)
     * @param string text
     * @param string default
     * @param string error message
     * @return boolean
     */
    private function promptYN($text, $default = null, $errmessage = null)
    {
        $pr = (!empty($default)) ? " [$default] > " : ' > ';
        $this->p($text . ' (Y/N)');
        echo $pr;
        $handle = fopen ("php://stdin","r");
        $line = fgets($handle);
        $ret = trim($line);
        fclose($handle);
        if (empty($ret) && $default) $ret = $default;
        $ret = strtoupper($ret);
        if (empty($ret) || !in_array($ret, array('Y', 'N'))) {
            $this->pline('Error: please indicate Y for yes or N for no', 'error');
            return $this->promptYN($text, $default, $allowed, $nullok, $errmessage);
        }
        return $ret == 'Y' ? 1 : 0;
    }

    /**
     * print out to screen with no newline
     * @param string text
     * @param string color
     */
    private function p($text, $color = 'yellow')
    {
        if (!$this->color) echo $text;
        else $this->colors[$color]->p($text);
    }

    /**
     * print out to screen with a newline
     * @param string text
     * @param string color
     * @param int pad
     * @param int padstyle
     */
    private function pline($text, $color = 'normal', $pad = 0, $padstyle = STR_PAD_BOTH)
    {
        if (!$this->color) echo $text, "\n";
        else {
            if ($pad) $text = str_pad($text, $pad, ' ', $padstyle);
            $this->colors[$color]->pline($text);
        }
    }

    /**
     * clear the screen
     */
    private function clearScreen()
    {
        if ($this->isWin) passthru('cls');
        else passthru('clear');
    }

    /**
     * set up the color objects
     */
    private function setupColors()
    {
        $this->colors = array (
            'red' => new ANSI(ANSI::RED, ANSI::BLACK, ANSI::BRIGHT),
            'blue' => new ANSI(ANSI::BLUE, ANSI::BLACK, ANSI::BRIGHT),
            'dullred' => new ANSI(ANSI::RED, ANSI::BLACK),
            'error' => new ANSI(ANSI::WHITE, ANSI::RED, ANSI::BRIGHT),
            'warning' => new ANSI(ANSI::BLACK, ANSI::YELLOW),
            'success' => new ANSI(ANSI::WHITE, ANSI::GREEN, ANSI::BRIGHT),
            'info' => new ANSI(ANSI::WHITE, ANSI::BLUE, ANSI::BRIGHT),
            'yellow' => new ANSI(ANSI::YELLOW, ANSI::BLACK, ANSI::BRIGHT),
            'green' => new ANSI(ANSI::GREEN, ANSI::BLACK, ANSI::BRIGHT),
            'normal' => new ANSI(ANSI::WHITE, ANSI::BLACK)
            );
    }

}
if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
    $user = $_SERVER['USERNAME'];
    if ($user !== 'root') {
        die('You must run this script as root' . PHP_EOL);
    }
} else {
    $res = exec(__DIR__ . DIRECTORY_SEPARATOR . '_win_admin.bat');
    if (!$res) {
        die('You must run this script as Administrator.' . PHP_EOL);
    }
}
// First, figure out if we want to colorize
$opts = getopt('acf:');
$color = isset($opts['c']);
$auto = false;
$conffile = null;
if (isset($opts['f'])) {
    $conffile = $opts['f'];
    $auto = isset($opts['a']);
}
$bootstrap = new Bootstrap($color, $conffile, $auto);
$bootstrap->run();


