<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/SSOTestServer.php';

$sso = new SSOTestServer();
$request = isset($_REQUEST['command']) ? $_REQUEST['command'] : null;

if (!$request || !method_exists($sso, $request)) {
    error_log('Unkown command');
    header("HTTP/1.1 406 Not Acceptable");
    header('Content-type: application/json; charset=UTF-8');

    echo "{error: 'Uknown command'}";
    die;
}

$sso->$request();

