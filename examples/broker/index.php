<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$broker = new Jasny\SSO\Broker(getenv('SSO_SERVER_URL'), getenv('SSO_BROKER_ID'), getenv('SSO_BROKER_SECRET'));
$broker->attach();

$user = $broker->getUserInfo();

if (!$user) {
    header("Location: login.php", true, 307);
    exit;
}

?>
<!doctype html>
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
