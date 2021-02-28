<?php

declare(strict_types=1);

namespace Jasny\SSO\Server;

use Jasny\Immutable;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\SimpleCache\CacheInterface;

/**
 * Single sign-on server.
 * The SSO server is responsible of managing users sessions which are available for brokers.
 */
class Server
{
    use Immutable\With;

    /**
     * Callback to get the secret for a broker.
     * @var \Closure
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
     * @phpstan-param CacheInterface                                          $cache
     */
    public function __construct(callable $getBrokerInfo, CacheInterface $cache)
    {
        $this->getBrokerInfo = \Closure::fromCallable($getBrokerInfo);
        $this->cache = $cache;

        $this->logger = new NullLogger();
        $this->session = new GlobalSession();
    }

    /**
     * Get a copy of the service with logging.
     *
     * @return static
     */
    public function withLogger(LoggerInterface $logger): self
    {
        return $this->withProperty('logger', $logger);
    }

    /**
     * Get a copy of the service with a custom session service.
     *
     * @return static
     */
    public function withSession(SessionInterface $session): self
    {
        return $this->withProperty('session', $session);
    }


    /**
     * Start the session for broker requests to the SSO server.
     *
     * @throws BrokerException
     * @throws ServerException
     */
    public function startBrokerSession(?ServerRequestInterface $request = null): void
    {
        if ($this->session->isActive()) {
            throw new ServerException("Session is already started", 500);
        }

        $bearer = $this->getBearerToken($request);

        [$brokerId, $token, $checksum] = $this->parseBearer($bearer);

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
    protected function getBearerToken(?ServerRequestInterface $request = null): string
    {
        $authorization = $request === null
            ? ($_SERVER['HTTP_AUTHORIZATION'] ?? '') // @codeCoverageIgnore
            : $request->getHeaderLine('Authorization');

        [$type, $token] = explode(' ', $authorization, 2) + ['', ''];

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
    protected function parseBearer(string $bearer): array
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
    protected function getCacheKey(string $brokerId, string $token): string
    {
        return "SSO-{$brokerId}-{$token}";
    }

    /**
     * Get the broker secret using the configured callback.
     *
     * @param string $brokerId
     * @return string|null
     */
    protected function getBrokerSecret(string $brokerId): ?string
    {
        return ($this->getBrokerInfo)($brokerId)['secret'] ?? null;
    }

    /**
     * Generate the verification code based on the token using the server secret.
     */
    protected function getVerificationCode(string $brokerId, string $token, string $sessionId): string
    {
        return base_convert(hash('sha256', $brokerId . $token . $sessionId), 16, 36);
    }

    /**
     * Generate checksum for a broker.
     */
    protected function generateChecksum(string $command, string $brokerId, string $token): string
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
    protected function validateChecksum(
        string $checksum,
        string $command,
        string $brokerId,
        string $token,
        ?string $code = null
    ): void {
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
    public function validateDomain(string $type, string $url, string $brokerId, ?string $token = null): void
    {
        $domains = ($this->getBrokerInfo)($brokerId)['domains'] ?? [];
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
    public function attach(?ServerRequestInterface $request = null): string
    {
        ['broker' => $brokerId, 'token' => $token] = $this->processAttachRequest($request);

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
    protected function assertNotAttached(string $brokerId, string $token): void
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
    protected function processAttachRequest(?ServerRequestInterface $request): array
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
    protected function getQueryParam(?ServerRequestInterface $request, string $key): ?string
    {
        $params = $request === null
            ? $_GET // @codeCoverageIgnore
            : $request->getQueryParams();

        return $params[$key] ?? null;
    }

    /**
     * Get required query parameter from PSR-7 request or $_GET.
     *
     * @throws BrokerException if query parameter isn't set
     */
    protected function getRequiredQueryParam(?ServerRequestInterface $request, string $key): string
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
     * @param string                 $key
     * @return string
     */
    protected function getHeader(?ServerRequestInterface $request, string $key): string
    {
        return $request === null
            ? ($_SERVER['HTTP_' . str_replace('-', '_', strtoupper($key))] ?? '') // @codeCoverageIgnore
            : $request->getHeaderLine($key);
    }
}
