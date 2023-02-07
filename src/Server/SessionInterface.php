<?php

namespace Jasny\SSO\Server;

/**
 * Interface to start a session.
 */
interface SessionInterface
{
    /**
     * @see session_id()
     */
    public function getId();

    /**
     * Start a new session.
     * @see session_start()
     *
     * @throws ServerException if session can't be started.
     */
    public function start();

    /**
     * Resume an existing session.
     *
     * @throws ServerException if session can't be started.
     * @throws BrokerException if session is expired
     */
    public function resume($id);

    /**
     * Check if a session is active. (status PHP_SESSION_ACTIVE)
     * @see session_status()
     */
    public function isActive();
}
