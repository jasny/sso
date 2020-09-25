<?php

declare(strict_types=1);

namespace Jasny\SSO\Server;

/**
 * Interface to start a session.
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
}
