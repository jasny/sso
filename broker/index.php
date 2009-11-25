<?php
require_once("sso.php");

$sso = new SingleSignOn_Broker();
$user = $sso->getInfo();

if (!$user) {
    header("Location: login.php", true, 307);
    exit;
}

?>

<html>
	<head>
		<title>Single Sign-On demo (<?= $sso->broker ?>)</title>
	</head>
	<body>
		<h1>Single Sign-On demo</h1>
		<h2><?= $sso->broker ?></h2>
		
		<dl>
			<? foreach($user as $key=>$value) : ?>		
				<dt><?= $key ?></dt><dd><?= $value ?></dd>
			<? endforeach; ?>
		</dl>
		<a href="login.php?logout=1">Logout</a> 
	</body>
</html>
