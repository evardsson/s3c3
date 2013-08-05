<?php
/**
 * Database class.
 *
 * The Database class acts as a bridge between the models and the ADOdb classes.
 * This class handles creating the SQL queries, making the calls and returning the
 * data in a format the models can use.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage -
 */
namespace s3c3\core\db;

use \s3c3\conf\Config;
use \s3c3\except\ConfigurationException;
use \s3c3\except\DatabaseException;


/**
 * class Database
 *
 * @since version 1.0
 */
class Database
{

    /**
     * For building searches with EQUAL (=)
     * @constant EQ
     */
    const EQ    = 1;

    /**
     * For building searches with NOT EQUAL (!=)
     * @constant NE
     */
    const NE    = 2;

    /**
     * For building searches with LESS THAN (<)
     * @constant LT
     */
    const LT    = 3;

    /**
     * For building searches with LESS THAN OR EQUAL (<=)
     * @constant LTE
     */
    const LTE   = 4;

    /**
     * For building searches with GREATER THAN (>)
     * @constant GT
     */
    const GT    = 5;

    /**
     * For building searches with GREATER THAN OR EQUAL (>=)
     * @constant GTE
     */
    const GTE   = 6;

    /**
     * For building searches with BETWEEN (BETWEEN ? AND ?)
     * @constant BTW
     */
    const BTW   = 7;

    /**
     * For building searches with IN
     * @constant IN
     */
    const IN    = 8;

    /**
     * For building searches with NOT IN
     * @constant NIN
     */
    const NIN   = 9;

    /**
     * For building searches with IS NULL
     * @constant NUL
     */
    const NUL   = 10;

    /**
     * For building searches with IS NOT NULL
     * @constant NNUL
     */
    const NNUL  = 11;

    /**
     * For building searches with LIKE
     * @constant LIKE
     */
    const LIKE  = 12;

    /**
     * For building searches with NOT LIKE
     * @constant NLIKE
     */
    const NLIKE = 13;


    /**
     * Database object
     * @var instance
     * @access private
     */
	private static $instance = array();
	
    /**
     * string prefix
     * @var prefix
     * @access private
     */
	private static $prefix;

	/**
	 * Construct a new Database object
	 * @access private
	 */
	private function __construct() {
	}

	/**
	 * Method to grab the handle to a specified database
	 * @param string db name (defaults to config database.defaultdb)
	 * @param boolean write/master db (default true)
	 * @return ADODB_Connection object
	 */
	public static function getDatabase($db = null, $master = true) {
	    $conf = Config::getInstance();
	    $pre = S3C3_PREFIX;
	    //$pre = self::$prefix;
	    if (is_null(self::$prefix)) {
	        if (empty($pre)) {
	            self::$prefix = '';
	        } else {
	            self::$prefix = $pre . '_';
	        }
	    }
	    if (S3C3_UNITTEST && strpos(self::$prefix, 'unit_') === false) self::$prefix .= 'unit_';
		$dbh = $conf->read('database.dbmaster');
		if (!$master && $conf->exists('database.dbslave')) {
		    $dbh = $conf->read('database.dbslave');
		}
		if (empty($dbh)) {
			throw new ConfigurationException("Couldn't determine database server "
			    . "information from local configuration.");
		}
		if (empty($db)) {
			$db = $dbh['defaultdb'];
		}
		return self::getConnection($db, $dbh);
	}
	
	/**
	 * Get the DB prefix (including for unittests)
	 *
	 * @return string
	 */
	public static function getPrefix() {
	    if (is_null(self::$prefix)) {
	        self::getDatabase();
	    }
	    return self::$prefix;
	}

	/**
	 * Temporarily update the DB prefix (useful in bootstrap)
	 *
	 * @param string newPrefix
	 */
	public static function updatePrefix($newPrefix) {
	    if (defined('IN_BOOTSTRAP')) {
	        self::$prefix = $newPrefix;
	    }
	}

