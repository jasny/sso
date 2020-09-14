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

// Get the user info from the SSO server via the API.
try {
    $userInfo = $broker->request('GET', '/api/info.php');
} catch (\RuntimeException $exception) {
    require __DIR__ . '/error.php';
    exit();
}

?>
<!doctype html>
<html>
    <head>
        <title><?= $broker->getBrokerId() ?> &mdash; Single Sign-On demo</title>

        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,300italic,700,700italic">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/milligram/1.4.1/milligram.css">
    </head>
    <body>
        <div class="container">
            <h1>Single Sign-On demo <small>(Broker: <?= $broker->getBrokerId() ?>)</small></h1>

            <?php if ($userInfo === null) : ?>
                <h3>Logged out</h3>
                <a id="login" class="button" href="login.php">Login</a>
            <?php else : ?>
                <h3>Logged in</h3>
                <pre><?= json_encode($userInfo, JSON_PRETTY_PRINT); ?></pre>

                <a id="logout" class="button" href="logout.php">Logout</a>
            <?php endif ?>
        </div>
    </body>
</html>
