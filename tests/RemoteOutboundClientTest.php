<?php

declare(strict_types = 1);

namespace RTCKit\React\ESL\Tests;

use RTCKit\React\ESL\{
    AsyncConnection,
    RemoteOutboundClient
};

/**
 * Class RemoteOutboundClientTest.
 *
 * @covers \RTCKit\React\ESL\RemoteOutboundClient
 */
class RemoteOutboundClientTest extends TestCase
{
    private AsyncConnection $esl;

    private RemoteOutboundClient $remote;

    protected function setUp(): void
    {
        $this->esl = $this->getMock(AsyncConnection::class);
        $this->remote = new RemoteOutboundClient($this->esl);
    }

    public function testConstructor(): void
    {
        $this->assertSame($this->esl, $this->getPropertyValue($this->remote, 'esl'));
    }

    public function testGetConnected(): void
    {
        $this->setPropertyValue($this->remote, 'connected', false);
        $this->assertFalse($this->remote->getConnected());

        $this->setPropertyValue($this->remote, 'connected', true);
        $this->assertTrue($this->remote->getConnected());
    }

    public function testSetConnected(): void
    {
        $this->remote->setConnected(false);
        $this->assertFalse($this->getPropertyValue($this->remote, 'connected'));

        $this->remote->setConnected(true);
        $this->assertTrue($this->getPropertyValue($this->remote, 'connected'));
    }

    public function testClose(): void
    {
        $this->esl
            ->expects($this->once())
            ->method('close');

        $this->remote->close();
    }
}
