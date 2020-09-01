<?php

declare(strict_types=1);

namespace Jasny\SSO\Server;

use Jasny\Immutable;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

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
     * Service to interact with sessions.
     * @var SessionInterface
     */
    protected $session;

    /**
     * Broker of the current session.
     * @var string|null
     */
    protected $brokerId = null;


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

        $this->session = new GlobalSession();
    }

    /**
     * Get a copy of the service with a custom session service.
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

        try {
            $linkedId = $this->cache->get($bearer);
        } catch (\Exception $exception) {
            throw new ServerException("Failed to get session id from cache", 500, $exception);
        }

        if (!$linkedId) {
            throw new BrokerException("Bearer token isn't attached to a user session", 403);
        }

        $this->brokerId = $this->getBrokerIdFromBearer($bearer);
        $this->session->start($linkedId);
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
     * Get the broker id from the bearer token used by the broker.
     */
    protected function getBrokerIdFromBearer(string $bearer): string
    {
        $matches = null;

        if (!(bool)preg_match('/^SSO-(\w*+)-(\w*+)-([a-z0-9]*+)$/', $bearer, $matches)) {
            throw new BrokerException("Invalid session id");
        }

        $brokerId = $matches[1];
        $token = $matches[2];

        if ($this->generateBearerToken($brokerId, $token) != $bearer) {
            throw new BrokerException("Bearer token checksum failed", 403);
        }

        return $brokerId;
    }

    /**
     * Generate session id from session token.
     */
    protected function generateBearerToken(string $brokerId, string $token): string
    {
        return "SSO-{$brokerId}-{$token}-" . $this->generateChecksum('bearer', $brokerId, $token);
    }

    /**
     * Generate checksum for a broker.
     */
    protected function generateChecksum(string $command, string $brokerId, string $token): string
    {
        $secret = ($this->getBrokerSecret)($brokerId);

        if ($secret === null) {
            throw new BrokerException("Invalid broker id", 400);
        }

        $checksum = hash_hmac('sha256', $command . ':' . $token, $secret);

        if ($checksum === null) {
            throw new ServerException("Failed to generate $command checksum");
        }

        return $checksum;
    }

    /**
     * Attach a user session to a broker session.
     *
     * @throws BrokerException
     * @throws ServerException
     */
    public function attach(?ServerRequestInterface $request = null): void
    {
        $brokerId = $this->getQueryParam($request, 'broker', true);
        $token = $this->getQueryParam($request, 'token', true);

        $checksum = $this->getQueryParam($request, 'checksum', true);
        $expectedChecksum = $this->generateChecksum('attach', $brokerId, $token);

        if ($checksum !== $expectedChecksum) {
            throw new BrokerException("Invalid checksum", 400);
        }

        if (!$this->session->isActive()) {
            $this->session->start();
        }

        $bearer = $this->generateBearerToken($brokerId, $token);

        try {
            $this->cache->set($bearer, $this->session->getId());
        } catch (InvalidArgumentException $exception) {
            throw new ServerException("Failed to attach bearer token to session id", 500, $exception);
        }
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
