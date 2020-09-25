<?php

declare(strict_types=1);

namespace Jasny\Tests\SSO\Server;

use Jasny\HttpMessage\ServerRequest;
use Jasny\HttpMessage\Uri;
use Jasny\PHPUnit\CallbackMockTrait;
use Jasny\PHPUnit\SafeMocksTrait;
use Jasny\SSO\Server\BrokerException;
use Jasny\SSO\Server\Server;
use Jasny\SSO\Server\ServerException;
use Jasny\SSO\Server\SessionInterface;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Test Server::startBrokerSession() and related methods.
 *
 * @covers \Jasny\SSO\Server\Server
 */
class BrokerSessionTest extends \Codeception\Test\Unit
{
    use ServerTestTrait;
    use CallbackMockTrait;
    use SafeMocksTrait;

    public function testSuccessfulStart()
    {
        $callback = $this->createCallbackMock($this->atLeastOnce(), ['foo'], ['secret' => 'bar']);

        $cache = $this->createMock(CacheInterface::class);

        $bearer = $this->getBearerToken('foo', 'bar', '123456', 'abc123');
        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withHeader('Authorization', "Bearer $bearer");

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $cache->expects($this->once())->method('get')
            ->with('SSO-foo-123456')
            ->willReturn('abc123');

        $session->expects($this->once())->method('isActive')->willReturn(false);
        $session->expects($this->once())->method('start')->with('abc123');

        $logger->expects($this->once())->method('debug')
            ->with(
                "Broker request with session",
                ['broker' => 'foo', 'token' => '123456', 'session' => 'abc123']
            );

        $server->startBrokerSession($request);
    }

    public function testSessionAlreadyStarted()
    {
        $callback = $this->createCallbackMock($this->never());

        $cache = $this->createMock(CacheInterface::class);

        $bearer = $this->getBearerToken('foo', 'bar', '123456', 'abc123');
        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withHeader('Authorization', "Bearer $bearer");

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $session->expects($this->once())->method('isActive')->willReturn(true);
        $session->expects($this->never())->method('start');

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage("Session is already started");

        $server->startBrokerSession($request);
    }

    public function testMissingAuthorizationHeader()
    {
        $callback = $this->createCallbackMock($this->never());

        $cache = $this->createMock(CacheInterface::class);

        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"));

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $session->expects($this->once())->method('isActive')->willReturn(false);
        $session->expects($this->never())->method('start');

        $logger->expects($this->once())->method('warning')
            ->with("Broker didn't use bearer authentication: No 'Authorization' header");

        $this->expectException(BrokerException::class);
        $this->expectExceptionMessage("Broker didn't use bearer authentication");

        $server->startBrokerSession($request);
    }

    public function testNoBearerAuthorization()
    {
        $callback = $this->createCallbackMock($this->never());

        $cache = $this->createMock(CacheInterface::class);

        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withHeader("Authorization", "Basic dXNlcjpwYXNz");

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $session->expects($this->once())->method('isActive')->willReturn(false);
        $session->expects($this->never())->method('start');

        $logger->expects($this->once())->method('warning')
            ->with("Broker didn't use bearer authentication: Basic authorization used");

        $this->expectException(BrokerException::class);
        $this->expectExceptionMessage("Broker didn't use bearer authentication");

        $server->startBrokerSession($request);
    }

    public function testInvalidBearerToken()
    {
        $callback = $this->createCallbackMock($this->never());

        $cache = $this->createMock(CacheInterface::class);

        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withHeader('Authorization', "Bearer 000000");

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $session->expects($this->once())->method('isActive')->willReturn(false);
        $session->expects($this->never())->method('start');

        $logger->expects($this->once())->method('warning')
            ->with("Invalid bearer token", ['bearer' => '000000']);

        $this->expectException(BrokerException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage("Invalid or expired bearer token");

        $server->startBrokerSession($request);
    }

    public function testInvalidChecksum()
    {
        $callback = $this->createCallbackMock($this->atLeastOnce(), ['foo'], ['secret' => 'bar']);

        $cache = $this->createMock(CacheInterface::class);

        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withHeader('Authorization', "Bearer SSO-foo-123456-000000");

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $cache->expects($this->once())->method('get')
            ->with('SSO-foo-123456')
            ->willReturn('abc123');

        $session->expects($this->once())->method('isActive')->willReturn(false);
        $session->expects($this->never())->method('start');

        $bearer = $this->getBearerToken('foo', 'bar', '123456', 'abc123');

        $logger->expects($this->once())->method('warning')
            ->with(
                "Invalid bearer checksum",
                [
                    'expected' => str_replace('SSO-foo-123456-', '', $bearer),
                    'received' => '000000',
                    'broker' => 'foo',
                    'token' => '123456',
                    'verification_code' => $this->getVerificationCode('foo', '123456', 'abc123')
                ]
            );

        $this->expectException(BrokerException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage("Invalid or expired bearer token");

        $server->startBrokerSession($request);
    }

    public function testUnattachedToken()
    {
        $callback = $this->createCallbackMock($this->any(), ['foo'], ['secret' => 'bar']);

        $cache = $this->createMock(CacheInterface::class);

        $bearer = $this->getBearerToken('foo', 'bar', '123456', 'abc123');

        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withHeader('Authorization', "Bearer $bearer");

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $cache->expects($this->once())->method('get')
            ->with('SSO-foo-123456')
            ->willReturn(null);

        $session->expects($this->once())->method('isActive')->willReturn(false);
        $session->expects($this->never())->method('start');

        $logger->expects($this->once())->method('warning')
            ->with(
                "Bearer token isn't attached to a client session",
                ['broker' => 'foo', 'token' => '123456']
            );

        $this->expectException(BrokerException::class);
        $this->expectExceptionCode(403);
        $this->expectExceptionMessage("Invalid or expired bearer token");

        $server->startBrokerSession($request);
    }

    public function testUnknownBroker()
    {
        $callback = $this->createCallbackMock($this->atLeastOnce(), ['foo'], null);

        $cache = $this->createMock(CacheInterface::class);

        $bearer = $this->getBearerToken('foo', 'bar', '123456', 'abc123');

        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withHeader('Authorization', "Bearer $bearer");

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $cache->expects($this->once())->method('get')
            ->with('SSO-foo-123456')
            ->willReturn('abc123');

        $session->expects($this->once())->method('isActive')->willReturn(false);
        $session->expects($this->never())->method('start');

        $logger->expects($this->once())->method('warning')
            ->with("Unknown broker", ['broker' => 'foo', 'token' => '123456']);

        $this->expectException(BrokerException::class);
        $this->expectExceptionMessage("Broker is unknown or disabled");

        $server->startBrokerSession($request);
    }
}
