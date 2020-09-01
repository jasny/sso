<?php

declare(strict_types=1);

use Jasny\SSO\Broker\Broker;

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/include/functions.php';

// Configure the broker.
$broker = new Broker(
    getenv('SSO_SERVER'),
    getenv('SSO_BROKER_ID'),
    getenv('SSO_BROKER_SECRET')
);

// Attach through redirect if the client isn't attached yet.
if (!$broker->isAttached()) {
    $returnUrl = (!empty($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $attachUrl = $broker->getAttachUrl(['return_url' => $returnUrl]);

    redirect($attachUrl);
    exit();
}

try {
    $broker->request('POST', 'api/logout.php');
} catch (\RuntimeException $exception) {
    require __DIR__ . '/error.php';
    exit();
}

redirect('index.php');
