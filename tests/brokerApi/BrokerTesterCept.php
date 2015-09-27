<?php
$token = 'hello_world';
$broker = "Api";
$password = 'admin';
$username = 'admin';

$I = new ServerApiTester($scenario);

$I->wantTo('login through broker and view user data');
$I->sendServerRequest('getUserInfo');
$I->seeResponseIsJson();
$I->seeResponseCodeIs(200);
$I->seeResponseEquals('null');

$I->sendServerRequest('attach');
$I->seeResponseIsJson();

$I->sendServerRequest('getUserInfo');
$I->seeResponseIsJson();
$I->seeResponseCodeIs(200);
$I->seeResponseEquals('null');


$I->sendServerRequest('login', [
    'password' => $username,
    'username' => $password
]);
$I->seeResponseCodeIs(200);
$I->seeResponseIsJson(['token' => $token]);

$I->sendServerRequest('getUserInfo');
$I->seeResponseCodeIs(200);
$I->seeResponseIsJson();
$I->seeResponseContainsJson([
    'fullname' => 'jackie',
    'email' => 'jackie@admin.com',
    'username' => 'admin'
]);

$I->sendServerRequest('detach');
$I->sendServerRequest('attach');

$I->sendServerRequest('getUserInfo');
$I->seeResponseCodeIs(200);
$I->seeResponseIsJson();
$I->seeResponseContainsJson([
    'fullname' => 'jackie',
    'email' => 'jackie@admin.com',
    'username' => 'admin'
]);

$I->sendServerRequest('logout');
$I->seeResponseCodeIs(200);
$I->seeResponseIsJson('null');