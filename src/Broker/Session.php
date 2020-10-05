<?php

declare(strict_types=1);

namespace Jasny\SSO\Broker;

/**
 * Use global $_SESSION to persist the client token.
 *
 * @implements \ArrayAccess<string,mixed>
 * @codeCoverageIgnore
 */
class Session implements \ArrayAccess
{
    /**
     * @inheritDoc
     */
    public function offsetSet($name, $value): void
    {
        $_SESSION[$name] = $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($name): void
    {
        unset($_SESSION[$name]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($name)
    {
        return $_SESSION[$name] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($name): bool
    {
        return isset($_SESSION[$name]);
    }
}
