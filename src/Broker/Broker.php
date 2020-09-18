<?php

declare(strict_types=1);

namespace Jasny\SSO\Broker;

/**
 * Single sign-on broker.
 *
 * The broker lives on the website visited by the user. The broken doesn't have any user credentials stored. Instead it
 * will talk to the SSO server in name of the user, verifying credentials and getting user information.
 */
class Broker
{
    /**
     * URL of SSO server.
     * @var string
     */
    protected $url;

    /**
     * My identifier, given by SSO provider.
     * @var string
     */
    protected $broker;

    /**
     * My secret word, given by SSO provider.
     * @var string
     */
    protected $secret;

    /**
     * @var bool
     */
    protected $initialized = false;

    /**
     * Session token of the client.
     * @var string|null
     */
    protected $token;

    /**
     * Verification code returned by the server.
     * @var string|null
     */
    protected $verificationCode;

    /**
     * @var \ArrayAccess
     */
    protected $state;

    /**
     * Class constructor
     *
     * @param string $url     Url of SSO server
     * @param string $broker  My identifier, given by SSO provider.
     * @param string $secret  My secret word, given by SSO provider.
     */
    public function __construct(string $url, string $broker, string $secret)
    {
        if (!(bool)preg_match('~^https?://~', $url)) {
            throw new \InvalidArgumentException("Invalid SSO server URL '$url'");
        }

        if ((bool)preg_match('/\W/', $broker)) {
            throw new \InvalidArgumentException("The broker id must be alphanumeric");
        }

        $this->url = $url;
        $this->broker = $broker;
        $this->secret = $secret;

        $this->state = new Cookies();
    }

    /**
     * Get a copy with a different handler for the user state (like cookie or session).
     *
     * @param \ArrayAccess $handler
     * @return static
     */
    public function withTokenIn(\ArrayAccess $handler): self
    {
        if ($this->state === $handler) {
            return $this;
        }

        $clone = clone $this;
        $clone->state = $handler;

        return $clone;
    }

    /**
     * Get the broker identifier.
     */
    public function getBrokerId(): string
    {
        return $this->broker;
    }

    /**
     * Get information from cookie.
     */
    protected function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->token = $this->state[$this->getCookieName('token')];
        $this->verificationCode = $this->state[$this->getCookieName('verify')];
        $this->initialized = true;
    }

    /**
     * @return string|null
     */
    protected function getToken(): ?string
    {
        $this->initialize();

        return $this->token;
    }

    /**
     * @return string|null
     */
    protected function getVerificationCode(): ?string
    {
        $this->initialize();

        return $this->verificationCode;
    }

    /**
     * Get the cookie name.
     * The broker name is part of the cookie name. This resolves issues when multiple brokers are on the same domain.
     */
    protected function getCookieName(string $type): string
    {
        $brokerName = preg_replace('/[_\W]+/', '_', strtolower($this->broker));

        return "sso_{$type}_{$brokerName}";
    }

    /**
     * Generate session id from session key
     *
     * @throws NotAttachedException
     */
    public function getBearerToken(): ?string
    {
        $token = $this->getToken();
        $verificationCode = $this->getVerificationCode();

        if ($verificationCode === null) {
            throw new NotAttachedException("The client isn't attached to the SSO server for this broker. "
                . "Make sure that the '" . $this->getCookieName('verify') . "' cookie is set.");
        }

        return "SSO-{$this->broker}-{$token}-" . $this->generateChecksum("bearer:$verificationCode");
    }

    /**
     * Generate session token.
     */
    public function generateToken(): void
    {
        if ($this->getToken() !== null) {
            return;
        }

        $this->token = base_convert(bin2hex(random_bytes(32)), 16, 36);
        $this->state[$this->getCookieName('token')] = $this->token;
    }

    /**
     * Clears session token.
     */
    public function clearToken(): void
    {
        unset($this->state[$this->getCookieName('token')]);
        unset($this->state[$this->getCookieName('verify')]);

        $this->token = null;
        $this->verificationCode = null;
    }

    /**
     * Check if we have an SSO token.
     */
    public function isAttached(): bool
    {
        return $this->getVerificationCode() !== null;
    }

    /**
     * Get URL to attach session at SSO server.
     *
     * @param array<string,mixed> $params
     * @return string
     */
    public function getAttachUrl(array $params = []): string
    {
        $this->generateToken();

        $data = [
            'broker' => $this->broker,
            'token' => $this->getToken(),
            'checksum' => $this->generateChecksum('attach')
        ];

        return $this->url . "?" . http_build_query($data + $params);
    }

    /**
     * Verify attaching to the SSO server by providing the verification code.
     */
    public function verify(string $code): void
    {
        $this->initialize();

        if ($this->verificationCode === $code) {
            return;
        }

        if ($this->verificationCode !== null) {
            trigger_error("SSO attach already verified", E_USER_WARNING);
            return;
        }

        $this->verificationCode = $code;
        $this->state[$this->getCookieName('verify')] = $code;
    }

    /**
     * Generate checksum for a broker.
     */
    protected function generateChecksum(string $command): string
    {
        return base_convert(hash_hmac('sha256', $command . ':' . $this->token, $this->secret), 16, 36);
    }

    /**
     * Get the request url for a command
     *
     * @param string                     $path
     * @param array<string,mixed>|string $params   Query parameters
     * @return string
     */
    protected function getRequestUrl(string $path, $params = ''): string
    {
        $query = is_array($params) ? http_build_query($params) : $params;

        $base = $path[0] === '/'
            ? preg_replace('~^(\w+://[^/]+).*~', '$1', $this->url)
            : preg_replace('~/[^/]*$~', '', $this->url);

        return $base . '/' . ltrim($path, '/') . ($query !== '' ? '?' . $query : '');
    }


    /**
     * Send an HTTP request to the SSO server.
     *
     * @param string                     $method  HTTP method: 'GET', 'POST', 'DELETE'
     * @param string                     $path    Relative path
     * @param array<string,mixed>|string $data    Query or post parameters
     * @return mixed
     * @throws NotAttachedException
     */
    public function request(string $method, string $path, $data = '')
    {
        $bearer = $this->getBearerToken();
        $url = $this->getRequestUrl($path, $method === 'POST' ? '' : $data);

        $ch = curl_init($url);

        if ($ch === false) {
            throw new \RuntimeException("Failed to initialize a cURL session");
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer '. $bearer
        ]);

        if ($method === 'POST' && ($data !== [] && $data !== '')) {
            $post = is_string($data) ? $data : http_build_query($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        $response = (string)curl_exec($ch);

        return $this->handleResponse($ch, $response);
    }

    /**
     * Handle response of Curl request.
     *
     * @param resource $ch        Curl handler
     * @param string   $response
     * @return mixed
     */
    protected function handleResponse($ch, string $response)
    {
        if (curl_errno($ch) != 0) {
            throw new RequestException('Server request failed: ' . curl_error($ch));
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        list($contentType) = explode(';', curl_getinfo($ch, CURLINFO_CONTENT_TYPE));

        if ($httpCode === 201 || $httpCode === 401) {
            return null;
        }

        if ($contentType != 'application/json') {
            throw new RequestException(
                "Expected 'application/json' response, got '$contentType'",
                0,
                new RequestException($response, $httpCode)
            );
        }

        $data = json_decode($response, true);

        if ($httpCode === 403) {
            $this->clearToken();
            throw new NotAttachedException($data['error'] ?? $response, $httpCode);
        } elseif ($httpCode >= 400) {
            throw new RequestException($data['error'] ?? $response, $httpCode);
        }

        return $data;
    }
}
