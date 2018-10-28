<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once 'MySSOServer.php';

header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
header('Access-Control-Allow-Headers: Authorization');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit();
}

$ssoServer = new MySSOServer();
$command = isset($_REQUEST['command']) ? $_REQUEST['command'] : null;
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if (!$isAjax && !($command && method_exists($ssoServer, $command))) {
    header("HTTP/1.1 404 Not Found");
    header('Content-type: application/json; charset=UTF-8');

    echo json_encode(['error' => 'Unknown command']);
    exit();
}

$result = $ssoServer->$command();

