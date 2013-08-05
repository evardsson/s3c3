<?php
/**
 * The example cli script to run the client shim
 *
 * This file lets the user select a listener, how many packages to send to the
 * listener and whether or not to generate errors in the packages.
 *
 * @author Sjan Evardsson <sjan@evardsson.com>
 * @link http://www.evardsson.com/
 * @copyright Sjan Evardsson 2013
 * @version 1.0
 * @package s3c3
 * @subpackage example
 */
namespace s3c3\example;

require_once __DIR__ . '/../init.php';
use \s3c3\conf\Config;

require_once __DIR__ . '/ClientShim.php';

$isWin = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
$target = '';

/**
 * main menu for running examples
 */
function menu()
{
    global $target;
    clearScreen();
    echo 'S3C3 Example Menu';
    echo PHP_EOL, PHP_EOL;
    $sline = 'S:  Select Listener';
    if (!empty($target)) $sline .= " ($target)";
    $menu = array(
        $sline,
        'G:  Generate Requests',
        'X:  Exit'
        );
    foreach ($menu as $item) echo $item, PHP_EOL;
    $choice = prompt("Please select an action from the menu above", null,
        array('S','s','G','g','X','x'));
    switch ($choice) {
        case 'S':
        case 's':
            selectTarget();
            break;
        case 'G':
        case 'g':
            generate();
            break;
        case 'X':
        case 'x':
            echo PHP_EOL;
            exit;
    }
    menu();
}

/**
 * target selection menu
 */
function selectTarget()
{
    global $target;
    $certs = ClientShim::getListeners();
    foreach ($certs as $k => $c) {
        echo "$k:  $c", PHP_EOL;
    }
    echo "X:  Cancel and go back", PHP_EOL;
    $choice = prompt("Please select a listener from the list above", $target,
        array_merge(array_keys($certs), array('X','x')));
    if (strtoupper($choice) === 'X') return;
    $target = $certs[$choice];
    return;
}

/**
 * generate the calls
 */
function generate()
{
    global $target;
    if (empty($target)) {
        echo "You must first select a target", PHP_EOL;
        selectTarget();
    }
    $requests = prompt("How many requests would you like to generate? [1 - 10]",
        null, array(1,2,3,4,5,6,7,8,9,10));
    $errors = promptYN("Generate requests with errors?", 'N');
    $result = ClientShim::run($target, $requests, $errors);
    foreach($result as $row) {
        print_r(json_decode($row['data']));
    }
    $continue = prompt("Hit enter to continue or X to exit", null, null, true);
    if (!empty($continue) && strtoupper($continue) == 'X') {
        echo PHP_EOL;
        exit;
    }
}

/**
 * clear the screen
 */
function clearScreen()
{
    global $isWin;
    if ($isWin) passthru('cls');
    else passthru('clear');
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
function prompt($text, $default = null, $allowed = null,
    $nullok = false, $errmessage = null)
{
    $pr = (!empty($default)) ? " [$default] > " : ' > ';
    echo $text, $pr;
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
            echo $errmessage;
            return prompt($text, $default, $allowed, $nullok, $errmessage);
        }
    } elseif (!empty($allowed) && is_string($allowed)) {
        if (preg_match($allowed, $ret)) return $ret;
        else {
            echo $errmessage;
            return prompt($text, $default, $allowed, $nullok, $errmessage);
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
function promptYN($text, $default = null, $errmessage = null)
{
    $pr = (!empty($default)) ? " [$default] > " : ' > ';
    echo $text . ' (Y/N)', $pr;
    $handle = fopen ("php://stdin","r");
    $line = fgets($handle);
    $ret = trim($line);
    fclose($handle);
    if (empty($ret) && $default) $ret = $default;
    $ret = strtoupper($ret);
    if (empty($ret) || !in_array($ret, array('Y', 'N'))) {
        echo 'Error: please indicate Y for yes or N for no';
        return promptYN($text, $default, $allowed, $nullok, $errmessage);
    }
    return $ret == 'Y' ? 1 : 0;
}

menu();
