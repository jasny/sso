<?php

declare(strict_types=1);

namespace Jasny\SSO\Broker;

/**
 * Use global $_COOKIE and setcookie() to persist the client token.
 *
 * @implements \ArrayAccess<string,mixed>
 * @codeCoverageIgnore
 */
class Cookies8 implements \ArrayAccess
{
    /** @var int */
    protected int $ttl;

    /** @var string */
    protected string $path;

    /** @var string */
    protected string $domain;

    /** @var bool */
    protected bool $secure;

    public function __construct(int $ttl = 3600, string $path = '', string $domain = '', bool $secure = false)
    {
        $this->ttl = $ttl;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
    }

    /**
     * @inheritDoc
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($_COOKIE[$offset]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $_COOKIE[$offset] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $success = setcookie($offset, $value, time() + $this->ttl, $this->path, $this->domain, $this->secure, true);

        if (!$success) {
            throw new \RuntimeException("Failed to set cookie '$offset'");
        }

        $_COOKIE[$offset] = $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset(mixed $offset): void
    {
        setcookie($offset, '', 1, $this->path, $this->domain, $this->secure, true);
        unset($_COOKIE[$offset]);
    }
}
