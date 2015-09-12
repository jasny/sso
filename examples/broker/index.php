<?php
session_save_path('/tmp/SSO2');
require_once __DIR__ . '/../../vendor/autoload.php';

$broker = new Jasny\SSO\Broker('http://localhost:9000/examples/server/', 'Alice', 'Bob');
$broker->attach('http://localhost:9001/examples/broker/');
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