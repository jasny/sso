<?php
require_once $_SERVER['DOCUMENT_ROOT']. '/src/Broker.php';

$broker = new Jasny\SSO\Broker('http://localhost:9000/examples/server/', 'Alice', 'Bob');
$result = array(
    'token' => $broker.getToken();
);

header("Content-Type: application/json");
echo json_encode($result);
?>