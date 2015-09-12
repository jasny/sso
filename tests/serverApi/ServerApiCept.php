<?php
$token = 'hello_world';
$broker = "ServerApi";
$checksum = 'fe3892f0d85b3b92bdda31cbbf993ace';
$password = 'admin';
$username = 'admin';

$I = new ServerApiTester($scenario);
$I->defaultArgs = [
    'token' => $token,
    'broker' => $broker, 'checksum' => $checksum,
    'PHPSESSID' => 'SSO-ServerApi-hello_world-ec31fb7dff02625359acc8d1bd6d9dc0'
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