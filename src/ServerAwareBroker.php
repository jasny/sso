<?php

namespace Jasny\SSO;

use GuzzleHttp\ClientInterface;

class ServerAwareBroker implements BrokerInterface
{
    /**
     * @var BrokerInterface
     */
    private $broker;

    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var string
     */
    private $healthCheckUrl;

    /**
     * ServerAwareBroker constructor.
     *
     * @param BrokerInterface $broker
     * @param ClientInterface $client
     * @param string          $healthCheckUrl
     */
    public function __construct(BrokerInterface $broker, ClientInterface $client, $healthCheckUrl)
    {
        $this->broker         = $broker;
        $this->client         = $client;
        $this->healthCheckUrl = $healthCheckUrl;
    }

    /**
     *{@inheritdoc}
     */
    public function generateToken()
    {
        $this->broker->generateToken();
    }

    /**
     *{@inheritdoc}
     */
    public function clearToken()
    {
        $this->broker->clearToken();
    }

    /**
     *{@inheritdoc}
     */
    public function isAttached()
    {
        return $this->broker->isAttached();
    }

    /**
     *{@inheritdoc}
     */
    public function getAttachUrl($params = [])
    {
        return $this->broker->getAttachUrl($params);
    }

    /**
     *{@inheritdoc}
     */
    public function attach($returnUrl = null)
    {
        //check if the server is alive
        $this->client->request('GET', $this->healthCheckUrl);

        $this->broker->attach($returnUrl);
    }

    /**
     *{@inheritdoc}
     */
    public function login($username = null, $password = null)
    {
        return $this->broker->login($username, $password);
    }

    /**
     *{@inheritdoc}
     */
    public function logout()
    {
        $this->broker->logout();
    }

    /**
     *{@inheritdoc}
     */
    public function getUserInfo()
    {
        return $this->broker->getUserInfo();
    }
}
