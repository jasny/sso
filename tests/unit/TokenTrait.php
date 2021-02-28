<?php

declare(strict_types=1);

namespace Jasny\Tests\SSO;

/**
 * Traits for server tests.
 */
trait TokenTrait
{
    protected function generateChecksum(string $command, string $secret, string $token): string
    {
        return base_convert(hash_hmac('sha256', $command . ':' . $token, $secret), 16, 36);
    }

    protected function getVerificationCode(string $brokerId, string $token, string $sessionId): string
    {
        return base_convert(hash('sha256', $brokerId . $token . $sessionId), 16, 36);
    }

    protected function getBearerToken(string $broker, string $secret, string $token, string $sessionId): string
    {
        $code = $this->getVerificationCode($broker, $token, $sessionId);

        return "SSO-{$broker}-{$token}-" . $this->generateChecksum("bearer:$code", $secret, $token);
    }
}
