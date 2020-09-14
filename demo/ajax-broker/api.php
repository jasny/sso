<?php

declare(strict_types=1);

use Jasny\SSO\Broker\Broker;

require_once __DIR__ . '/../../vendor/autoload.php';

// Configure the broker.
$broker = new Broker(
    getenv('SSO_SERVER') . '/attach.php',
    getenv('SSO_BROKER_ID'),
    getenv('SSO_BROKER_SECRET')
);

try {
    $path = '/api/' . $_GET['command'] . '.php';
    $result = $broker->request($_SERVER['REQUEST_METHOD'], $path, $_POST);
} catch (Exception $e) {
    $status = $e->getCode() ?: 500;
    $result = ['error' => $e->getMessage()];
}

// REST
if (!$result) {
    http_response_code(204);
} else {
    http_response_code(isset($status) ? $status : 200);
    header("Content-Type: application/json");
    echo json_encode($result);
}
