<?php

declare(strict_types=1);

$brokerId = getenv('SSO_BROKER_ID');
$error = isset($exception) ? $exception->getMessage() : "Unknown error";

?>
<!doctype html>
<html>
    <head>
        <title>Single Sign-On demo (<?= $brokerId ?>)</title>

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
            <h1>Single Sign-On demo <small>(<?= $brokerId ?>)</small></h1>

            <div class="error">
                <?= $error ?>
            </div>
            
            <a href="/">Try again</a>
        </div>
    </body>
</html>