	/**
	 * Get the actual connection
	 * @param string db name (defaults to config database.defaultdb)
	 * @param array db configs
	 * @return ADODB_Connection object
	 */
	private static function getConnection($db, $dbh) {
		if (isset(self::$instance[$db]) && self::$instance[$db]->isConnected()) {
		    try {
		        self::$instance[$db]->Execute("set names 'utf8'");
		        return self::$instance[$db]; // only if it is still live
		    } catch (\Exception $adoex) {
		        // skip this - it will follow through below
		    }
		}
		// Using DSN to ADONewConnection, with persistent connections
		$dsn = $dbh['driver'] . '://' . $dbh['user'] . ':' . $dbh['password'] .
		    '@' . $dbh['host'] . '/' . $db . '?persist=1';
		if (!empty($dbh['port'])) $dsn .= '&port=' . $dbh['port'];
		try {
            self::$instance[$db] = ADONewConnection($dsn);
            self::$instance[$db]->SetFetchMode(ADODB_FETCH_ASSOC);
            if (strpos($dbh['driver'], 'sqlite') === false)
                self::$instance[$db]->Execute("set names 'utf8'");
            return self::$instance[$db];
        } catch (\ADODB_Exception $e) {
            throw new DatabaseException('Unable to connect to database.', $e->getCode(), $e);
        }
	}

	/**
	 * Method to grab the read-only handle to a specified database
	 * @param string db name (defaults to config database.defaultdb)
	 * @return ADODB_Connection object
	 */
	public static function getSlave($db = null) {
	    return self::getDatabase($db, false);
	}

	/**
	 * Method to grab the read-write handle to a specified database
	 * @param string db name (defaults to config database.defaultdb)
	 * @return ADODB_Connection object
	 */
	public static function getMaster($db = null) {
	    return self::getDatabase($db, true);
	}

    /**
     * Build the tables - this will only work with an empty database, OR with a
     * defined prefix in a database with no tables bearing that prefix
     *
     * @param string dbtype - currently supports mysql, postgresql and sqlite
     * @throws ConfigurationException
     */
    public static function buildTables($dbtype = 'mysql')
    {
        $bqstring = file_get_contents(__DIR__ . '/s3c3_'.$dbtype.'.sql');
        $prefix = Config::getInstance()->read('database.prefix');
        if (!empty($prefix)) $prefix .= '_';
        if (S3C3_UNITTEST) $prefix .= 'unit_';
        $buildqueries = explode(';', $bqstring);
        $conn = self::getMaster();
        $tableson = $conn->MetaTables('TABLE');
        if (!empty($tableson)) {
            foreach ($tableson as $tableon) {
                if ($tableon == $prefix.'configs' || $tableon == $prefix.'contexts')
                    throw new ConfigurationException('This would overwrite existing tables! Aborting DB build.');
            }
        }
        foreach ($buildqueries as $query) {
            if (!empty($prefix) && strpos($query, 'CREATE TABLE') !== false) {
                $query = preg_replace('/(CREATE TABLE )([a-z_]+)/','${1}'.$prefix.'${2}', $query);
            }
            if (!empty($prefix) && strpos($query, 'CREATE INDEX') !== false) {
                $query = preg_replace('/(CREATE INDEX .*ON )([a-z_]+)/','${1}'.$prefix.'${2}', $query);
                die ($query);
            }
            if (!empty($prefix) && strpos($query, 'INSERT INTO') !== false) {
                $query = preg_replace('/(INSERT INTO )([a-z_]+)/','${1}'.$prefix.'${2}', $query);
            }
            $query = trim($query);
            //echo "\n *** \n$query\n *** \n";
            if (!empty($query)) $conn->Execute($query);
        }
    }
    
