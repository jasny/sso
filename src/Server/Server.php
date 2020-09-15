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
    protected $getBrokerSecret;

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
     * @param callable(string):?string $getBrokerSecret
     * @param CacheInterface           $cache
     */
    public function __construct(callable $getBrokerSecret, CacheInterface $cache)
    {
        $this->getBrokerSecret = \Closure::fromCallable($getBrokerSecret);
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
            throw new ServerException("Session is already started", 400);
        }

        $bearer = $this->getBearerToken($request);

        if ($bearer === null) {
            throw new BrokerException("Broker didn't use bearer authentication", 401);
        }

        [$brokerId, $token] = $this->parseBearer($bearer);

        try {
            $sessionId = $this->cache->get('SSO-' . $brokerId . '-' . $token);
        } catch (\Exception $exception) {
            $this->logger->error(
                "Failed to get session id: " . $exception->getMessage(),
                ['broker' => $brokerId, 'token' => $token]
            );
            throw new ServerException("Failed to get session id", 500, $exception);
        }

        if (!$sessionId) {
            $this->logger->warning(
                "Bearer token isn't attached to a client session",
                ['broker' => $brokerId, 'token' => $token]
            );
            throw new BrokerException("Bearer token isn't attached to a client session", 403);
        }

        $this->session->start($sessionId);

        $this->logger->debug(
            "Broker request with session",
            ['broker' => $brokerId, 'token' => $token, 'session' => $sessionId]
        );
    }

    /**
     * Get bearer token from Authorization header.
     */
    protected function getBearerToken(?ServerRequestInterface $request = null): ?string
    {
        $authorization = $request === null
            ? ($_SERVER['HTTP_AUTHORIZATION'] ?? '')
            : $request->getHeaderLine('Authorization');

        return strpos($authorization, 'Bearer') === 0
            ? substr($authorization, 7)
            : null;
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
            throw new BrokerException("Invalid bearer token");
        }

        [, $brokerId, $token, $checksum] = $matches;
        $this->validateChecksum($checksum, 'bearer', $brokerId, $token);

        return [$brokerId, $token];
    }

    /**
     * Generate session id from session token.
     */
    protected function getCacheKey(string $brokerId, string $token): string
    {
        return "SSO-{$brokerId}-{$token}";
    }

    /**
     * Generate checksum for a broker.
     */
    protected function generateChecksum(string $command, string $brokerId, string $token): string
    {
        try {
            $secret = ($this->getBrokerSecret)($brokerId);
        } catch (\Exception $exception) {
            $this->logger->warning(
                "Failed to get broker secret: " . $exception->getMessage(),
                ['broker' => $brokerId, 'token' => $token]
            );
            throw new ServerException("Failed to get broker secret", 500, $exception);
        }

        if ($secret === null) {
            $this->logger->warning("Unknown broker id", ['broker' => $brokerId, 'token' => $token]);
            throw new BrokerException("Unknown broker id", 400);
        }

        return hash_hmac('sha256', $command . ':' . $token, $secret);
    }

    /**
     * Assert that the checksum matches the expected checksum.
     *
     * @param string $checksum
     * @param string $command
     * @param string $brokerId
     * @param string $token
     * @throws BrokerException
     */
    protected function validateChecksum(string $checksum, string $command, string $brokerId, string $token): void
    {
        $expected = $this->generateChecksum($command, $brokerId, $token);

        if ($checksum !== $expected) {
            $this->logger->warning(
                "Invalid $command checksum",
                ['expected' => $expected, 'received' => $checksum, 'broker' => $brokerId, 'token' => $token]
            );
            throw new BrokerException("Invalid checksum", 400);
        }
    }

    /**
     * Attach a client session to a broker session.
     *
     * @throws BrokerException
     * @throws ServerException
     */
    public function attach(?ServerRequestInterface $request = null): void
    {
        $brokerId = $this->getQueryParam($request, 'broker', true);
        $token = $this->getQueryParam($request, 'token', true);

        $checksum = $this->getQueryParam($request, 'checksum', true);
        $this->validateChecksum($checksum, 'attach', $brokerId, $token);

        if (!$this->session->isActive()) {
            $this->session->start();
        }

        $key = $this->getCacheKey($brokerId, $token);
        $cached = $this->cache->set($key, $this->session->getId());

        $info = ['broker' => $brokerId, 'token' => $token, 'session' => $this->session->getId()];

        if (!$cached) {
            $this->logger->error("Failed to attach attach bearer token to session id due to cache issue", $info);
            throw new ServerException("Failed to attach bearer token to session id");
        }

        $this->logger->info("Attached broker token to session", $info);
    }

    /**
     * Get query parameter from PSR-7 request or $_GET.
     *
     * @param ServerRequestInterface $request
     * @param string                 $key
     * @param bool                   $required
     * @return mixed
     */
    protected function getQueryParam(?ServerRequestInterface $request, string $key, bool $required = false)
    {
        $params = $request === null ? $_GET : $request->getQueryParams();

        if ($required && !isset($params[$key])) {
            throw new BrokerException("Missing '$key' query parameter", 400);
        }

        return $params[$key] ?? null;
    }
}
