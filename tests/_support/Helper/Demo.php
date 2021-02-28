<?php

declare(strict_types=1);

namespace Helper;

use Codeception\Module\PhpBrowser;
use PhpBuiltInServer;

// here you can define custom actions
// all public methods declared in helper class will be available in $I

class Demo extends \Codeception\Module
{
    protected $server;
    protected $broker1;
    protected $broker2;

    /**
     * Hook runs before any test of the suite is run
     *
     * @param array $settings
     */
    public function _beforeSuite($settings = [])
    {
        parent::_beforeSuite($settings);

        $this->server = new PhpBuiltInServer(ROOT_DIR . '/demo/server/', 8200);

        $this->broker1 = new PhpBuiltInServer(
            ROOT_DIR . '/demo/broker/',
            8201,
            [
                'SSO_SERVER' => 'http://localhost:8200/attach.php',
                'SSO_BROKER_ID' => 'Alice',
                'SSO_BROKER_SECRET' => '8iwzik1bwd'
            ]
        );

        $this->broker2 = new PhpBuiltInServer(
            ROOT_DIR . '/demo/broker/',
            8202,
            [
                'SSO_SERVER' => 'http://localhost:8200/attach.php',
                'SSO_BROKER_ID' => 'Greg',
                'SSO_BROKER_SECRET' => '7pypoox2pc'
            ]
        );
    }

    /**
     * Hook runs after all test of the suite is run
     */
    public function _afterSuite()
    {
        $this->server = null;
        $this->broker1 = null;
        $this->broker2 = null;

        parent::_afterSuite();
    }

    /**
     * Set URL of broker as base host.
     *
     * @param int $nr
     */
    public function amOnBroker(int $nr): void
    {
        if ($nr < 1 || $nr > 2) {
            throw new \Exception("Invalid broker number $nr");
        }

        $port = $nr + 8200;

        /** @var PhpBrowser $phpBrowser */
        $phpBrowser =$this->getModule('PhpBrowser');

        $phpBrowser->amOnUrl("http://localhost:$port");
    }
}
