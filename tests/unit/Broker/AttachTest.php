<?php

declare(strict_types=1);

namespace Jasny\Tests\SSO\Broker;

use Jasny\PHPUnit\ExpectWarningTrait;
use Jasny\PHPUnit\SafeMocksTrait;
use Jasny\SSO\Broker\Broker;
use Jasny\SSO\Broker\Curl;
use Jasny\SSO\Broker\NotAttachedException;
use Jasny\Tests\SSO\TokenTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test methods for attaching the broker token to a client session.
 *
 * @covers \Jasny\SSO\Broker\Broker
 */
class AttachTest extends TestCase
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
        $this->session = new \ArrayObject();
        $this->curl = $this->createMock(Curl::class);

        $this->broker = (new Broker('https://example.com/attach', 'foo', 'bar'))
            ->withTokenIn($this->session)
            ->withCurl($this->curl);
    }

    public function testUrlValidationInConstruct()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid SSO server URL 'example'");

        new Broker('example', 'foo', 'bar');
    }

    public function testBrokerIdValidationInConstruct()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid broker id 'foo-1': must be alphanumeric");

        new Broker('https://example.com', 'foo-1', 'bar');
    }

    public function testGetBrokerId()
    {
        $this->assertEquals('foo', $this->broker->getBrokerId());
    }

    public function testGetAttachUrl()
    {
        $url = $this->broker->getAttachUrl();

        $this->assertArrayHasKey('sso_token_foo', $this->session);

        $token = $this->session["sso_token_foo"];
        $checksum = $this->generateChecksum('attach', 'bar', $token);

        $this->assertEquals("https://example.com/attach?broker=foo&token=$token&checksum=$checksum", $url);
        $this->assertFalse($this->broker->isAttached());
    }

    public function testGetAttachUrlWithParams()
    {
        $url = $this->broker->getAttachUrl([
            'return_url' => 'http://broker.example.com/',
            'color' => 'red',
        ]);

        $this->assertArrayHasKey('sso_token_foo', $this->session);

        $token = $this->session["sso_token_foo"];
        $checksum = $this->generateChecksum('attach', 'bar', $token);

        $expectedUrl = "https://example.com/attach?broker=foo&token=$token&checksum=$checksum&return_url="
            . urlencode('http://broker.example.com/') . '&color=red';
        $this->assertEquals($expectedUrl, $url);
    }

    public function testVerify()
    {
        $this->session['sso_token_foo'] = '123456';

        $this->assertFalse($this->broker->isAttached());

        $code = $this->getVerificationCode('foo', '123456', 'abc123');
        $this->broker->verify($code);

        $this->assertArrayHasKey('sso_verify_foo', $this->session);
        $this->assertEquals($code, $this->session['sso_verify_foo']);
        $this->assertTrue($this->broker->isAttached());
    }

    public function testVerifyIsIdempotent()
    {
        $code = $this->getVerificationCode('foo', '123456', 'abc123');

        $this->session['sso_token_foo'] = '123456';
        $this->session['sso_verify_foo'] = $code;

        $this->broker->verify($code);

        $this->assertArrayHasKey('sso_verify_foo', $this->session);
        $this->assertEquals($code, $this->session['sso_verify_foo']);
    }

    public function testVerifyIsImmutable()
    {
        $this->session['sso_token_foo'] = '123456';
        $this->session['sso_verify_foo'] = '000000';

        $code = $this->getVerificationCode('foo', '123456', 'abc123');

        $this->expectWarningMessage("SSO attach already verified");

        $this->broker->verify($code);

        $this->assertArrayHasKey('sso_verify_foo', $this->session);
        $this->assertEquals('000000', $this->session['sso_verify_foo']);
    }

    public function testClearToken()
    {
        $this->session['sso_token_foo'] = '123456';
        $this->session['sso_verify_foo'] = $this->getVerificationCode('foo', '123456', 'abc123');

        $this->assertTrue($this->broker->isAttached());

        $this->broker->clearToken();

        $this->assertFalse($this->broker->isAttached());
        $this->assertArrayNotHasKey('sso_token_foo', $this->session);
        $this->assertArrayNotHasKey('sso_verify_foo', $this->session);
    }
}
