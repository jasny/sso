<?php
namespace Jasny\SSO;

require_once __DIR__ . '/../vendor/autoload.php';

use Jasny\ValidationResult;
use Desarrolla2\Cache\Cache;
use Desarrolla2\Cache\Adapter\Memory;

class TestServer extends Server
{
    private static $brokers = array (
        'Alice' => array('secret'=>"Bob"),
        'Greg' => array('secret'=>'Geraldo'),
        'BrokerApi' => array('secret'=>'BrokerApi'),
        'ServerApi' => array('secret' => 'ServerApi')
    );

    private static $users = array (
        'admin' => array(
            'fullname' => 'jackie',
            'email' => 'jackie@admin.com'
        )
    );

    public function __construct()
    {
        parent::__construct();
    }

    protected function getBrokerInfo($broker)
    {
        return self::$brokers[$broker];
    }

    protected function authenticate($username, $password)
    {
        $result = new ValidationResult();

        if (!isset($username)) {
            return ValidationResult::error("username isn't set");
        } elseif (!isset($password)) {
            return ValidationResult::error("password isn't set");
        } elseif ($username != 'admin' || $password != 'admin') {
            return ValidationResult::error("Invalid credentials");
        }

        return $result;
    }

    protected function getUserInfo($user)
    {
        return self::$users[$user];
    }
}
