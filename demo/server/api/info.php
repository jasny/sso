<?php

/**
 * API endpoint to get the user info.
 * If you don't have a method to authenticate users, consider [jasny/auth](https://github.com/jasny/auth).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

// Instantiate the SSO server and start the broker session
require __DIR__ . '/../include/start_broker_session.php';

// No user is logged in; respond with a 401
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => "User not logged in"]);
    exit();
}

// Get the username from the session
$username = $_SESSION['user'];

// Output user info as JSON.
$info = ['username' => $username] + $config['users'][$username];
unset($info['password']);

header('Content-Type: application/json');
echo json_encode($info);
