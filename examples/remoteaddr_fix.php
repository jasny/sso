<?php

// This file set $_SERVER['REMOTE_ADDR'] and should be used when testing a
// broker on localhost with a remote server.
//
// Use this file by adding `-d auto_prepend_file=../remoteaddr_fix.php`.

$externalContent = file_get_contents('http://ip4.me/');
preg_match('/\b(\d{1,3}\.){3}\d{1,3}\b/', $externalContent, $m);

$_SERVER['REMOTE_ADDR'] = $m[0];

