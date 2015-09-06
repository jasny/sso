<?php
require_once __DIR__ . '/../../vendor/autoload.php';

class ServerTest extends \Codeception\TestCase\Test
{
    /**
     * @var \UnitTester
     */
    protected $tester;

    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests
    public function testMe()
    {
        $broker = new Jasny\SSO\Broker('http://localhost:9000/examples/server/', 'Alice', 'Bob');

    }
}
