<?php
/**
 * Example shim for tieing the Listener to an outside process.
 *
 * This example (test) shim evaluates the data data sent by the test Client Shim
 * and creates an answer to send back. In a real scenario the Listener may be given
 * different callbacks depending on the value of 'call' in the post vars.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage example
 */
namespace s3c3\example;

/**
 * class ListenerShim
 *
 * @since version 1.0
 */
class ListenerShim
{
    
    /**
     * string json data
     * @var json
     * @access private
     */
    private $json;

    /**
     * mixed data
     * @var data
     * @access private
     */
    private $data;

    /**
     * array of field names
     * @var fields
     * @access private
     */
    private $fields = array('name','password','dividend','divisor');

    /**
     * Constructor
     * Create a new ListenerShim
     */
    public function __construct($json)
    {
        $this->data = json_decode($json, true);
    }
    
    /**
     * Generate an answer (or an error)
     *
     * @return void
     */
    protected function calculate()
    {
        $err = false;
        $errors = array();
        foreach ($this->fields as $key) {
            if (!isset($this->data[$key]) ||
                is_null($this->data[$key]) ||
                trim($this->data[$key]) === '')
            {
                $err = true;
                $errors[] = "Missing value for $key";
            }
        }
        if ($this->data['divisor']+0 === 0) {
            $err = true;
            $errors[] = "Cannot divide by zero";
        }
        if (trim($this->data['password']) !== trim(strrev($this->data['name']))) {
            $err = true;
            $errors[] = "Bad password";
        }
        if ($err) $this->data['error'] = $errors;
        else $this->data['answer'] = $this->data['dividend']/$this->data['divisor'];
    }
    
    /**
     * Generate return json object
     *
     * @return string
     */
    protected function getResponse()
    {
        if (is_null($this->data['answer'])) unset($this->data['answer']);
        if (is_null($this->data['error'])) unset($this->data['error']);
        return json_encode($this->data);
    }
    
    /**
     * Respond to incoming requests
     *
     * @param string indata in json format
     * @return string json
     */
    public static function respondPublic($indata)
    {
        $ls = new self($indata);
        $ls->calculate();
        return $ls->getResponse();
    }

}
