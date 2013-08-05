<?php
/**
 * Context class for connection management.
 *
 * The Context class puts a token/connection into a context - that is which client,
 * token and expiration time go together. This class also manages removing the
 * token string when a context is used or expires (see SRS: 4.4)
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage core
 */
namespace s3c3\core\model;

use \s3c3\conf\Config;
use \s3c3\core\Model;
use \s3c3\core\db\Database as DB;
use \s3c3\except\ObjectNotFoundException;
use \s3c3\except\SecurityViolationException;

/**
 * class Context
 *
 * @since version 1.0
 */
class Context extends Model
{

    const COMPLETED = 1;
    const TIMEOUT   = 2;
    const INVALID   = 4;

    /**
     * array of valid fields
     * @var fields
     * @access protected
     */
    protected $fields = array(
        'id', 'client', 'token', 'created', 'expires', 'completed', 'usage_status'
        );
    
    /**
     * string table name
     * @var table
     * @access protected
     */
    protected $table = 'contexts';

    /**
     * integer object id
     * @var id
     * @access protected
     */
    protected $id;

    /**
     * string client name
     * @var client
     * @access protected
     */
    protected $client;

    /**
     * string token
     * @var token
     * @access protected
     */
    protected $token;

    /**
     * timestamp created (as str YYYY-MM-DD HH:MM:SS)
     * @var created
     * @access protected
     */
    protected $created;

    /**
     * timestamp created (as str YYYY-MM-DD HH:MM:SS)
     * @var expires
     * @access protected
     */
    protected $expires;

    /**
     * timestamp completed (as str YYYY-MM-DD HH:MM:SS)
     * @var completed
     * @access protected
     */
    protected $completed;

    /**
     * int usage_status
     * @var usage_status
     * @access protected
     */
    protected $usage_status;

    /**
     * Mark a context as being successfully completed
     *
     * @param int usage_status to set
     * @return int context id
     */
    public function markAs($usage_status = self::COMPLETED)
    {
        $this->token = null;
        $this->completed = date('Y-m-d H:i:s');
        $this->usage_status = $usage_status;
        return $this->save();
    }

    /**
     * "Delete" expired objects from the database (removes only the token)
     *
     * @return int number rows updated
     */
    public function deleteExpired()
    {
        $table = DB::getPrefix() . $this->table; 
        $query = "UPDATE $table SET token = NULL, usage_status = 2 " .
            "WHERE expires < CURRENT_TIMESTAMP AND token IS NOT NULL";
        return DB::execute($query);
    }
    
    /**
     * Create a contect object in the database by token
     *
     * @param object token Token
     * @return object Context or null
     */
    public static function createContextFor($token)
    {
        $args = array(
            'client'  => $token->getClient(),
            'token'   => $token->getTokenString(),
            'expires' => $token->getExpires()
        );
        $c = new self($args);
        $c->save();
        return $c;
    }

    /**
     * Find a contect object in the database by token
     *
     * @param string token
     * @param string client name
     * @return object Context or null
     */
    public static function findContextByToken($token, $client)
    {
        $c = new self();
        if (Config::getInstance()->read('token.delete_on_load')) {
            $c->deleteExpired();
        }
        $args = array('token' => array(DB::EQ, $token));
        $context = $c->findOne($args);
        if (is_null($context)) {
            throw new ObjectNotFoundException("No Context found for token $token");
        } elseif ($context->client != $client) {
            $context->markAs(self::INVALID);
            throw new SecurityViolationException("Invalid client for token $token");
        }
        $context->markAs(self::COMPLETED);
        return $context;
    }

}
