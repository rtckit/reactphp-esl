<?php

declare(strict_types = 1);

namespace RTCKit\React\ESL\Tests;

use RTCKit\React\ESL\{
    AsyncConnection,
    OutboundClient
};
use RTCKit\React\ESL\Exception\ReactESLException;

use React\Promise\{
    Deferred,
    PromiseInterface
};
use React\Socket\{
    ConnectionInterface,
    Connector,
    ConnectorInterface
};
use React\Stream\ThroughStream;
use RTCKit\ESL;
use stdClass;
use function React\Promise\{
    reject,
    resolve
};

/**
 * Class OutboundClientTest.
 *
 * @covers \RTCKit\React\ESL\OutboundClient
 */
class OutboundClientTest extends TestCase
{
    public function testConstructor(): void
    {
        $client = new OutboundClient('127.0.0.1', 8084);

        $this->assertEquals('127.0.0.1', $this->getPropertyValue($client, 'host'));
        $this->assertEquals(8084, $this->getPropertyValue($client, 'port'));
    }

    public function testConnectSuccessful(): void
    {
        $client = new OutboundClient('127.0.0.1', 8084);
        $connector = $this->getAbstractMock(ConnectorInterface::class);

        $this->setPropertyValue($client, 'connector', $connector);

        $stream = $this->getAbstractMock(ConnectionInterface::class);

        $connector->method('connect')
            ->with('tcp://127.0.0.1:8084')
            ->willReturn(resolve($stream));

        $this->assertInstanceOf(PromiseInterface::class, $client->connect());
        $this->assertEquals($stream, $this->getPropertyValue($client, 'stream'));
        $this->assertInstanceOf(AsyncConnection::class, $this->getPropertyValue($client, 'esl'));
    }

    public function testConnectFailed(): void
    {
        $client = new OutboundClient('127.0.0.1', 8084);
        $connector = $this->getAbstractMock(ConnectorInterface::class);

        $this->setPropertyValue($client, 'connector', $connector);

        $stream = $this->getAbstractMock(ConnectionInterface::class);

        $connector->method('connect')
            ->with('tcp://127.0.0.1:8084')
            ->willReturn(reject(new \Exception('Something went wrong')));

        $this->assertInstanceOf(PromiseInterface::class, $client->connect());
        $this->assertFalse($this->isPropertyInitialized($client, 'stream'));
        $this->assertFalse($this->isPropertyInitialized($client, 'esl'));
    }

    public function testDataHandler(): void
    {
        $client = new OutboundClient('127.0.0.1', 8084);
        $connector = $this->getAbstractMock(ConnectorInterface::class);

        $this->setPropertyValue($client, 'connector', $connector);

        $stream = $this->getAbstractMock(ConnectionInterface::class);

        $connector->method('connect')
            ->with('tcp://127.0.0.1:8084')
            ->willReturn(resolve($stream));

        $this->assertInstanceOf(PromiseInterface::class, $client->connect());
        $this->assertEquals($stream, $this->getPropertyValue($client, 'stream'));
        $this->assertInstanceOf(AsyncConnection::class, $this->getPropertyValue($client, 'esl'));

        $dataHandler = $this->getMethod($client, 'dataHandler');

        $this->assertFalse($this->getPropertyValue($client, 'connected'));
        $dataHandler->invokeArgs($client, ["connect\n\n"]);
        $this->assertTrue($this->getPropertyValue($client, 'connected'));

        $client->on('request', function (ESL\Request $request) {
            if ($request instanceof ESL\Request\Resume) {
                $this->assertTrue(true, 'Received resume');
            } else if ($request instanceof ESL\Request\Linger) {
                $this->assertTrue(true, 'Received linger');
            } else if ($request instanceof ESL\Request\MyEvents) {
                $this->assertTrue(true, 'Received my events');
                $this->assertEquals('json', $request->getParameters());
            } else {
                $this->fail('Unrecognized request');
            }
        });

        $dataHandler->invokeArgs($client, [
            "resume\n\n" .
            "linger\n\n" .
            "myevents json\n\n"
        ]);
    }

    public function testClose(): void
    {
        $client = new OutboundClient('127.0.0.1', 8084);
        $connector = $this->getAbstractMock(ConnectorInterface::class);

        $this->setPropertyValue($client, 'connector', $connector);

        $stream = $this->getAbstractMock(ConnectionInterface::class);

        $connector->method('connect')
            ->with('tcp://127.0.0.1:8084')
            ->willReturn(resolve($stream));

        $client->connect();
        $stream->expects($this->once())->method('close');
        $client->close();
    }
}
