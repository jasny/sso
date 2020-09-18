<?php

declare(strict_types=1);

use Jasny\SSO\Broker\Broker;

require_once __DIR__ . '/../../vendor/autoload.php';

// Configure the broker.
$broker = new Broker(
    getenv('SSO_SERVER'),
    getenv('SSO_BROKER_ID'),
    getenv('SSO_BROKER_SECRET')
);

// Set the verification cookie.
// Don't do this in JS using document.cookie, because an XSS vulnerability would grand access to the session.
$broker->verify($_POST['verify']);

http_response_code(204);
