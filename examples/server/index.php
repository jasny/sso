<?php
require_once __DIR__ . '/../../vendor/autoload.php';
session_save_path(__DIR__ .'/../../server-sessions');

if (realpath($_SERVER["SCRIPT_FILENAME"]) == realpath(__FILE__) && isset($_REQUEST['command'])) {
    $sso = new Jasny\SSO\TestServer();
    $sso->$_REQUEST['command']();
} else {
    error_log('Unkown command');
    header("HTTP/1.1 406 Not Acceptable");
    header('Content-type: application/json; charset=UTF-8');

    echo "{error: 'Uknown command'}";
}
