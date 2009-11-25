<?php
require_once("sso.php");

$sso = new SingleSignOn_Broker();

if (!empty($_GET['logout'])) {
    $sso->logout();
} elseif ($sso->getInfo() || ($_SERVER['REQUEST_METHOD'] == 'POST' && $sso->login())) {
    header("Location: index.php", true, 303);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') $errmsg = "Login failed";

?>

<html>
	<head>
		<title>Single Sign-On demo (<?= $sso->broker ?>) - Login</title>
	</head>
	<body>
		<h1>Single Sign-On demo - Login</h1>
		<h2><?= $sso->broker ?></h2>
		
		<? if (isset($errmsg)): ?><div style="color:red"><?= $errmsg ?></div><? endif; ?>
		<form action="login.php" method="POST">
    		<table>
    			<tr><td>Username</td><td><input type="text" name="username" /></td></tr>
    			<tr><td>Password</td><td><input type="password" name="password" /></td></tr>
    			<tr><td></td><td><input type="submit" value="Login" /></td></tr>
    		</table>
    	</form>
	</body>
</html>
