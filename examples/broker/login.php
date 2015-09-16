<?php

require_once __DIR__ . '/../../vendor/autoload.php';

$broker = new Jasny\SSO\Broker(getenv('SSO_SERVER_URL'), getenv('SSO_BROKER_ID'), getenv('SSO_BROKER_SECRET'));
$broker->attach();

if (!empty($_GET['logout'])) {
    $broker->logout();
} elseif ($broker->getUserInfo()
          || ($_SERVER['REQUEST_METHOD'] == 'POST' && $broker->login($_POST['username'], $_POST['password']))) {
    header("Location: index.php", true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') $errmsg = "Login failed";

?>
<!doctype html>
<html>
	<head>
		<title>Single Sign-On demo (<?= $broker->broker ?>) - Login</title>
	</head>
	<body>
		<h1>Single Sign-On demo - Login</h1>
		<h2><?= $broker->broker ?></h2>
		
		<? if (isset($errmsg)): ?><div style="color:red"><?= $errmsg ?></div><? endif; ?>
		<form id="login" action="login.php" method="POST">
    		<table>
    			<tr><td>Username</td><td><input type="text" name="username" /></td></tr>
    			<tr><td>Password</td><td><input type="password" name="password" /></td></tr>
    			<tr><td></td><td><input type="submit" value="Login" /></td></tr>
    		</table>
    	</form>
	</body>
</html>
