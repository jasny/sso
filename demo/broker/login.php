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

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $credentials = [
            'username' => $_POST['username'],
            'password' => $_POST['password']
        ];

        $broker->request('POST', '/api/login.php', $credentials);

        redirect('index.php');
        exit();
    } catch (\RuntimeException $exception) {
        $error = $exception->getMessage();
    }
}

// Show the form in case of GET request
?>
<!doctype html>
<html>
    <head>
        <title><?= $broker->getBrokerId() ?> | Login (Single Sign-On demo)</title>

        <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,300italic,700,700italic">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/normalize/8.0.1/normalize.css">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/milligram/1.4.1/milligram.css">

        <style>
            .error {
                background: #fff3f3;
                border-left: 0.3rem solid #d00000;
                padding: 5px 5px 5px 10px;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Single Sign-On demo <small>(Broker: <?= $broker->getBrokerId() ?>)</small></h1>

            <?php if (isset($error)) : ?>
                <div class="error"><?= $error ?></div>
            <?php endif; ?>

            <form action="login.php" method="post">
                <label for="inputUsername">Username</label>
                <input type="text" name="username" id="inputUsername">

                <label for="inputPassword">Password</label>
                <input type="password" name="password" id="inputPassword">

                <button type="submit">Login</button>
            </form>
        </div>
    </body>
</html>
