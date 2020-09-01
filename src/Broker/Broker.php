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
     * Url of SSO server
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
     * Session token of the client
     * @var string|null
     */
    protected $token;

    /**
     * Cookie lifetime
     * @var int
     */
    protected $cookieTtl;

    /**
     * Class constructor
     *
     * @param string $url        Url of SSO server
     * @param string $broker     My identifier, given by SSO provider.
     * @param string $secret     My secret word, given by SSO provider.
     * @param int    $cookieTtl  Cookie lifetime in seconds
     */
    public function __construct(string $url, string $broker, string $secret, int $cookieTtl = 3600)
    {
        $this->url = $url;
        $this->broker = $broker;
        $this->secret = $secret;
        $this->cookieTtl = $cookieTtl;

        $this->token = $_COOKIE[$this->getCookieName()] ?? null;
    }

    /**
     * Get the broker identifier.
     */
    public function getBrokerId(): string
    {
        return $this->broker;
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
     */
    protected function generateBearerToken(): ?string
    {
        if ($this->token === null) {
            return null;
        }

        return "SSO-{$this->broker}-{$this->token}-" . $this->generateChecksum('bearer');
    }

    /**
     * Generate session token
     */
    public function generateToken()
    {
        if (isset($this->token)) {
            return;
        }

        $this->token = base_convert(bin2hex(random_bytes(16)), 16, 36);

        setcookie($this->getCookieName(), $this->token, time() + $this->cookieTtl, '/');
    }

    /**
     * Clears session token
     */
    public function clearToken()
    {
        setcookie($this->getCookieName(), null, 1, '/');
        $this->token = null;
    }

    /**
     * Check if we have an SSO token.
     */
    public function isAttached(): bool
    {
        return $this->token !== null;
    }

    /**
     * Get URL to attach session at SSO server.
     */
    public function getAttachUrl(array $params = []): string
    {
        $this->generateToken();

        $data = [
            'broker' => $this->broker,
            'token' => $this->token,
            'checksum' => $this->generateChecksum('attach')
        ] + $_GET;

        return $this->url . "/attach.php?" . http_build_query($data + $params);
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
    protected function getRequestUrl(string $path, $params = '')
    {
        $query = is_array($params) ? http_build_query($params) : $params;

        return $this->url . '/' . ltrim($path, '/') . ($query !== '' ? '?' . $query : '');
    }


    /**
     * Send an HTTP request to the SSO server.
     *
     * @param string       $method  HTTP method: 'GET', 'POST', 'DELETE'
     * @param string       $path    Relative path
     * @param array|string $data    Query or post parameters
     * @return mixed
     */
    public function request(string $method, string $path, $data = null)
    {
        if (!$this->isAttached()) {
            throw new NotAttachedException("The client isn't attached to the SSO server for this broker. "
                . "Make sure that the '" . $this->getCookieName() . "' cookie is set.");
        }

        $url = $this->getRequestUrl($path, $method === 'POST' ? '' : $data);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Bearer '. $this->generateBearerToken()
        ]);

        if ($method === 'POST' && !empty($data)) {
            $post = is_string($data) ? $data : http_build_query($data);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        }

        $response = curl_exec($ch);
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
