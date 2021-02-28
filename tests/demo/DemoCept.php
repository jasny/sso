<?php

/** @var \Codeception\Scenario $scenario */
$I = new DemoTester($scenario);
$I->wantTo("login at broker 1 and see I'm also logged in at broker 2");

// ---
$I->amGoingTo("login at Alice (broker 1)");

$I->amOnBroker(1);
$I->see('Alice');
$I->see('Logged out');

$I->click('Login');
$I->seeElement('form', ['action' => 'login.php']);
$I->submitForm('form', [
    'username' => 'john',
    'password' => 'john123'
]);

$I->see('Logged in');
$I->see('John Doe');
$I->see('john.doe@example.com');

// ---
$I->amGoingTo("visit Greg (broker 2)");
$I->expect("john to be logged in through SSO");

$I->amOnBroker(2);
$I->see('Greg');

$I->see('Logged in');
$I->see('John Doe');
$I->see('john.doe@example.com');

// ---
$I->amGoingTo("logout at Greg (broker 2)");

$I->amOnBroker(2);
$I->see('Greg');

$I->click('Logout');
$I->see('Logged out');

// ---
$I->amGoingTo("visit Alice (broker 1)");
$I->expect("john to be logged out through SSO");

$I->amOnBroker(1);
$I->see('Alice');

$I->see('Logged out');
