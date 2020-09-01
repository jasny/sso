<?php

/**
 * Create a new SSO Server instance.
 */

declare(strict_types=1);

use Jasny\SSO\Server\Server;
use Desarrolla2\Cache\File as FileCache;
use Jasny\SSO\Server\ExceptionInterface as SsoException;

// Config contains the secret keys of the brokers for this demo.
$config = require __DIR__ . '/config.php';

// Instantiate the SSO server.
$ssoServer = new Server(
    function (string $id) use ($config) {
        return $config['brokers'][$id] ?? null;  // Callback to get the broker secret. You might fetch this from DB.
    },
    new FileCache(sys_get_temp_dir())            // Any PSR-16 compatible cache
);

// Start the session using the broker bearer token (rather than a session cookie).
try {
    $ssoServer->startBrokerSession();
} catch (SsoException $exception) {
    http_response_code($exception->getCode());
    header('Content-Type: application/json');
    echo json_encode(['error' => $exception->getMessage()]);
    exit();
}

return $ssoServer;
