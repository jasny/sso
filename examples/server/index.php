<?php
require_once("example-server.php");

// Execute controller command
if (realpath($_SERVER["SCRIPT_FILENAME"]) == realpath(__FILE__) && isset($_REQUEST['command'])) {
    error_log('executing: '. $_REQUEST['command']);
    $sso = new ExampleServer();
    $sso->$_GET['command']();
}
else {
    error_log('nothing to execute');
}
?>