<?php

declare(strict_types = 1);

namespace RTCKit\React\ESL\Tests;

use RTCKit\React\ESL\{
    InboundServer,
    RemoteInboundClient
};

use React\EventLoop\Loop;
use React\Socket\{
    ConnectionInterface,
    ServerInterface
};
use React\Stream\ThroughStream;
use RTCKit\ESL;

/**
 * Class InboundServerTest.
 *
 * @covers \RTCKit\React\ESL\InboundServer
 */
class InboundServerTest extends TestCase
{
    public function testConstructor(): void
    {
        $server = new InboundServer('127.0.0.1', 8021);

        $this->assertEquals('127.0.0.1', $this->getPropertyValue($server, 'host'));
        $this->assertEquals(8021, $this->getPropertyValue($server, 'port'));
    }

    public function testListenDefaultTcpSock(): void
    {
        $server = new InboundServer('127.0.0.1', 0);
        $server->listen();

        $this->assertInstanceOf(ServerInterface::class, $this->getPropertyValue($server, 'socket'));
        Loop::stop();
    }

    public function testListen(): void
    {
        $socket = $this->getMock(ServerInterface::class);
        $server = new InboundServer('127.0.0.1', 8021);

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
        $server = new InboundServer('127.0.0.1', 0);
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
        $server = new InboundServer('127.0.0.1', 8021);

        $server->listen($socket);
        $connHandler = $this->getMethod($server, 'connectionHandler');
        $stream = new ThroughStream(function (string $bytes) {
            try {
                $response = ESL\Response::parse($bytes);

                if ($response instanceof ESL\Response\AuthRequest) {
                    return (new ESL\Request\Auth)->setParameters('ClueCon')->render();
                }
            } catch (ESL\Exception\ESLException $e) {}

            return $bytes;
        });

        $server->on('auth', function (RemoteInboundClient $client, ESL\Request\Auth $request) use ($stream) {
            $client->on('error', function (\Throwable $t) {
                $this->assertInstanceOf(ESL\Exception\ESLException::class, $t);
            });

            $this->assertEquals('ClueCon', $request->getParameters());

            $client->setAuthenticated(true);

            $stream->write("api version\n\n");
            $stream->write("bogus\n\n");
        });

        $connHandler->invokeArgs($server, [$stream]);
    }

    public function testConnectionHandlerNotAuthenticated(): void
    {
        $socket = $this->getMock(ServerInterface::class);
        $server = new InboundServer('127.0.0.1', 8021);

        $server->listen($socket);
        $connHandler = $this->getMethod($server, 'connectionHandler');
        $stream = new ThroughStream(function (string $bytes) {
            try {
                $response = ESL\Response::parse($bytes);

                if ($response instanceof ESL\Response\AuthRequest) {
                    return (new ESL\Request\Api)->setParameters('version')->render();
                }

                $this->assertInstanceOf(ESL\Response\CommandReply::class, $response);
                $this->assertFalse($response->isSuccessful());

                return '';
            } catch (ESL\Exception\ESLException $e) {
            }

            return $bytes;
        });

        $connHandler->invokeArgs($server, [$stream]);
    }

    public function testConnectionHandlerOnDisconnect(): void
    {
        $socket = $this->getMock(ServerInterface::class);
        $server = new InboundServer('127.0.0.1', 8021);

        $server->listen($socket);
        $connHandler = $this->getMethod($server, 'connectionHandler');
        $stream = new ThroughStream(function (string $bytes) {
            $stream->close();

            return '';
        });

        $server->on('disconnect', function (RemoteInboundClient $client) {
            $this->assertTrue(true, 'Disconnect handler to be invoked');
        });

        $connHandler->invokeArgs($server, [$stream]);
    }

    public function testGetAddress(): void
    {
        $socket = $this->getMock(ServerInterface::class);
        $server = new InboundServer('127.0.0.1', 8021);

        $socket
            ->expects($this->once())
            ->method('getAddress');

        $server->listen($socket);
        $server->getAddress();
    }

    public function testClose(): void
    {
        $socket = $this->getMock(ServerInterface::class);
        $server = new InboundServer('127.0.0.1', 8021);

        $socket
            ->expects($this->once())
            ->method('close');

        $server->listen($socket);
        $server->close();
    }
}
