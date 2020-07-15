<?php

declare(strict_types=1);

namespace Jasny\SSO\Server;

/**
 * Interact with session using $_SESSION and PHP's session_* functions.
 */
class GlobalSession implements SessionInterface
{
    /** Options passed to session_start(). */
    protected array $options;

    /**
     * Class constructor.
     *
     * @param array<string,mixed> $options  Options passed to session_start().
     */
    public function __construct(array $options = [])
    {
        $this->options = $options;
    }

    /**
     * @inheritDoc
     */
    public function getId(): string
    {
        return session_id();
    }

    /**
     * @inheritDoc
     */
    public function start(?string $id = null): void
    {
        $started = ($id === null || (bool)session_id($id))
            && session_start($this->options);

        if (!$started) {
            throw new ServerException(error_get_last()['message'], 500);
        }
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }


    /**
     * @inheritDoc
     */
    public function get(string $key)
    {
        return $_SESSION[$key] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }
}
