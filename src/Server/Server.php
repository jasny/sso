<?php

namespace Jasny\SSO\Server;

use Closure;
use Jasny\Immutable;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;
use InvalidArgumentException;

/**
 * Single sign-on server.
 * The SSO server is responsible of managing users sessions which are available for brokers.
 */
class Server
{
    use Immutable\With;

    /**
     * Callback to get the secret for a broker.
     * @var Closure
     */
    protected $getBrokerInfo;

    /**
     * Storage for broker session links.
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Service to interact with sessions.
     * @var SessionInterface
     */
    protected $session;

    /**
     * Class constructor.
     *
     * @phpstan-param callable(string):?array{secret:string,domains:string[]} $getBrokerInfo
     * @phpstan-param CacheInterface $cache
     */
    public function __construct($getBrokerInfo, CacheInterface $cache)
    {
        if (!is_callable($getBrokerInfo)) {
            throw new InvalidArgumentException('Expected a callable argument.');
        }

        $getBrokerInfoClosure = function () use ($getBrokerInfo) {
            return call_user_func($getBrokerInfo);
        };

        $this->getBrokerInfo = $getBrokerInfoClosure;
        $this->cache = $cache;

        $this->logger = new NullLogger();
        $this->session = new GlobalSession();
    }

    /**
     * Get a copy of the service with logging.
     *
     * @return static
     */
    public function withLogger(LoggerInterface $logger)
    {
        return $this->withProperty('logger', $logger);
    }

    /**
     * Get a copy of the service with a custom session service.
     *
     * @return static
     */
    public function withSession(SessionInterface $session)
    {
        return $this->withProperty('session', $session);
    }


    /**
     * Start the session for broker requests to the SSO server.
     *
     * @throws BrokerException
     * @throws ServerException
     */
    public function startBrokerSession($request = null)
    {
        if ($this->session->isActive()) {
            throw new ServerException("Session is already started", 500);
        }

        $bearer = $this->getBearerToken($request);

        $bearerParts = $this->parseBearer($bearer);
        $brokerId = $bearerParts[0];
        $token = $bearerParts[1];
        $checksum = $bearerParts[2];

        $sessionId = $this->cache->get($this->getCacheKey($brokerId, $token));

        if ($sessionId === null) {
            $this->logger->warning(
                "Bearer token isn't attached to a client session",
                ['broker' => $brokerId, 'token' => $token]
            );
            throw new BrokerException("Bearer token isn't attached to a client session", 403);
        }

        $code = $this->getVerificationCode($brokerId, $token, $sessionId);
        $this->validateChecksum($checksum, 'bearer', $brokerId, $token, $code);

        $this->session->resume($sessionId);

        $this->logger->debug(
            "Broker request with session",
            ['broker' => $brokerId, 'token' => $token, 'session' => $sessionId]
        );
    }

    /**
     * Get bearer token from Authorization header.
     */
    protected function getBearerToken($request = null)
    {
        $authorization = ($request === null) ? (isset($_SERVER['HTTP_AUTHORIZATION']) ? $_SERVER['HTTP_AUTHORIZATION'] : '')
            : $request->getHeaderLine('Authorization');

        $authorization_array = explode(' ', $authorization, 2);
        $type = isset($authorization_array[0]) ? $authorization_array[0] : '';
        $token = isset($authorization_array[1]) ? $authorization_array[1] : '';

        if ($type !== 'Bearer') {
            $this->logger->warning("Broker didn't use bearer authentication: "
                . ($authorization === '' ? "No 'Authorization' header" : "$type authorization used"));
            throw new BrokerException("Broker didn't use bearer authentication", 401);
        }

        return $token;
    }

    /**
     * Get the broker id and token from the bearer token used by the broker.
     *
     * @return string[]
     * @throws BrokerException
     */
    protected function parseBearer($bearer)
    {
        $matches = null;

        if (!(bool)preg_match('/^SSO-(\w*+)-(\w*+)-([a-z0-9]*+)$/', $bearer, $matches)) {
            $this->logger->warning("Invalid bearer token", ['bearer' => $bearer]);
            throw new BrokerException("Invalid bearer token", 403);
        }

        return array_slice($matches, 1);
    }

    /**
     * Generate cache key for linking the broker token to the client session.
     */
    protected function getCacheKey($brokerId, $token)
    {
        return "SSO-{$brokerId}-{$token}";
    }

    /**
     * Get the broker secret using the configured callback.
     *
     * @param string $brokerId
     * @return string|null
     */
    protected function getBrokerSecret($brokerId)
    {
        $brokerInfo = call_user_func($this->getBrokerInfo, $brokerId);
        return isset($brokerInfo['secret']) ? $brokerInfo['secret'] : null;
    }

    /**
     * Generate the verification code based on the token using the server secret.
     */
    protected function getVerificationCode($brokerId, $token, $sessionId)
    {
        return base_convert(hash('sha256', $brokerId . $token . $sessionId), 16, 36);
    }

    /**
     * Generate checksum for a broker.
     */
    protected function generateChecksum($command, $brokerId, $token)
    {
        $secret = $this->getBrokerSecret($brokerId);

        if ($secret === null) {
            $this->logger->warning("Unknown broker", ['broker' => $brokerId, 'token' => $token]);
            throw new BrokerException("Broker is unknown or disabled", 403);
        }

        return base_convert(hash_hmac('sha256', $command . ':' . $token, $secret), 16, 36);
    }

