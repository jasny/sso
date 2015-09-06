<?php 
$I = new AcceptanceTester($scenario);
$I->wantTo('ensure that frontpage works');
$I->amOnPage('/');
$I->see('Single Sign-On demo - Login', 'h1');

$I->amOnPage('/login.php');
$I->seeCookie('PHPSESSID');
$I->submitForm('#login', [
    'username' => 'admin',
    'password' => 'admin'
]);

$I->amOnPage('/');
$I->see('Logged in', 'h3');
$I->click('#logout');


$I->amOnPage('/');
$I->dontSee('Logged in', 'h3');

$I->amOnPage('/login.php');
?>
