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
use function Jasny\array_without;

/**
 * Test Server::attach() and related methods.
 *
 * @covers \Jasny\SSO\Server\Server
 */
class AttachTest extends \Codeception\Test\Unit
{
    use ServerTestTrait;
    use CallbackMockTrait;
    use SafeMocksTrait;

    public function testSuccessfulAttach()
    {
        $callback = $this->createCallbackMock(
            $this->atLeastOnce(),
            ['foo'],
            ['secret' => 'bar', 'domains' => ['broker.example.com']]
        );

        $cache = $this->createMock(CacheInterface::class);

        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withQueryParams([
                'broker' => 'foo',
                'checksum' => $this->generateChecksum('attach', 'bar', '123456'),
                'token' => '123456',
                'return_url' => 'https://broker.example.com/attached'
            ])
            ->withHeader('Referer', 'https://broker.example.com/login')
            ->withHeader('Origin', 'https://broker.example.com/');

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $session->expects($this->once())->method('isActive')->willReturn(false);
        $session->expects($this->once())->method('start')->id('start');
        $session->expects($this->any())->method('getId')->after('start')->willReturn('abc123');

        $cache->expects($this->once())->method('get')
            ->with('SSO-foo-123456')
            ->willReturn(null);
        $cache->expects($this->once())->method('set')
            ->with('SSO-foo-123456', 'abc123')
            ->willReturn(true);

        $logger->expects($this->once())->method('info')
            ->with(
                "Attached broker token to session",
                ['broker' => 'foo', 'token' => '123456', 'session' => 'abc123']
            );

        $code = $server->attach($request);

        $this->assertEquals(
            $this->getVerificationCode('foo', '123456', 'abc123'),
            $code
        );
    }

    public function missingQueryParameterProvider()
    {
        return [
            'broker' => ['broker'],
            'checksum' => ['checksum'],
            'token' => ['token'],
        ];
    }

    /**
     * @dataProvider missingQueryParameterProvider
     */
    public function testMissingQueryParameter(string $key)
    {
        $callback = $this->createCallbackMock($this->never());

        $cache = $this->createMock(CacheInterface::class);

        $queryParams = [
            'broker' => 'foo',
            'checksum' => $this->generateChecksum('attach', 'bar', '123456'),
            'token' => '123456',
            'return_url' => 'https://return_url.example.com/'
        ];

        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withQueryParams(array_without($queryParams, [$key]))
            ->withHeader('Referer', 'https://referer.example.com/')
            ->withHeader('Origin', 'https://origin.example.com/');

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $session->expects($this->any())->method('isActive')->willReturn(false);
        $session->expects($this->never())->method('start');

        $cache->expects($this->never())->method('get');
        $cache->expects($this->never())->method('set');

        $this->expectException(BrokerException::class);
        $this->expectExceptionMessage("Missing '$key' query parameter");

        $server->attach($request);
    }

    public function domainProvider()
    {
        return [
            'return_url' => ['return_url', ['origin.example.com', 'referer.example.com']],
            'origin' => ['origin', ['referer.example.com', 'return_url.example.com']],
            'referer' => ['referer', ['origin.example.com', 'return_url.example.com']],
        ];
    }

    /**
     * @dataProvider domainProvider
     */
    public function testInvalidDomain(string $type, array $domains)
    {
        $callback = $this->createCallbackMock(
            $this->atLeastOnce(),
            ['foo'],
            ['secret' => 'bar', 'domains' => $domains]
        );

        $cache = $this->createMock(CacheInterface::class);

        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withQueryParams([
                'broker' => 'foo',
                'checksum' => $this->generateChecksum('attach', 'bar', '123456'),
                'token' => '123456',
                'return_url' => 'https://return_url.example.com/'
            ])
            ->withHeader('Referer', 'https://referer.example.com/')
            ->withHeader('Origin', 'https://origin.example.com/');

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $session->expects($this->any())->method('isActive')->willReturn(false);
        $session->expects($this->never())->method('start');

        $cache->expects($this->never())->method('get');
        $cache->expects($this->never())->method('set');

        $logger->expects($this->once())->method('warning')
            ->with(
                "Domain of $type is not allowed for broker",
                [$type => "https://$type.example.com/", 'broker' => 'foo', 'token' => '123456']
            );

        $this->expectException(BrokerException::class);
        $this->expectExceptionMessage("Domain of $type is not allowed");

        $server->attach($request);
    }

    public function testInvalidChecksum()
    {
        $callback = $this->createCallbackMock($this->atLeastOnce(), ['foo'], ['secret' => 'bar']);

        $cache = $this->createMock(CacheInterface::class);

        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withQueryParams([
                'broker' => 'foo',
                'checksum' => '0000000000',
                'token' => '123456'
            ]);

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $cache->expects($this->never())->method('get');
        $cache->expects($this->never())->method('set');

        $checksum = $this->generateChecksum('attach', 'bar', '123456');
        $logger->expects($this->once())->method('warning')
            ->with(
                "Invalid attach checksum",
                ['expected' => $checksum, 'received' => '0000000000', 'broker' => 'foo', 'token' => '123456']
            );

        $this->expectException(BrokerException::class);
        $this->expectExceptionMessage("Invalid checksum");

        $server->attach($request);
    }

