<?php

/**
 * An example script for attaching the broker token to a user session.
 */

declare(strict_types=1);

use Jasny\SSO\Server\Server;
use Desarrolla2\Cache\File as FileCache;
use Jasny\SSO\Server\ExceptionInterface as SSOException;

require_once __DIR__ . '/../../vendor/autoload.php';

// Config contains the secret keys of the brokers for this demo.
$config = require __DIR__ . '/include/config.php';

// Instantiate the SSO server.
$ssoServer = (new Server(
    function (string $id) use ($config) {
        return $config['brokers'][$id] ?? null;  // Callback to get the broker secret. You might fetch this from DB.
    },
    new FileCache(sys_get_temp_dir())            // Any PSR-16 compatible cache
))->withLogger(new Loggy('SSO'));

try {
    // Attach the broker token to the user session. Uses query parameters from $_GET.
    $ssoServer->attach();
} catch (SSOException $exception) {
    // Something went wrong. Output the error as a 4xx or 5xx response.
    http_response_code($exception->getCode());
    header('Content-Type: text/plain');
    echo $exception;
    exit();
}

// The token is attached; output 'success'.

// In this demo we support multiple types of attaching the session. If you choose to support only one method,
// you don't need to detect the return type.

$returnType =
    (isset($_GET['return_url']) ? 'redirect' : null) ??
    (isset($_GET['callback']) ? 'jsonp' : null) ??
    (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false ? 'html' : null) ??
    (strpos($_SERVER['HTTP_ACCEPT'], 'image/') !== false ? 'image' : null) ??
    (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false ? 'json' : null) ??
    (isset($_GET['HTTP_REFERER']) ? 'redirect' : null);

switch ($returnType) {
    case 'json':
        header('Content-type: application/json');
        echo json_encode(['success' => 'attached']);
        break;

    case 'jsonp':
        header('Content-type: application/javascript');
        $data = json_encode(['success' => 'attached']);
        echo $_REQUEST['callback'] . "($data, 200);";
        break;

    case 'image':
        // Output a 1x1px transparent image
        header('Content-Type: image/png');
        echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZg'
            . 'AAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII=');
        break;

    case 'redirect':
        $url = $_GET['return_url'] ?? $_SERVER['HTTP_REFERER'];
        header('Location: ' . $url, true, 303);
        echo "You're being redirected to <a href='{$url}'>$url</a>";
        break;

    default:
        http_response_code(400);
        header('Content-Type: text/plain');
        echo "Unable to detect return type";
        break;
}
