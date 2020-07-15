<?php

/**
 * API endpoint to get the user info.
 * If you don't have a method to authenticate users, consider [jasny/auth](https://github.com/jasny/auth).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

use Jasny\SSO\Server\Server;
use Desarrolla2\Cache\File as FileCache;

// Config contains the user and broker info
$config = require '../config.php';

// Instantiate the SSO server.
$ssoServer = new Server(
    fn($id) => $config['brokers'][$id] ?? null,  // Callback to get the broker secret. You might fetch this from DB.
    new FileCache(),                             // Any PSR-16 compatible cache
);

// Start the session using the broker bearer token (rather than a session cookie).
$ssoServer->startBrokerSession();

// No user is logged in; respond with a 401
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    header('Content-Type: text/plain');
    echo "User not logged in";
    exit();
}

// Output user info as JSON.
$info = ['username' => $username] + $config['users'][$username];
unset($info['password']);

header('Content-Type: application/json');
echo json_encode($info);
