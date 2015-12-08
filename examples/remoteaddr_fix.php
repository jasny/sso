<?php

// This file set $_SERVER['REMOTE_ADDR'] and should be used when testing a
// broker on localhost with a remote server.
//
// Use this file by adding `-d auto_prepend_file=../remoteaddr_fix.php`.

$_SERVER['REMOTE_ADDR'] = file_get_contents("http://ipecho.net/plain");

