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
        $this->options = $options + ['cookie_samesite' => 'None', 'cookie_secure' => true];
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
    public function start(): void
    {
        $started = session_status() !== PHP_SESSION_ACTIVE
            ? session_start($this->options)
            : true;

        if (!$started) {
            $err = error_get_last() ?? ['message' => 'Failed to start session'];
            throw new ServerException($err['message'], 500);
        }

        // Session shouldn't be empty when resumed.
        $_SESSION['_sso_init'] = 1;
    }

    /**
     * @inheritDoc
     */
    public function resume(string $id): void
    {
        session_id($id);
        $started = session_start($this->options);

        if (!$started) {
            $err = error_get_last() ?? ['message' => 'Failed to start session'];
            throw new ServerException($err['message'], 500);
        }

        if ($_SESSION === []) {
            session_abort();
            throw new BrokerException("Session has expired. Client must attach with new token.", 401);
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
