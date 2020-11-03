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
    $verificationCode = $ssoServer->attach();
    $error = null;
} catch (SSOException $exception) {
    $verificationCode = null;
    $error = ['code' => $exception->getCode(), 'message' => $exception->getMessage()];
}

// The token is attached; output 'success'.

// In this demo we support multiple types of attaching the session. If you choose to support only one method,
// you don't need to detect the return type.

$returnType =
    (isset($_GET['return_url']) ? 'redirect' : null) ??
    (isset($_GET['callback']) ? 'jsonp' : null) ??
    (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false ? 'html' : null) ??
    (strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false ? 'json' : null);

switch ($returnType) {
    case 'json':
        header('Content-type: application/json');
        http_response_code($error['code'] ?? 200);
        echo json_encode($error ?? ['verify' => $verificationCode]);
        break;

    case 'jsonp':
        $callback = $_GET['callback'];
        if (!preg_match('/^[a-z_]\w*$/i', $callback)) {
            http_response_code(400);
            header('Content-Type: text/plain');
            echo "JSONP callback must be a valid js function name";
            break;
        }
    
        header('Content-type: application/javascript');
        $data = json_encode($error ?? ['verify' => $verificationCode]);
        $responseCode = $error['code'] ?? 200;
        echo "{$callback}($data, $responseCode);";
        break;

    case 'redirect':
        $query = isset($error) ? 'sso_error=' . $error['message'] : 'sso_verify=' . $verificationCode;
        $url = $_GET['return_url'] . (strpos($_GET['return_url'], '?') === false ? '?' : '&') . $query;
        header('Location: ' . $url, true, 303);
        echo "You're being redirected to <a href='{$url}'>$url</a>";
        break;

    default:
        http_response_code(400);
        header('Content-Type: text/plain');
        echo "Missing 'return_url' query parameter";
        break;
}
