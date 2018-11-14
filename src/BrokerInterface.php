<?php

namespace Jasny\SSO;

interface BrokerInterface
{
    /**
     * Generate session token
     */
    public function generateToken();


    /**
     * Clears session token
     */
    public function clearToken();

    /**
     * Check if we have an SSO token.
     *
     * @return boolean
     */
    public function isAttached();

    /**
     * Get URL to attach session at SSO server.
     *
     * @param array $params
     * @return string
     */
    public function getAttachUrl($params = []);

    /**
     * Attach our session to the user's session on the SSO server.
     *
     * @param string|true $returnUrl  The URL the client should be returned to after attaching
     */
    public function attach($returnUrl);

    /**
     * Log the client in at the SSO server.
     *
     * Only brokers marked trused can collect and send the user's credentials. Other brokers should omit $username and
     * $password.
     *
     * @param string $username
     * @param string $password
     * @return array  user info
     * @throws Exception if login fails eg due to incorrect credentials
     */
    public function login($username = null, $password = null);

    /**
     * Logout at sso server.
     */
    public function logout();

    /**
     * Get user information.
     *
     * @return object|null
     */
    public function getUserInfo();
}
