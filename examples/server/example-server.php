<?php
session_save_path('/tmp/SSO1');
require_once __DIR__ . '/../../vendor/autoload.php';

class ExampleServer extends Jasny\SSO\Server
{
    private static $brokers = array (
        'Alice' => array('secret'=>"Bob"),
        'Greg' => array('secret'=>'Geraldo')
    );

    private static $users = array (
        'admin' => array(
            'fullname' => 'jackie',
            'email' => 'jackie@admin.com'
        )
    );

    protected function getBrokerInfo($broker)
    {
        return self::$brokers[$broker];
    }

    protected function checkLogin($username, $password)
    {
        return $username == 'admin' && $password == 'admin';
    }

    protected function getUserInfo($user)
    {
        return self::$users[$user];
    }
}
?>
