<?php

/**
 * Endpoint that allows the broker to logout via the API.
 *
 * You only need this if you want to allow the broker to login and logout, not when logging in and out should be done
 * via the UI of the server.
 *
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

// Clear the session user.
unset($_SESSION['user']);
