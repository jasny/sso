<?php

namespace Jasny\SSO\Broker;

use ArrayAccess;
use Jasny\Immutable;

/**
 * Single sign-on broker.
 *
 * The broker lives on the website visited by the user. The broken doesn't have any user credentials stored. Instead it
 * will talk to the SSO server in name of the user, verifying credentials and getting user information.
 */
class Broker
{
    use Immutable\With;

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
     * @var ArrayAccess<string,mixed>
     */
    protected $state;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * Class constructor
     *
     * @param string $url Url of SSO server
     * @param string $broker My identifier, given by SSO provider.
     * @param string $secret My secret word, given by SSO provider.
     */
    public function __construct($url, $broker, $secret)
    {
        if (!(bool)preg_match('~^https?://~', $url)) {
            throw new \InvalidArgumentException("Invalid SSO server URL '$url'");
        }

        if ((bool)preg_match('/\W/', $broker)) {
            throw new \InvalidArgumentException("Invalid broker id '$broker': must be alphanumeric");
        }

        $this->url = $url;
        $this->broker = $broker;
        $this->secret = $secret;

        $this->state = new Cookies();
    }

    /**
     * Get a copy with a different handler for the user state (like cookie or session).
     *
     * @param ArrayAccess<string,mixed> $handler
     * @return static
     */
    public function withTokenIn(ArrayAccess $handler)
    {
        return $this->withProperty('state', $handler);
    }

    /**
     * Set a custom wrapper for cURL.
     *
     * @param Curl $curl
     * @return static
     */
    public function withCurl(Curl $curl)
    {
        return $this->withProperty('curl', $curl);
    }

    /**
     * Get Wrapped cURL.
     */
    protected function getCurl()
    {
        if (!isset($this->curl)) {
            $this->curl = new Curl(); // @codeCoverageIgnore
        }

        return $this->curl;
    }

    /**
     * Get the broker identifier.
     */
    public function getBrokerId()
    {
        return $this->broker;
    }

    /**
     * Get information from cookie.
     */
    protected function initialize()
    {
        if ($this->initialized) {
            return;
        }

        $this->token = $this->getTokenFromState();
        $this->verificationCode = $this->verificationCodeFromState();
        $this->initialized = true;
    }

    /**
     * @return string|null
     */
    protected function getToken()
    {
        $this->initialize();

        return $this->token;
    }

    /**
     * @return string|null
     */
    protected function getVerificationCode()
    {
        $this->initialize();

        return $this->verificationCode;
    }

    /**
     * Get the cookie name.
     * The broker name is part of the cookie name. This resolves issues when multiple brokers are on the same domain.
     */
    protected function getCookieName($type)
    {
        $brokerName = preg_replace('/[_\W]+/', '_', strtolower($this->broker));

        return "sso_{$type}_{$brokerName}";
    }

    /**
     * Generate session id from session key
     *
     * @throws NotAttachedException
     */
    public function getBearerToken()
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
    protected function generateToken()
    {
        $this->token = base_convert(bin2hex(openssl_random_pseudo_bytes(32)), 16, 36);
        $this->state[$this->getCookieName('token')] = $this->token;
    }

    /**
     * Clears session token.
     */
    public function clearToken()
    {
        unset($this->state[$this->getCookieName('token')]);
        unset($this->state[$this->getCookieName('verify')]);

        $this->token = null;
        $this->verificationCode = null;
    }

    /**
     * Check if we have an SSO token.
     */
    public function isAttached()
    {
        return $this->getVerificationCode() !== null;
    }

    /**
     * Get URL to attach session at SSO server.
     *
     * @param array<string,mixed> $params
     * @return string
     */
    public function getAttachUrl(array $params = [])
    {
        if ($this->getToken() === null) {
            $this->generateToken();
        }

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
    public function verify($code)
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
    protected function generateChecksum($command)
    {
        return base_convert(hash_hmac('sha256', $command . ':' . $this->token, $this->secret), 16, 36);
    }

    /**
     * Get the request url for a command
     *
     * @param string $path
     * @param array<string,mixed>|string $params Query parameters
     * @return string
     */
    protected function getRequestUrl($path, $params = '')
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
     * @param string $method HTTP method: 'GET', 'POST', 'DELETE'
     * @param string $path Relative path
     * @param array<string,mixed>|string $data Query or post parameters
     * @return mixed
     * @throws RequestException
     */
    public function request($method, $path, $data = '')
    {
        $url = $this->getRequestUrl($path, $method === 'POST' ? '' : $data);
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->getBearerToken()
        ];

        $curlResponse = $this->getCurl()->request($method, $url, $headers, $method === 'POST' ? $data : '');
        $httpCode = $curlResponse['httpCode'];
        $contentType = $curlResponse['contentType'];
        $body = $curlResponse['body'];

        return $this->handleResponse($httpCode, $contentType, $body);
    }

    /**
     * Handle the response of the cURL request.
     *
     * @param int $httpCode HTTP status code
     * @param string|null $ctHeader Content-Type header
     * @param string $body Response body
     * @return mixed
     * @throws RequestException
     */
    protected function handleResponse($httpCode, $ctHeader, $body)
    {
        if ($httpCode === 204) {
            return null;
        }

        $contentTypeParts = explode(';', $ctHeader, 2);
        $contentType = $contentTypeParts[0];

        if ($contentType != 'application/json') {
            throw new RequestException(
                "Expected 'application/json' response, got '$contentType'",
                500,
                new RequestException($body, $httpCode)
            );
        }

        try {
            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception();
            }
        } catch (\Exception $exception) {
            throw new RequestException("Invalid JSON response from server", 500);
        }

        if ($httpCode >= 400) {
            throw new RequestException(isset($data['error']) ? $data['error'] : $body, $httpCode);
        }

        return $data;
    }

    private function getTokenFromState()
    {
        if (isset($this->state[$this->getCookieName('token')])) {
            return $this->state[$this->getCookieName('token')];
        }

        return null;
    }

    private function verificationCodeFromState()
    {
        if (isset($this->state[$this->getCookieName('verify')])) {
            return $this->state[$this->getCookieName('verify')];
        }

        return null;
    }
}
