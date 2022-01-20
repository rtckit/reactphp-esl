<?php

declare(strict_types = 1);

namespace RTCKit\React\ESL\Tests;

use RTCKit\React\ESL\{
    AsyncConnection,
    RemoteInboundClient
};
use RTCKit\ESL;

/**
 * Class RemoteInboundClientTest.
 *
 * @covers \RTCKit\React\ESL\RemoteInboundClient
 */
class RemoteInboundClientTest extends TestCase
{
    private AsyncConnection $esl;

    private RemoteInboundClient $remote;

    protected function setUp(): void
    {
        $this->esl = $this->getMock(AsyncConnection::class);
        $this->remote = new RemoteInboundClient($this->esl);
    }

    public function testConstructor(): void
    {
        $this->assertSame($this->esl, $this->getPropertyValue($this->remote, 'esl'));
    }

    public function testGetAuthenticated(): void
    {
        $this->setPropertyValue($this->remote, 'authenticated', false);
        $this->assertFalse($this->remote->getAuthenticated());

        $this->setPropertyValue($this->remote, 'authenticated', true);
        $this->assertTrue($this->remote->getAuthenticated());
    }

    public function testSetAuthenticated(): void
    {
        $this->remote->setAuthenticated(false);
        $this->assertFalse($this->getPropertyValue($this->remote, 'authenticated'));

        $this->remote->setAuthenticated(true);
        $this->assertTrue($this->getPropertyValue($this->remote, 'authenticated'));
    }

    public function testSend(): void
    {
        $response = new ESL\Response;

        $this->esl
            ->expects($this->once())
            ->method('emit')
            ->with($response);

        $this->remote->send($response);
    }

    public function testClose(): void
    {
        $this->esl
            ->expects($this->once())
            ->method('close');

        $this->remote->close();
    }
}
