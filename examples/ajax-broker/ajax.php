<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/src/Broker.php';

function send_error($message) {
    header("Content-Type: application/json");
    header("HTTP/1.1 406 Not Acceptable");
    echo '{error: "$message"}';
}

if (empty($_REQUEST['command'])) {
    send_error('command not specified');
    exit();
}
else if ($_REQUEST['command'] == 'on') {
    send_error('unsupported command');
    exit();
}

$command = $_REQUEST['command'];
$broker = new Jasny\SSO\Broker('http://localhost:9000/examples/server/', 'Alice', 'Bob');

if (!empty($_REQUEST['token'])) {
    $broker->token = $_REQUEST['token'];
}

if (realpath($_SERVER["SCRIPT_FILENAME"]) == realpath(__FILE__) && isset($_REQUEST['command'])) {
    error_log('executing: '. $_REQUEST['command']);
    try {
        $result = $broker->$_GET['command']();
    }
    catch (\Exception $ex) {
        $result = $ex->getMessage();
    }
}
else {
    error_log('nothing to execute');
}

header("Content-Type: application/json");
echo json_encode($result);
?>