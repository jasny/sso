<?php

declare(strict_types=1);

namespace Jasny\SSO\Broker;

/**
 * Use global $_COOKIES and setcookie().
 *
 * @codeCoverageIgnore
 */
class GlobalCookies implements CookiesInterface
{
    /** @var int */
    protected $ttl;

    /** @var string */
    protected $path;

    /** @var string */
    protected $domain;

    /** @var bool */
    protected $secure;

    /**
     * Cookies constructor.
     *
     * @param int    $ttl     Cookie TTL in seconds
     * @param string $path
     * @param string $domain
     * @param bool   $secure
     */
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
    public function set(string $name, $value): void
    {
        $success = setcookie($name, $value, time() + $this->ttl, $this->domain, $this->path, $this->secure, true);

        if (!$success) {
            throw new \RuntimeException("Failed to set cookie '$name'");
        }
    }

    /**
     * @inheritDoc
     */
    public function clear(string $name): void
    {
        setcookie($name, '', 1, $this->domain, $this->path, $this->secure, true);
    }

    /**
     * @inheritDoc
     */
    public function get(string $name)
    {
        return $_COOKIE[$name] ?? null;
    }
}
