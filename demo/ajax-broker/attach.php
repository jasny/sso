<?php

declare(strict_types=1);

use Jasny\SSO\Broker\Broker;

require_once __DIR__ . '/../../vendor/autoload.php';

// Configure the broker.
$broker = new Broker(
    getenv('SSO_SERVER') . '/attach.php',
    getenv('SSO_BROKER_ID'),
    getenv('SSO_BROKER_SECRET')
);

$jsCallback = $_GET['callback'];

// Already attached
if ($broker->isAttached()) {
    echo $jsCallback . '(null, 200)';
}

// Attach through redirect if the client isn't attached yet.
$url = $broker->getAttachUrl(['callback' => $jsCallback]);
header("Location: $url", true, 303);
