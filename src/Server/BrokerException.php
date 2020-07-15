<?php

declare(strict_types=1);

namespace Jasny\SSO\Server;

/**
 * Exception that's thrown if request from broker is invalid.
 * Should result in an HTTP 4xx response.
 */
class BrokerException extends \RuntimeException implements ExceptionInterface
{
}
