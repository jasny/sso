<?php

declare(strict_types=1);

namespace Jasny\SSO\Server;

/**
 * Interface to interact with sessions.
 */
interface SessionInterface
{
    /**
     * @see session_id()
     */
    public function getId(): string;

    /**
     * Start the session. Optionally with a specific session id.
     * @see session_start()
     *
     * @throws ServerException if session can't be started.
     */
    public function start(?string $id = null): void;

    /**
     * Check if a session is active. (status PHP_SESSION_ACTIVE)
     * @see session_status()
     */
    public function isActive(): bool;


    /**
     * Get session data.
     *
     * @param string $key
     * @return mixed
     */
    public function get(string $key);

    /**
     * Set session data.
     *
     * @param string $key
     * @param mixed  $value
     */
    public function set(string $key, $value): void;
}
