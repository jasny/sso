<?php

declare(strict_types=1);

use Jasny\SSO\Broker\Broker;

require_once __DIR__ . '/../../vendor/autoload.php';

/** @var Broker $broker */
$broker = require_once __DIR__ . '/include/attach.php';

try {
    $broker->request('POST', 'api/logout.php');
} catch (\RuntimeException $exception) {
    require __DIR__ . '/error.php';
    exit();
}

redirect('index.php');
