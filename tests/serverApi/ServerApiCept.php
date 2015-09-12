<?php
$token = 'hello_world';
$broker = "ServerApi";
$checksum = '514ee01d6ed9a88908790683c203e2ac';
$password = 'admin';
$username = 'admin';

$I = new ServerApiTester($scenario);
$I->defaultArgs = [
    'token' => $token,
    'broker' => $broker, 'checksum' => $checksum,
    'PHPSESSID' => 'SSO-ServerApi-hello_world-0949c41dd2c747f8e1d4bfd85dd2f4d8'
];

$I->wantTo('attach session and view user info and logout');
$I->sendServerRequest('attach', ['PHPSESSID' => '']);
$I->seeResponseIsJson();
$I->seeResponseCodeIs(200);
$I->seeResponseContainsJson(['token' => $token]);

$I->sendServerRequest('userInfo');
$I->seeResponseCodeIs(401);
$I->seeResponseIsJson();
$I->seeResponseContainsJson(['error' => 'Not logged in']);

$I->sendServerRequest('login', [
    'password' => 'wrong',
    'username' => 'wrong'
]);

$I->seeResponseCodeIs(401);
$I->seeResponseIsJson(['error' => 'Incorrect credentials']);

$I->sendServerRequest('login', [
    'password' => $username,
    'username' => $password
]);
$I->seeResponseCodeIs(200);
$I->seeResponseIsJson(['token' => $token]);

$I->sendServerRequest('userInfo');
$I->seeResponseCodeIs(200);
$I->seeResponseIsJson();
$I->seeResponseContainsJson([
    'fullname' => 'jackie',
    'email' => 'jackie@admin.com',
    'username' => 'admin'
]);

$I->sendServerRequest('logout');
$I->seeResponseCodeIs(200);
$I->seeResponseIsJson();

$I->sendServerRequest('userInfo');
$I->seeResponseCodeIs(401);
$I->seeResponseIsJson();
$I->seeResponseContainsJson(['error' => 'Not logged in']);