<?php

/**
 * Endpoint that allows the broker to ask the user for credentials and login via the API.
 *
 * You only need this if you want to allow the broker to login and logout, not when logging in and out should be done
 * via the UI of the server.
 *
 * If you don't have a method to authenticate users, consider [jasny/auth](https://github.com/jasny/auth).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../vendor/autoload.php';

// Instantiate the SSO server and start the broker session
require __DIR__ . '/../include/start_broker_session.php';

// Take the username and password from the POST params.
$username = $_POST['username'];
$password = $_POST['password'];

// Authenticate the user.
if (!isset($config['users'][$username]) || !password_verify($password, $config['users'][$username]['password'])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => "Invalid credentials"]);
    exit();
}

// Store the current user in the session.
$_SESSION['user'] = $username;

// Output user info as JSON.
$info = ['username' => $username] + $config['users'][$username];
unset($info['password']);

header('Content-Type: application/json');
echo json_encode($info);
