<?php

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
    public function offsetSet($name, $value)
    {
        $_SESSION[$name] = $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($name)
    {
        unset($_SESSION[$name]);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($name)
    {
        return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($name)
    {
        return isset($_SESSION[$name]);
    }
}
