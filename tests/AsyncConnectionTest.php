<?php

declare(strict_types = 1);

namespace RTCKit\React\ESL\Tests;

use RTCKit\React\ESL\AsyncConnection;

use React\Socket\{
    Connection,
    ConnectionInterface
};
use React\Stream\ThroughStream;

/**
 * Class AsyncConnectionTest.
 *
 * @covers \RTCKit\React\ESL\AsyncConnection
 */
class AsyncConnectionTest extends TestCase
{
    public function testSetStream(): void
    {
        $stream = $this->getMock(ConnectionInterface::class);

        $conn = new AsyncConnection(AsyncConnection::INBOUND_CLIENT);
        $conn->setStream($stream);

        $this->assertEquals($stream, $this->getPropertyValue($conn, 'stream'));
    }

    public function testEmitBytes(): void
    {
        $stream = $this->getMockSetMethods(Connection::class, ['write']);

        $conn = new AsyncConnection(AsyncConnection::INBOUND_CLIENT);
        $conn->setStream($stream);

        $emitBytes = $this->getMethod($conn, 'emitBytes');

        $stream->expects($this->once())->method('write')->with('request');

        $emitBytes->invokeArgs($conn, ['request']);
    }

    public function testClose(): void
    {
        $stream = $this->getMockSetMethods(Connection::class, ['close']);

        $conn = new AsyncConnection(AsyncConnection::INBOUND_CLIENT);
        $conn->setStream($stream);

        $stream->expects($this->once())->method('close');

        $conn->close();
    }

    public function testGetAddressClient(): void
    {
        $stream = $this->getMockSetMethods(Connection::class, ['getLocalAddress']);

        $conn = new AsyncConnection(AsyncConnection::INBOUND_CLIENT);
        $conn->setStream($stream);

        $stream->expects($this->once())->method('getLocalAddress');

        $conn->getAddress();
    }

    public function testGetAddressServer(): void
    {
        $stream = $this->getMockSetMethods(Connection::class, ['getRemoteAddress']);

        $conn = new AsyncConnection(AsyncConnection::INBOUND_SERVER);
        $conn->setStream($stream);

        $stream->expects($this->once())->method('getRemoteAddress');

        $conn->getAddress();
    }

    public function testGetAddressNull(): void
    {
        $conn = new AsyncConnection(AsyncConnection::INBOUND_SERVER);
        $conn->setStream(new ThroughStream);

        $this->assertNull($conn->getAddress());
    }
}