    public function testUnknownBroker()
    {
        $callback = $this->createCallbackMock($this->atLeastOnce(), ['foo'], null);

        $cache = $this->createMock(CacheInterface::class);

        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withQueryParams([
                'broker' => 'foo',
                'checksum' => $this->generateChecksum('attach', 'bar', '123456'),
                'token' => '123456'
            ]);

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $cache->expects($this->never())->method('get');
        $cache->expects($this->never())->method('set');

        $logger->expects($this->once())->method('warning')
            ->with("Unknown broker", ['broker' => 'foo', 'token' => '123456']);

        $this->expectException(BrokerException::class);
        $this->expectExceptionMessage("Broker is unknown or disabled");

        $server->attach($request);
    }

    public function testAlreadyAttached()
    {
        $callback = $this->createCallbackMock($this->atLeastOnce(), ['foo'], ['secret' => 'bar']);

        $cache = $this->createMock(CacheInterface::class);

        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withQueryParams([
                'broker' => 'foo',
                'checksum' => $this->generateChecksum('attach', 'bar', '123456'),
                'token' => '123456'
            ]);

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $session->expects($this->once())->method('isActive')->willReturn(false);
        $session->expects($this->once())->method('start')->id('start');
        $session->expects($this->any())->method('getId')->after('start')->willReturn('abc123');

        $cache->expects($this->once())->method('get')
            ->with('SSO-foo-123456')
            ->willReturn('xyz543');
        $cache->expects($this->never())->method('set');

        $logger->expects($this->once())->method('warning')
            ->with(
                "Token is already attached",
                ['broker' => 'foo', 'token' => '123456', 'attached_to' => 'xyz543', 'session' => 'abc123']
            );

        $this->expectException(BrokerException::class);
        $this->expectExceptionMessage("Token is already attached");

        $server->attach($request);
    }

    public function testAttachIsIdempotent()
    {
        $callback = $this->createCallbackMock(
            $this->atLeastOnce(),
            ['foo'],
            ['secret' => 'bar', 'domains' => ['broker.example.com']]
        );

        $cache = $this->createMock(CacheInterface::class);

        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withQueryParams([
                'broker' => 'foo',
                'checksum' => $this->generateChecksum('attach', 'bar', '123456'),
                'token' => '123456',
                'return_url' => 'https://broker.example.com/attached'
            ])
            ->withHeader('Referer', 'https://broker.example.com/login')
            ->withHeader('Origin', 'https://broker.example.com/');

        $session = $this->createMock(SessionInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session);

        $session->expects($this->once())->method('isActive')->willReturn(false);
        $session->expects($this->once())->method('start')->id('start');
        $session->expects($this->any())->method('getId')->after('start')->willReturn('abc123');

        $cache->expects($this->once())->method('get')
            ->with('SSO-foo-123456')
            ->willReturn('abc123');
        $cache->expects($this->once())->method('set')
            ->with('SSO-foo-123456', 'abc123')
            ->willReturn(true);

        $code = $server->attach($request);

        $this->assertEquals(
            $this->getVerificationCode('foo', '123456', 'abc123'),
            $code
        );
    }

    public function testCacheIssue()
    {
        $callback = $this->createCallbackMock($this->atLeastOnce(), ['foo'], ['secret' => 'bar']);

        $cache = $this->createMock(CacheInterface::class);

        $request = (new ServerRequest())
            ->withUri(new Uri("https://server.example.com/attach.php"))
            ->withQueryParams([
                'broker' => 'foo',
                'checksum' => $this->generateChecksum('attach', 'bar', '123456'),
                'token' => '123456'
            ]);

        $session = $this->createMock(SessionInterface::class);
        $logger = $this->createMock(LoggerInterface::class);

        $server = (new Server($callback, $cache))
            ->withSession($session)
            ->withLogger($logger);

        $session->expects($this->once())->method('isActive')->willReturn(false);
        $session->expects($this->once())->method('start')->id('start');
        $session->expects($this->any())->method('getId')->after('start')->willReturn('abc123');

        $cache->expects($this->once())->method('get')
            ->with('SSO-foo-123456')
            ->willReturn(null);
        $cache->expects($this->once())->method('set')
            ->with('SSO-foo-123456', 'abc123')
            ->willReturn(false);

        $logger->expects($this->once())->method('error')
            ->with(
                "Failed to attach bearer token to session id due to cache issue",
                ['broker' => 'foo', 'token' => '123456', 'session' => 'abc123']
            );

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage("Failed to attach bearer token to session id");

        $server->attach($request);
    }
}
