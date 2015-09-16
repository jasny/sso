<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/SSOTestServer.php';

if (realpath($_SERVER["SCRIPT_FILENAME"]) == realpath(__FILE__) && isset($_REQUEST['command'])) {
    $sso = new SSOTestServer();
    $sso->$_REQUEST['command']();
} else {
    error_log('Unkown command');
    header("HTTP/1.1 406 Not Acceptable");
    header('Content-type: application/json; charset=UTF-8');

    echo "{error: 'Uknown command'}";
}
