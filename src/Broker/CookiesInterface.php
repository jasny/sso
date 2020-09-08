<?php

declare(strict_types=1);

namespace Jasny\SSO\Broker;

/**
 * Interface to set and get cookies.
 */
interface CookiesInterface
{
    /**
     * Set a cookie.
     *
     * @param string $name
     * @param mixed  $value
     * @throws \RuntimeException
     */
    public function set(string $name, $value): void;

    /**
     * Clear cookie
     *
     * @param string $name
     */
    public function clear(string $name): void;

    /**
     * Get a cookie.
     *
     * @param string $name
     * @return mixed
     */
    public function get(string $name);
}
