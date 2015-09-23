<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$broker = new Jasny\SSO\Broker(getenv('SSO_SERVER'), getenv('SSO_BROKER_ID'), getenv('SSO_BROKER_SECRET'));

if (empty($_REQUEST['command']) || !method_exists($broker, $_REQUEST['command'])) {
    header("Content-Type: application/json");
    header("HTTP/1.1 400 Bad Request");
    echo json_encode(['error' => 'Command not specified']);
    exit();
} 

try {
    $result = $broker->{$_REQUEST['command']}();
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    $result = ['error' => $e->getMessage()];
}

if (!$result) {
    http_response_code(204);
    exit();
}

header("Content-Type: application/json");
echo json_encode($result);
