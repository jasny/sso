<?php

declare(strict_types=1);

namespace Jasny\Tests\SSO\Broker;

use Jasny\PHPUnit\ExpectWarningTrait;
use Jasny\PHPUnit\SafeMocksTrait;
use Jasny\SSO\Broker\Broker;
use Jasny\SSO\Broker\Curl;
use Jasny\SSO\Broker\NotAttachedException;
use Jasny\SSO\Broker\RequestException;
use Jasny\Tests\SSO\TokenTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test broker methods for making API requests to the SSO server.
 *
 * @covers \Jasny\SSO\Broker\Broker
 */
class RequestTest extends TestCase
{
    use TokenTrait;
    use SafeMocksTrait;
    use ExpectWarningTrait;

    /**
     * @var \ArrayObject
     */
    protected $session;

    /**
     * @var Curl&MockObject
     */
    protected $curl;

    /**
     * @var Broker
     */
    protected $broker;

    public function setUp(): void
    {
        $this->session = new \ArrayObject([
            'sso_token_foo' => '123456',
            'sso_verify_foo' => $this->getVerificationCode('foo', '123456', 'abc123'),
        ]);
        $this->curl = $this->createMock(Curl::class);

        $this->broker = (new Broker('https://example.com/attach', 'foo', 'bar'))
            ->withTokenIn($this->session)
            ->withCurl($this->curl);
    }

    public function testGetBearerToken()
    {
        $this->assertTrue($this->broker->isAttached());

        $bearer = $this->broker->getBearerToken();

        $this->assertEquals(
            $this->getBearerToken('foo', 'bar', '123456', 'abc123'),
            $bearer
        );
    }

    public function testGetBearerTokenWhenNotAttached()
    {
        unset($this->session['sso_verify_foo']);

        $this->assertFalse($this->broker->isAttached());

        $this->expectException(NotAttachedException::class);
        $this->expectExceptionMessage("The client isn't attached to the SSO server for this broker. "
            . "Make sure that the 'sso_verify_foo' cookie is set.");

        $this->broker->getBearerToken();
    }


    public function testGetRequest()
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->getBearerToken('foo', 'bar', '123456', 'abc123')
        ];
        $this->curl->expects($this->once())->method('request')
            ->with('GET', 'https://example.com/info', $headers, '')
            ->willReturn([
                'httpCode' => 200,
                'contentType' => 'application/json; charset=utf-8',
                'body' => '{"name": "John", "email": "john@example.com"}',
            ]);

        $info = $this->broker->request('GET', '/info');

        $this->assertEquals(['name' => 'John', 'email' => 'john@example.com'], $info);
    }

    public function testPostRequest()
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->getBearerToken('foo', 'bar', '123456', 'abc123')
        ];
        $this->curl->expects($this->once())->method('request')
            ->with('POST', 'https://example.com/user', $headers, ['name' => 'John', 'color' => 'red'])
            ->willReturn([
                'httpCode' => 200,
                'contentType' => 'application/json; charset=utf-8',
                'body' => '{"name": "John", "email": "john@example.com"}',
            ]);

        $info = $this->broker->request('POST', '/user', ['name' => 'John', 'color' => 'red']);

        $this->assertEquals(['name' => 'John', 'email' => 'john@example.com'], $info);
    }

    public function testNoContent()
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->getBearerToken('foo', 'bar', '123456', 'abc123')
        ];
        $this->curl->expects($this->once())->method('request')
            ->with('POST', 'https://example.com/go', $headers, '')
            ->willReturn([
                'httpCode' => 204,
                'contentType' => '',
                'body' => '',
            ]);

        $info = $this->broker->request('POST', '/go');

        $this->assertNull($info);
    }

    public function testBadRequest()
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->getBearerToken('foo', 'bar', '123456', 'abc123')
        ];
        $this->curl->expects($this->once())->method('request')
            ->with('GET', 'https://example.com/', $headers, '')
            ->willReturn([
                'httpCode' => 400,
                'contentType' => 'application/json',
                'body' => '{"error": "something is wrong"}',
            ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("something is wrong");

        $this->broker->request('GET', '/');
    }

    public function testInvalidContentType()
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->getBearerToken('foo', 'bar', '123456', 'abc123')
        ];
        $this->curl->expects($this->once())->method('request')
            ->with('GET', 'https://example.com/', $headers, '')
            ->willReturn([
                'httpCode' => 200,
                'contentType' => 'text/html',
                'body' => '<h1>Foo</h1>',
            ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("Expected 'application/json' response, got 'text/html'");

        $this->broker->request('GET', '/');
    }

    public function testInvalidJson()
    {
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $this->getBearerToken('foo', 'bar', '123456', 'abc123')
        ];
        $this->curl->expects($this->once())->method('request')
            ->with('GET', 'https://example.com/', $headers, '')
            ->willReturn([
                'httpCode' => 200,
                'contentType' => 'application/json',
                'body' => 'not json',
            ]);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage("Invalid JSON response from server");

        $this->broker->request('GET', '/');
    }
}
