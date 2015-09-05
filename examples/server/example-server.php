<?php
require_once $_SERVER['DOCUMENT_ROOT']. "/src/Server.php";

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