    /**
     * Get all rows that match the args passed
     *
     * @param string table name
     * @param array args (see buildSearchClause)
     * @param array  fields to get or empty array to get all 
     * @param array order by
     * @param int limit
     * @return array
     */
    public static function getAll($table, $args, $fields = array(), $order = false, $limit = false)
    {
        $prefix = self::$prefix;
        if (!empty($fields) && is_scalar($fields)) $fields = array($fields);
        $flds = empty($fields) ? '*' : implode(', ', $fields);
        $query = "SELECT $flds FROM {$prefix}{$table} ";
        $values = $retval = array();
        if (is_array($args)) $query .= self::buildSearchClause($args, $values);
        if (is_array($order)) {
            $query .= " ORDER BY ";
            $comma = '';
            foreach ($order as $field => $direction) {
                $query .= "$comma$field $direction";
                $comma = ', ';
            }
        }
        if ($limit) {
            $query .= " LIMIT $limit";
        }
        $db = self::getSlave();
        return $db->GetAll($query, $values);
    }

    /**
     * Get one row that matches the args passed
     *
     * @param string table name
     * @param array args (see buildSearchClause)
     * @param array  fields to get or empty array to get all 
     * @param array order by
     * @return array
     */
    public static function getRow($table, $args, $fields = array(), $order = false)
    {
        $prefix = self::$prefix;
        if (!empty($fields) && is_scalar($fields)) $fields = array($fields);
        $flds = empty($fields) ? '*' : implode(', ', $fields);
        $query = "SELECT $flds FROM {$prefix}{$table} ";
        $values = $retval = array();
        if (is_array($args)) $query .= self::buildSearchClause($args, $values);
        if (is_array($order)) {
            $query .= " ORDER BY ";
            $comma = '';
            foreach ($order as $field => $direction) {
                $query .= "$comma$field $direction";
                $comma = ', ';
            }
        }
        $db = self::getSlave();
        return $db->GetRow($query, $values);
    }

    /**
     * Get one column that matches the args passed
     *
     * @param string table name
     * @param array args (see buildSearchClause)
     * @param string field to get 
     * @param array order by
     * @param int limit
     * @return array
     */
    public static function getColumn($table, $args, $field, $order = false, $limit = false)
    {
        $prefix = self::$prefix;
        $query = "SELECT $field FROM {$prefix}{$table} ";
        $values = $retval = array();
        if (is_array($args)) $query .= self::buildSearchClause($args, $values);
        if (is_array($order)) {
            $query .= " ORDER BY ";
            $comma = '';
            foreach ($order as $field => $direction) {
                $query .= "$comma$field $direction";
                $comma = ', ';
            }
        }
        if ($limit) {
            $query .= " LIMIT $limit";
        }
        $db = self::getSlave();
        return $db->GetCol($query, $values);
    }

    /**
     * Get one single value (from one column in one row) that matches the args passed
     *
     * @param string table name
     * @param array args (see buildSearchClause)
     * @param string field to get 
     * @param array order by
     * @return array
     */
    public static function getOne($table, $args, $field, $order = false)
    {
        $prefix = self::$prefix;
        $query = "SELECT $field FROM {$prefix}{$table} ";
        $values = $retval = array();
        if (is_array($args)) $query .= self::buildSearchClause($args, $values);
        if (is_array($order)) {
            $query .= " ORDER BY ";
            $comma = '';
            foreach ($order as $field => $direction) {
                $query .= "$comma$field $direction";
                $comma = ', ';
            }
        }
        $db = self::getSlave();
        return $db->GetOne($query, $values);
    }

    /**
     * Insert a row
     *
     * @param string table name
     * @param array  data (simple field => value type array)
     * @return array
     */
    public static function insert($table, $data)
    {
        $prefix = self::$prefix;
        $db = self::getMaster();
        if (!$db->AutoExecute($prefix . $table, $data, 'INSERT')) {
            throw new DatabaseException("Unable to insert row in $table: ".print_r($data, true));
        }
        return $db->Insert_Id();
    }

    /**
     * Update a row
     *
     * @param string table name
     * @param int    row id
     * @param array  data (simple field => value type array)
     * @return array
     */
    public static function update($table, $id, $data)
    {
        $prefix = self::$prefix;
        $db = self::getMaster();
        if (!$db->AutoExecute($prefix . $table, $data, 'UPDATE', "id = $id")) {
            throw new DatabaseException("Unable to update row in $table with id of $id");
        }
        return $id;
    }

