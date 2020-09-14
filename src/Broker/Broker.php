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
     * Session token of the client
     * @var string|null
     */
    protected $token;

    /**
     * @var CookiesInterface
     */
    protected $cookies;

    /**
     * Class constructor
     *
     * @param string $url     Url of SSO server
     * @param string $broker  My identifier, given by SSO provider.
     * @param string $secret  My secret word, given by SSO provider.
     */
    public function __construct(string $url, string $broker, string $secret)
    {
        if (!preg_match('~^https?://~', $url)) {
            throw new \InvalidArgumentException("Invalid SSO server URL '$url'");
        }

        if (preg_match('/\W/', $broker)) {
            throw new \InvalidArgumentException("The broker id must be alphanumeric");
        }

        $this->url = $url;
        $this->broker = $broker;
        $this->secret = $secret;

        $this->cookies = new GlobalCookies();
    }

    /**
     * Get a copy with a custom cookie handler.
     *
     * @param CookiesInterface $cookies
     * @return static
     */
    public function withCookies(CookiesInterface $cookies): self
    {
        if ($this->cookies === $cookies) {
            return $this;
        }

        $clone = clone $this;
        $clone->cookies = $cookies;

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
     * @return string|null
     */
    protected function getToken(): ?string
    {
        if (!$this->initialized) {
            $this->token = $this->cookies->get($this->getCookieName());
            $this->initialized = true;
        }

        return $this->token;
    }

    /**
     * Get the cookie name.
     *
     * The broker name is part of the cookie name. This resolves issues when multiple brokers are on the same domain.
     */
    protected function getCookieName(): string
    {
        return 'sso_token_' . preg_replace('/[_\W]+/', '_', strtolower($this->broker));
    }

    /**
     * Generate session id from session key
     *
     * @throws NotAttachedException
     */
    public function getBearerToken(): ?string
    {
        if ($this->getToken() === null) {
            throw new NotAttachedException("The client isn't attached to the SSO server for this broker. "
                . "Make sure that the '" . $this->getCookieName() . "' cookie is set.");
        }

        return "SSO-{$this->broker}-{$this->token}-" . $this->generateChecksum('bearer');
    }

    /**
     * Generate session token.
     */
    public function generateToken(): void
    {
        if ($this->getToken() !== null) {
            return;
        }

        $this->token = base_convert(bin2hex(random_bytes(16)), 16, 36);
        $this->cookies->set($this->getCookieName(), $this->token);
    }

    /**
     * Clears session token.
     */
    public function clearToken(): void
    {
        $this->cookies->clear($this->getCookieName());
        $this->token = null;
    }

    /**
     * Check if we have an SSO token.
     */
    public function isAttached(): bool
    {
        return $this->getToken() !== null;
    }

    /**
     * Get URL to attach session at SSO server.
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
     * Generate checksum for a broker.
     */
    protected function generateChecksum(string $command): string
    {
        return hash_hmac('sha256', $command . ':' . $this->token, $this->secret);
    }

    /**
     * Get the request url for a command
     *
     * @param string        $path
     * @param array|string  $params   Query parameters
     * @return string
     */
    protected function getRequestUrl(string $path, $params = ''): string
    {
        $query = is_array($params) ? http_build_query($params) : $params;

        $base = $path[0] === '/'
            ? preg_replace('~^(\w+://[^/]+).*~', '$1', $this->url)
            : preg_replace('~/[^/]*$~', '', $this->url);

        return $base . '/' . ltrim($path, '/') . ((string)$query !== '' ? '?' . $query : '');
    }


    /**
     * Send an HTTP request to the SSO server.
     *
     * @param string       $method  HTTP method: 'GET', 'POST', 'DELETE'
     * @param string       $path    Relative path
     * @param array|string $data    Query or post parameters
     * @return mixed
     * @throws NotAttachedException
     */
    public function request(string $method, string $path, $data = null)
    {
        $bearer = $this->getBearerToken();
        $url = $this->getRequestUrl($path, $method === 'POST' ? '' : $data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer '. $bearer
        ]);

        if ($method === 'POST' && !empty($data)) {
            $post = is_string($data) ? $data : http_build_query($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        $response = curl_exec($ch);

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
            throw new RequestException("Expected 'application/json' response, got '$contentType'");
        }

        $data = json_decode($response, true);

        if ($httpCode === 403) {
            $this->clearToken();
            throw new NotAttachedException($data['error'] ?: $response, $httpCode);
        } elseif ($httpCode >= 400) {
            throw new RequestException($data['error'] ?: $response, $httpCode);
        }

        return $data;
    }
}
