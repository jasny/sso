<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$broker = new Jasny\SSO\Broker(getenv('SSO_SERVER'), getenv('SSO_ID'), getenv('SSO_TOKEN'));
$broker->attach('http://' . $_SERVER['HTTP_HOST']);
$user = $broker->getUserInfo();

if (!$user) {
    header("Location: login.php", true, 307);
    exit;
}

?>

<html>
	<head>
		<title>Single Sign-On demo (<?= $broker->broker ?>)</title>
	</head>
	<body>
		<h1>Single Sign-On demo</h1>
		<h2><?= $broker->broker ?></h2>
    <?php if ($user) : ?>
    <h3>Logged in</h3>
    <?php
endif ?>

		<dl>
			<?php foreach ($user as $key => $value) : ?>
				<dt><?= $key  ?></dt><dd><?= $value ?></dd>
            <?php
endforeach; ?>
		</dl>
		<a id="logout" href="login.php?logout=1">Logout</a>
	</body>
</html>