    /**
     * Assert that the checksum matches the expected checksum.
     *
     * @throws BrokerException
     */
    protected function validateChecksum($checksum, $command, $brokerId, $token, $code = null)
    {
        $expected = $this->generateChecksum($command . ($code !== null ? ":$code" : ''), $brokerId, $token);

        if ($checksum !== $expected) {
            $this->logger->warning(
                "Invalid $command checksum",
                ['expected' => $expected, 'received' => $checksum, 'broker' => $brokerId, 'token' => $token]
                + ($code !== null ? ['verification_code' => $code] : [])
            );
            throw new BrokerException("Invalid $command checksum", 403);
        }
    }

    /**
     * Validate that the URL has a domain that is allowed for the broker.
     */
    public function validateDomain($type, $url, $brokerId, $token = null)
    {
        $brokerInfo = call_user_func($this->getBrokerInfo, $brokerId);
        $domains = isset($brokerInfo['domains']) ? $brokerInfo['domains'] : [];
        $host = parse_url($url, PHP_URL_HOST);

        if (!in_array($host, $domains, true)) {
            $this->logger->warning(
                "Domain of $type is not allowed for broker",
                [$type => $url, 'broker' => $brokerId] + ($token !== null ? ['token' => $token] : [])
            );
            throw new BrokerException("Domain of $type is not allowed", 400);
        }
    }

    /**
     * Attach a client session to a broker session.
     * Returns the verification code.
     *
     * @throws BrokerException
     * @throws ServerException
     */
    public function attach($request = null)
    {
        $attachRequest = $this->processAttachRequest($request);
        $brokerId = isset($attachRequest['broker']) ? $attachRequest['broker'] : null;
        $token = isset($attachRequest['token']) ? $attachRequest['token'] : null;

        $this->session->start();

        $this->assertNotAttached($brokerId, $token);

        $key = $this->getCacheKey($brokerId, $token);
        $cached = $this->cache->set($key, $this->session->getId());

        $info = ['broker' => $brokerId, 'token' => $token, 'session' => $this->session->getId()];

        if (!$cached) {
            $this->logger->error("Failed to attach bearer token to session id due to cache issue", $info);
            throw new ServerException("Failed to attach bearer token to session id", 500);
        }

        $this->logger->info("Attached broker token to session", $info);

        return $this->getVerificationCode($brokerId, $token, $this->session->getId());
    }

    /**
     * Assert that the token isn't already attached to a different session.
     */
    protected function assertNotAttached($brokerId, $token)
    {
        $key = $this->getCacheKey($brokerId, $token);
        $attached = $this->cache->get($key);

        if ($attached !== null && $attached !== $this->session->getId()) {
            $this->logger->warning("Token is already attached", [
                'broker' => $brokerId,
                'token' => $token,
                'attached_to' => $attached,
                'session' => $this->session->getId()
            ]);
            throw new BrokerException("Token is already attached", 400);
        }
    }

    /**
     * Validate attach request and return broker id and token.
     *
     * @param ServerRequestInterface|null $request
     * @return array{broker:string,token:string}
     * @throws BrokerException
     */
    protected function processAttachRequest($request)
    {
        $brokerId = $this->getRequiredQueryParam($request, 'broker');
        $token = $this->getRequiredQueryParam($request, 'token');
        $checksum = $this->getRequiredQueryParam($request, 'checksum');

        $this->validateChecksum($checksum, 'attach', $brokerId, $token);

        $origin = $this->getHeader($request, 'Origin');
        if ($origin !== '') {
            $this->validateDomain('origin', $origin, $brokerId, $token);
        }

        $referer = $this->getHeader($request, 'Referer');
        if ($referer !== '') {
            $this->validateDomain('referer', $referer, $brokerId, $token);
        }

        $returnUrl = $this->getQueryParam($request, 'return_url');
        if ($returnUrl !== null) {
            $this->validateDomain('return_url', $returnUrl, $brokerId, $token);
        }

        return ['broker' => $brokerId, 'token' => $token];
    }

    /**
     * Get query parameter from PSR-7 request or $_GET.
     */
    protected function getQueryParam($request, $key)
    {
        $params = $request === null
            ? $_GET // @codeCoverageIgnore
            : $request->getQueryParams();

        return isset($params[$key]) ? $params[$key] : null;
    }

    /**
     * Get required query parameter from PSR-7 request or $_GET.
     *
     * @throws BrokerException if query parameter isn't set
     */
    protected function getRequiredQueryParam($request, $key)
    {
        $value = $this->getQueryParam($request, $key);

        if ($value === null) {
            throw new BrokerException("Missing '$key' query parameter", 400);
        }

        return $value;
    }

    /**
     * Get HTTP Header from PSR-7 request or $_SERVER.
     *
     * @param ServerRequestInterface $request
     * @param string $key
     * @return string
     */
    protected function getHeader($request, $key)
    {
        return $this->test($request, $key);
    }

    private function test($request, $key)
    {
        if ($request === null) {
            $headerKey = 'HTTP_' . str_replace('-', '_', strtoupper($key));
            return isset($_SERVER[$headerKey]) ? $_SERVER[$headerKey] : '';
        }

        return $request->getHeaderLine($key);
    }
}
