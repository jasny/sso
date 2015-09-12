<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Broker.php';

$command = $_REQUEST['command'];
$broker = new Jasny\SSO\Broker('http://localhost:9000/examples/server/', 'BrokerApi', 'BrokerApi');

if (empty($_REQUEST['command'])) {
    header("Content-Type: application/json");
    header("HTTP/1.1 406 Not Acceptable");
    echo json_encode(['error' => 'Command not specified']);
    exit();
}
else if (realpath($_SERVER["SCRIPT_FILENAME"]) == realpath(__FILE__)) {
    error_log('executing: '. $_REQUEST['command']);
    try {
        $result = $broker->$_REQUEST['command']();
        header("Content-Type: application/json");
        echo json_encode($result);
    } catch (Exception $ex) {
        $errorCode = $ex->getCode();
        error_log('error code' . $errorCode);

        header("Content-Type: application/json");
        if ($errorCode == 401) header("HTTP/1.1 401 Unauthorized");
        if ($errorCode == 406) header("HTTP/1.1 406 Not Acceptable");

        echo json_encode(['error' => $ex->getMessage()]);
    }
}
else {
    error_log('nothing to execute');

    header("Content-Type: application/json");
    header("HTTP/1.1 406 Not Acceptable");
    echo json_encode(['error' => 'Command not supported']);
    exit();
}
