<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once 'MySSOServer.php';

$ssoServer = new MySSOServer();
$command = isset($_REQUEST['command']) ? $_REQUEST['command'] : null;

if (!$command || !method_exists($ssoServer, $command)) {
    header("HTTP/1.1 404 Not Found");
    header('Content-type: application/json; charset=UTF-8');
    
    echo json_encode(['error' => 'Unknown command']);
    exit();
}

$result = $ssoServer->$command();