    /**
     * Update a row
     *
     * @param string table name
     * @param int    row id
     * @return array
     */
    public static function delete($table, $id)
    {
        $prefix = self::$prefix;
        $db = self::getMaster();
        $db->Execute("DELETE FROM $prefix$table WHERE id = ?", array($id));
        return $db->Affected_Rows();
    }

    /**
     * Execute a raw SQL query - useful only for updates/deletes
     * DO NOT USE WITH USER INPUT! THIS DOES NO CHECKING OR ESCAPING OF SQL! DO NOT
     * USE NON-STANDARD SQL-92 QUERIES IN THIS METHOD!
     *
     * @param string raw SQL
     * @return number of affected rows
     */
    public static function execute($rawSql)
    {
        $prefix = self::$prefix;
        $db = self::getMaster();
        $db->Execute($rawSql);
        return $db->Affected_Rows();
    }

    /**
     * Build a WHERE clause for a query
     *
     * @param array args: example:
     *     array(
     *        'id' => array(self::NIN ,array(1,2,4,7,8)),
     *        'city' => array(self::EQ, 'Cleveland'),
     *        'date' => array(self::BTW, '2012-12-31','2013-07-31'),
     *        'last_name' => array(self::IN, array('Smith', 'Jones')),
     *        'first_name' => array(self::LIKE, 'Mic%'),
     *        'income' => array(self::LTE, 100000));
     *     Resulting search clause:
     *        WHERE id NOT IN (?, ?, ?, ?, ?) AND city = ? AND date BETWEEN ? AND ?
     *        AND last_name IN (?, ?) AND first_name LIKE ? AND income <= ?
     *     Resulting values:
     *        array(1,2,4,5,8,'Cleveland','2012-12-31','2013-07-31',
     *              'Smith', 'Jones', 'Mic%', 100000);
     * @param array values - passed by reference and updated in place
     * @return string search clause
     */
    public static function buildSearchClause($args, &$values = array())
    {
        $where = " WHERE";
        $xand = '';
        foreach ($args as $field => $arg) {
            $where .= "$xand $field ";
            $match = array_shift($arg);
            switch ($match) {
                case self::NE:
                    $where .= '!= ?';
                    $values[] = $arg[0];
                    break;
                case self::LT:
                    $where .= '< ?';
                    $values[] = $arg[0];
                    break;
                case self::LTE:
                    $where .= '<= ?';
                    $values[] = $arg[0];
                    break;
                case self::GT:
                    $where .= '> ?';
                    $values[] = $arg[0];
                    break;
                case self::GTE:
                    $where .= '>= ?';
                    $values[] = $arg[0];
                    break;
                case self::BTW:
                    $where .= 'BETWEEN ? AND ?';
                    $values[] = $arg[0];
                    $values[] = $arg[1];
                    break;
                case self::IN:
                    $where .= 'IN (';
                    $comma = '';
                    foreach ($arg as $v) {
                        $where .= "$comma?";
                        $comma = ', ';
                        $values[] = $v;
                    }
                    break;
                case self::NIN:
                    $where .= 'NOT IN (';
                    $comma = '';
                    foreach ($arg as $v) {
                        $where .= "$comma?";
                        $comma = ', ';
                        $values[] = $v;
                    }
                    break;
                case self::NUL:
                    $where .= 'IS NULL';
                    break;
                case self::NNUL:
                    $where .= 'IS NOT NULL';
                    break;
                case self::LIKE:
                    $where .= 'LIKE ?';
                    $values[] = $arg[0];
                    break;
                case self::NLIKE:
                    $where .= 'NOT LIKE ?';
                    $values[] = $arg[0];
                    break;
                case self::EQ:
                    $where .= '= ?';
                    $values[] = $arg[0];
                default:
                    break;
            }
            $xand = ' AND';
        }
        return $where;
    }

}
