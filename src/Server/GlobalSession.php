<?php

declare(strict_types=1);

namespace Jasny\SSO\Server;

/**
 * Interact with the global session using PHP's session_* functions.
 *
 * @codeCoverageIgnore
 */
class GlobalSession implements SessionInterface
{
    /**
     * Options passed to session_start().
     * @var array<string,mixed>
     */
    protected $options;

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
        if ($id !== null) {
            session_id($id);
        }

        $started = session_start($this->options);

        if (!$started) {
            $err = error_get_last() ?? ['message' => 'Failed to start session'];
            throw new ServerException($err['message'], 500);
        }
    }

    /**
     * @inheritDoc
     */
    public function isActive(): bool
    {
        return session_status() === PHP_SESSION_ACTIVE;
    }
}
