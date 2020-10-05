<?php

declare(strict_types=1);

use Jasny\SSO\Broker\Broker;

require_once __DIR__ . '/functions.php';

// Configure the broker.
$broker = new Broker(
    getenv('SSO_SERVER'),
    getenv('SSO_BROKER_ID'),
    getenv('SSO_BROKER_SECRET')
);

// Handle error from SSO server
if (isset($_GET['sso_error'])) {
    require __DIR__ . '/../error.php';
    exit();
}

// Handle verification from SSO server
if (isset($_GET['sso_verify'])) {
    $broker->verify($_GET['sso_verify']);

    $url = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $redirectUrl = preg_replace('/sso_verify=\w+&|[\?&]sso_verify=\w+$/', '', $url);
    redirect($redirectUrl);
    exit();
}

// Attach through redirect if the client isn't attached yet.
if (!$broker->isAttached() || ($_GET['reattach'] ?? false)) {
    $returnUrl = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] .
        preg_replace('/reattach=1&?/', '', $_SERVER['REQUEST_URI']);
    $attachUrl = $broker->getAttachUrl(['return_url' => $returnUrl]);

    redirect($attachUrl);
    exit();
}

return $broker;
