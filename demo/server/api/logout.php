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

// Instantiate the SSO server and start the broker session
require __DIR__ . '/../include/start_broker_session.php';

// Clear the session user.
unset($_SESSION['user']);

// Done (no output)
http_response_code(201);
