<?php

/**
 * API endpoint to get the user info.
 * If you don't have a method to authenticate users, consider [jasny/auth](https://github.com/jasny/auth).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

// Instantiate the SSO server and start the broker session
require __DIR__ . '/../include/start_broker_session.php';

// No user is logged in; respond with a 204 No content
if (!isset($_SESSION['user'])) {
    http_response_code(204);
    exit();
}

// Get the username from the session
$username = $_SESSION['user'];

// Read config with user info
$config = require __DIR__ . '/../include/config.php';

// Output user info as JSON.
$info = ['username' => $username] + $config['users'][$username];
unset($info['password']);

header('Content-Type: application/json');
echo json_encode($info);
