<?php

declare(strict_types = 1);

namespace RTCKit\React\ESL\Tests;

use RTCKit\React\ESL\{
    OutboundServer,
    RemoteOutboundClient
};
use RTCKit\React\ESL\Exception\ReactESLException;

use React\EventLoop\Loop;
use React\Promise\Deferred;
use React\Socket\{
    ConnectionInterface,
    ServerInterface
};
use React\Stream\ThroughStream;
use RTCKit\ESL;

/**
 * Class OutboundServerTest.
 *
 * @covers \RTCKit\React\ESL\OutboundServer
 */
class OutboundServerTest extends TestCase
{
    public function testConstructor(): void
    {
        $server = new OutboundServer('127.0.0.1', 8084);

        $this->assertEquals('127.0.0.1', $this->getPropertyValue($server, 'host'));
        $this->assertEquals(8084, $this->getPropertyValue($server, 'port'));
    }

    public function testListenDefaultTcpSock(): void
    {
        $server = new OutboundServer('127.0.0.1', 0);
        $server->listen();

        $this->assertInstanceOf(ServerInterface::class, $this->getPropertyValue($server, 'socket'));
        Loop::stop();
    }

    public function testListen(): void
    {
        $socket = $this->getMock(ServerInterface::class);
        $server = new OutboundServer('127.0.0.1', 8084);

        $socket
            ->expects($this->exactly(2))
            ->method('on')
            ->withConsecutive(
                ['connection'],
                ['error']
            );

        $server->listen($socket);

        $this->assertSame($socket, $this->getPropertyValue($server, 'socket'));
    }

    public function testListenError(): void
    {
        $server = new OutboundServer('127.0.0.1', 0);
        $server->on('error', function(\Throwable $t) {
            $this->assertInstanceOf(\RuntimeException::class, $t);
            $this->assertEquals('test', $t->getMessage());
        });
        $server->listen();

        $this->getPropertyValue($server, 'socket')->emit('error', [new \RuntimeException('test')]);
    }

    public function testConnectionHandler(): void
    {
        $socket = $this->getMock(ServerInterface::class);
        $server = new OutboundServer('127.0.0.1', 8084);

        $server->listen($socket);
        $connHandler = $this->getMethod($server, 'connectionHandler');
        $stream = new ThroughStream(function (string $bytes) {
            if ($bytes === "connect\n\n") {
                $this->assertTrue(true, 'Connect preamble to be sent');

                return "content-type: text/event-plain\ntest: true\n\n";
            }

            return $bytes;
        });

        $server->on('disconnect', function ($client, $response = null) {
            if (isset($response)) {
                if ($response instanceof ESL\Response\TextDisconnectNotice) {
                    $this->assertTrue(true, 'Received disconnect notice');
                } else {
                    $this->fail('Unrecognized notice');
                }
            } else {
                $this->assertTrue(true, 'Sudden disconnect');
            }
        });

        $server->on('connect', function (RemoteOutboundClient $client, ESL\Response $response) use ($stream) {
            $this->assertInstanceOf(ESL\Response\TextEventPlain::class, $response);
            $this->assertEquals('true', $response->getHeader('test'));

            $client->on('event', function (ESL\Response $event) {
                if ($event instanceof ESL\Response\TextEventJson) {
                    $this->assertTrue(true, 'Received JSON event');
                } else if ($event instanceof ESL\Response\TextEventPlain) {
                    $this->assertTrue(true, 'Received plain event');
                } else if ($event instanceof ESL\Response\TextEventXml) {
                    $this->assertTrue(true, 'Received XML event');
                } else {
                    $this->fail('Unrecognized event');
                }
            });

            $stream->write(
                "content-type: text/event-json\n\n" .
                "content-type: text/event-plain\n\n" .
                "content-type: text/event-xml\n\n"
            );

            $stream->write("content-type: text/disconnect-notice\n\n");

            $client->on('error', function (\Throwable $t) {
                if ($t instanceof ReactESLException) {
                    $this->assertTrue(true, 'Received out of sequence exception');
                } else if ($t instanceof ESL\Exception\ESLException) {
                    $this->assertTrue(true, 'Received parsing exception');
                } else {
                    $this->fail('Unrecognized exception');
                }
            });

            $deferred = new Deferred;
            $this->setPropertyValue($client, 'queue', [$deferred]);

            $stream->write("content-type: api/response\nexpected: true\n\n");

            $deferred->promise()->then(function ($response) {
                $this->assertInstanceOf(ESL\Response\ApiResponse::class, $response);
                $this->assertEquals('true', $response->getHeader('expected'));
            });

            $this->setPropertyValue($client, 'queue', []);
            $stream->write("content-type: api/response\nexpected: false\n\n");

            $stream->write("content-type: bogus\n\n");

            $stream->close();
        });

        $connHandler->invokeArgs($server, [$stream]);
    }

    public function testGetAddress(): void
    {
        $socket = $this->getMock(ServerInterface::class);
        $server = new OutboundServer('127.0.0.1', 8084);

        $socket
            ->expects($this->once())
            ->method('getAddress');

        $server->listen($socket);
        $server->getAddress();
    }

    public function testClose(): void
    {
        $socket = $this->getMock(ServerInterface::class);
        $server = new OutboundServer('127.0.0.1', 8084);

        $socket
            ->expects($this->once())
            ->method('close');

        $server->listen($socket);
        $server->close();
    }
}
