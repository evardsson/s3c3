<?php
/**
 * This is the web entry point for S3C3.
 *
 * This is where you would set up actions for the Listener to call based on the
 * value of 'call' in the post fields, if it is set. This also sets up a default
 * action which is called when 'call' does not exist in the post fields.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 */
require_once 'init.php';

/**
 * @var callbacks array
 * set this up to match your needs
 * This should be set up as 'callname' => array('callback', 'includefile')
 */
$callbacks = array (
    'default' => array(
        '\\s3c3\\example\\ListenerShim::respondPublic', __DIR__ . '/examples/ListenerShim.php')
    );

$remoteip = (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) ?
            $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
if ($remoteip == '127.0.0.1' || $remoteip == '::1') {
    \s3c3\listener\LocalListener::handleRequest();
} else {
    $call = 'default';
    if (isset($_POST['call']) && array_key_exists($call, $callbacks)) $call = $_POST['call'];
    require_once $callbacks[$call][1];
    \s3c3\listener\Listener::handleRequest($callbacks[$call][0]);
}
