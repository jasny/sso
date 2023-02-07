<?php

namespace Jasny\SSO\Broker;

/**
 * Use global $_COOKIE and setcookie() to persist the client token.
 *
 * @implements \ArrayAccess<string,mixed>
 * @codeCoverageIgnore
 */
class Cookies implements \ArrayAccess
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
    public function __construct($ttl = 3600, $path = '', $domain = '', $secure = false)
    {
        $this->ttl = $ttl;
        $this->path = $path;
        $this->domain = $domain;
        $this->secure = $secure;
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($name, $value)
    {
        $success = setcookie($name, $value, time() + $this->ttl, $this->path, $this->domain, $this->secure, true);

        if (!$success) {
            throw new \RuntimeException("Failed to set cookie '$name'");
        }

        $_COOKIE[$name] = $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($name)
    {
        setcookie($name, '', 1, $this->path, $this->domain, $this->secure, true);
        unset($_COOKIE[$name]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($name)
    {
        return isset($_COOKIE[$name]) ? $_COOKIE[$name] : null;
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($name)
    {
        return isset($_COOKIE[$name]);
    }
}
