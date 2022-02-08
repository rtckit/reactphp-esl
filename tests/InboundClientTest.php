<?php

declare(strict_types = 1);

namespace RTCKit\React\ESL\Tests;

use RTCKit\React\ESL\{
    AsyncConnection,
    InboundClient
};
use RTCKit\React\ESL\Exception\ReactESLException;

use React\Promise\{
    Deferred,
    PromiseInterface
};
use React\Stream\ThroughStream;
use React\Socket\{
    ConnectionInterface,
    Connector,
    ConnectorInterface
};
use RTCKit\ESL;
use stdClass;
use function React\Promise\{
    reject,
    resolve
};

/**
 * Class InboundClientTest.
 *
 * @covers \RTCKit\React\ESL\InboundClient
 */
class InboundClientTest extends TestCase
{
    public function testConstructorWithoutUser(): void
    {
        $client = new InboundClient('127.0.0.1', 8021, 'password');

        $this->assertEquals('127.0.0.1', $this->getPropertyValue($client, 'host'));
        $this->assertEquals(8021, $this->getPropertyValue($client, 'port'));
        $this->assertFalse($this->isPropertyInitialized($client, 'user'));
        $this->assertEquals('password', $this->getPropertyValue($client, 'password'));
        $this->assertInstanceOf(Connector::class, $this->getPropertyValue($client, 'connector'));
    }

    public function testConstructorWithUser(): void
    {
        $client = new InboundClient('127.0.0.1', 8021, 'user', 'password');

        $this->assertEquals('127.0.0.1', $this->getPropertyValue($client, 'host'));
        $this->assertEquals(8021, $this->getPropertyValue($client, 'port'));
        $this->assertEquals('user', $this->getPropertyValue($client, 'user'));
        $this->assertEquals('password', $this->getPropertyValue($client, 'password'));
        $this->assertInstanceOf(Connector::class, $this->getPropertyValue($client, 'connector'));
    }

    public function testConnectSuccessful(): void
    {
        $client = new InboundClient('127.0.0.1', 8021, 'password');
        $connector = $this->getAbstractMock(ConnectorInterface::class);

        $this->setPropertyValue($client, 'connector', $connector);

        $stream = $this->getAbstractMock(ConnectionInterface::class);

        $connector->method('connect')
            ->with('tcp://127.0.0.1:8021')
            ->willReturn(resolve($stream));

        $this->assertInstanceOf(PromiseInterface::class, $client->connect());
        $this->assertEquals($stream, $this->getPropertyValue($client, 'stream'));
        $this->assertInstanceOf(AsyncConnection::class, $this->getPropertyValue($client, 'esl'));
    }

    public function testConnectDisconnectEvent(): void
    {
        $client = new InboundClient('127.0.0.1', 8021, 'password');
        $connector = $this->getAbstractMock(ConnectorInterface::class);

        $this->setPropertyValue($client, 'connector', $connector);

        $stream = new ThroughStream;

        $connector->method('connect')
            ->with('tcp://127.0.0.1:8021')
            ->willReturn(resolve($stream));

        $this->assertInstanceOf(PromiseInterface::class, $client->connect());

        $client->on('disconnect', function () {
            $this->assertTrue(true, 'disconnect event to be dispatched');
        });

        $stream->close();
    }

    public function testConnectFailed(): void
    {
        $client = new InboundClient('127.0.0.1', 8021, 'incorrect');
        $connector = $this->getAbstractMock(ConnectorInterface::class);

        $this->setPropertyValue($client, 'connector', $connector);

        $stream = $this->getAbstractMock(ConnectionInterface::class);

        $connector->method('connect')
            ->with('tcp://127.0.0.1:8021')
            ->willReturn(reject(new \Exception('Something went wrong')));

        $this->assertInstanceOf(PromiseInterface::class, $client->connect());
        $this->assertFalse($this->isPropertyInitialized($client, 'stream'));
        $this->assertFalse($this->isPropertyInitialized($client, 'esl'));
    }

    public function testDataHandlerOnParsingError(): void
    {
        $context = $this->prepareClientAndConnectors();
        $this->setPropertyValue($context->client, 'authenticated', true);
        $dataHandler = $this->getMethod($context->client, 'dataHandler');

        $this->setPropertyValue($context->client, 'esl', new AsyncConnection(AsyncConnection::INBOUND_CLIENT));

        $context->client->on('error', function (\Throwable $t) {
            $this->assertInstanceOf(ESL\Exception\ESLException::class, $t);
        });

        $dataHandler->invokeArgs($context->client, ["Content-Type: bogus/no-such-thing\n\n"]);
    }

    public function testDataHandlerOnPreAuth(): void
    {
        $context = $this->prepareClientAndConnectors();
        $dataHandler = $this->getMethod($context->client, 'dataHandler');

        $context->client->on('event', function ($response) {
            $this->fail('Event handler should not be invoked');
        });

        $context->client->on('log', function ($response) {
            $this->fail('Log handler should not be invoked');
        });

        $context->client->on('disconnect', function ($response) {
            $this->fail('Disconnect handler should not be invoked');
        });

        $authRequest = "Content-Type: auth/request\n\n";

        $context->esl
            ->method('consume')
            ->with($authRequest, [])
            ->will($this->returnCallback(function (string $chunk, array &$responses): int {
                $responses = [ESL\Response::parse($chunk)];

                return ESL\Connection::SUCCESS;
            }));

        $dataHandler->invokeArgs($context->client, [$authRequest]);
        $this->assertTrue(true, 'No exception to be thrown');
    }

    public function testDataHandlerOnEvent(): void
    {
        $context = $this->prepareClientAndConnectors();
        $this->setPropertyValue($context->client, 'authenticated', true);
        $dataHandler = $this->getMethod($context->client, 'dataHandler');

        $context->client->on('event', function ($response) {
            if ($response instanceof ESL\Response\TextEventJson) {
                $this->assertTrue(true, 'Received JSON event');
            } else if ($response instanceof ESL\Response\TextEventPlain) {
                $this->assertTrue(true, 'Received plain event');
            } else if ($response instanceof ESL\Response\TextEventXml) {
                $this->assertTrue(true, 'Received XML event');
            } else {
                $this->fail('Unrecognized event');
            }
        });

        $jsonEvent = "Content-Type: text/event-json\n\n";
        $plainEvent = "Content-Type: text/event-plain\n\n";
        $xmlEvent = "Content-Type: text/event-xml\n\n";

        $context->esl
            ->method('consume')
            ->withConsecutive(
                [$jsonEvent, []],
                [$plainEvent, []],
                [$xmlEvent, []],
            )
            ->will($this->returnCallback(function (string $chunk, array &$responses): int {
                $responses = [ESL\Response::parse($chunk)];

                return ESL\Connection::SUCCESS;
            }));

        $dataHandler->invokeArgs($context->client, [$jsonEvent]);
        $dataHandler->invokeArgs($context->client, [$plainEvent]);
        $dataHandler->invokeArgs($context->client, [$xmlEvent]);
        $this->assertTrue(true, 'No exception to be thrown');
    }

    public function testDataHandlerOnLog(): void
    {
        $context = $this->prepareClientAndConnectors();
        $this->setPropertyValue($context->client, 'authenticated', true);
        $dataHandler = $this->getMethod($context->client, 'dataHandler');

        $context->client->on('log', function ($response) {
            if ($response instanceof ESL\Response\LogData) {
                $this->assertTrue(true, 'Received log data');
            } else {
                $this->fail('Unrecognized log data');
            }
        });

        $logData = "Content-Type: log/data\n\n";

        $context->esl
            ->method('consume')
            ->with($logData, [])
            ->will($this->returnCallback(function (string $chunk, array &$responses): int {
                $responses = [ESL\Response::parse($chunk)];

                return ESL\Connection::SUCCESS;
            }));

        $dataHandler->invokeArgs($context->client, [$logData]);
        $this->assertTrue(true, 'No exception to be thrown');
    }

    public function testDataHandlerOnDisconnectNotice(): void
    {
        $context = $this->prepareClientAndConnectors();
        $this->setPropertyValue($context->client, 'authenticated', true);
        $dataHandler = $this->getMethod($context->client, 'dataHandler');

        $context->client->on('disconnect', function ($response) {
            if ($response instanceof ESL\Response\TextDisconnectNotice) {
                $this->assertTrue(true, 'Received disconnect notice');
            } else {
                $this->fail('Unrecognized notice');
            }
        });

        $notice = "Content-Type: text/disconnect-notice\n\n";

        $context->esl
            ->method('consume')
            ->with($notice, [])
            ->will($this->returnCallback(function (string $chunk, array &$responses): int {
                $responses = [ESL\Response::parse($chunk)];

                return ESL\Connection::SUCCESS;
            }));

        $dataHandler->invokeArgs($context->client, [$notice]);
        $this->assertTrue(true, 'No exception to be thrown');
    }

    public function testDataHandlerOnOutOfSequence(): void
    {
        $context = $this->prepareClientAndConnectors();
        $this->setPropertyValue($context->client, 'authenticated', true);
        $dataHandler = $this->getMethod($context->client, 'dataHandler');

        $response = "Content-Type: api/response\n\n";

        $context->client->on('error', function (\Throwable $t) {
            $this->assertInstanceOf(ReactESLException::class, $t);
        });

        $context->esl
            ->method('consume')
            ->with($response, [])
            ->will($this->returnCallback(function (string $chunk, array &$responses): int {
                $responses = [ESL\Response::parse($chunk)];

                return ESL\Connection::SUCCESS;
            }));

        $dataHandler->invokeArgs($context->client, [$response]);
    }

    public function testDataHandlerOnProperResponse(): void
    {
        $deferred = new Deferred;
        $context = $this->prepareClientAndConnectors();
        $this->setPropertyValue($context->client, 'authenticated', true);
        $this->setPropertyValue($context->client, 'queue', [$deferred]);
        $dataHandler = $this->getMethod($context->client, 'dataHandler');

        $response = "Content-Type: api/response\n\n";

        $context->esl
            ->method('consume')
            ->with($response, [])
            ->will($this->returnCallback(function (string $chunk, array &$responses): int {
                $responses = [ESL\Response::parse($chunk)];

                return ESL\Connection::SUCCESS;
            }));

        $dataHandler->invokeArgs($context->client, [$response]);

        $deferred->promise()->then(function ($response) {
            $this->assertInstanceOf(ESL\Response\ApiResponse::class, $response);
        });
    }

    public function testPreAuthHandlerOnAuthRequestWithoutUser(): void
    {
        $context = $this->prepareClientAndConnectors();
        $preAuthHandler = $this->getMethod($context->client, 'preAuthHandler');

        $context->esl
            ->expects($this->once())
            ->method('emit')
            ->will($this->returnCallback(function (ESL\Request\Auth $request) {
                $this->assertEquals('password', $request->getParameters());
            }));

        $preAuthHandler->invokeArgs($context->client, [new ESL\Response\AuthRequest]);
    }

    public function testPreAuthHandlerOnAuthRequestWithUser(): void
    {
        $context = $this->prepareClientAndConnectors();
        $this->setPropertyValue($context->client, 'user', 'user');
        $preAuthHandler = $this->getMethod($context->client, 'preAuthHandler');

        $context->esl
            ->expects($this->once())
            ->method('emit')
            ->will($this->returnCallback(function (ESL\Request\Auth $request) {
                $this->assertEquals('user:password', $request->getParameters());
            }));

        $preAuthHandler->invokeArgs($context->client, [new ESL\Response\AuthRequest]);
    }

    public function testPreAuthHandlerOnCommandReplySuccess(): void
    {
        $context = $this->prepareClientAndConnectors();
        $preAuthHandler = $this->getMethod($context->client, 'preAuthHandler');
        $preAuthHandler->invokeArgs($context->client, [
            (new ESL\Response\CommandReply)->setHeader(ESL\AbstractHeader::REPLY_TEXT, '+OK accepted')
        ]);

        $this->assertTrue($this->getPropertyValue($context->client, 'authenticated'));
    }

    public function testPreAuthHandlerOnDisconnectNotice(): void
    {
        $context = $this->prepareClientAndConnectors();
        $preAuthHandler = $this->getMethod($context->client, 'preAuthHandler');

        $context->client->on('disconnect', function ($response) {
            $this->assertInstanceOf(ESL\Response\TextDisconnectNotice::class, $response);
        });

        $preAuthHandler->invokeArgs($context->client, [new ESL\Response\TextDisconnectNotice]);
    }

    public function testPreAuthHandlerOnRudeRejection(): void
    {
        $context = $this->prepareClientAndConnectors();
        $preAuthHandler = $this->getMethod($context->client, 'preAuthHandler');

        $preAuthHandler->invokeArgs($context->client, [new ESL\Response\TextRudeRejection]);
        $context->promise
            ->then(function () {
                $this->fail('Should never resolve');
            })
            ->otherwise(function (\Throwable $t) {
                $this->assertInstanceOf(ReactESLException::class, $t);
            });
    }

    public function testPreAuthHandlerOnUnexpectedResponse(): void
    {
        $context = $this->prepareClientAndConnectors();
        $preAuthHandler = $this->getMethod($context->client, 'preAuthHandler');

        $preAuthHandler->invokeArgs($context->client, [new ESL\Response\ApiResponse]);
        $context->promise
            ->then(function () {
                $this->fail('Should never resolve');
            })
            ->otherwise(function (\Throwable $t) {
                $this->assertInstanceOf(ReactESLException::class, $t);
            });
    }

    public function testClose(): void
    {
        $context = $this->prepareClientAndConnectors();

        $context->stream->expects($this->once())->method('close');
        $context->client->close();
    }

    private function prepareClientAndConnectors(): stdClass
    {
        $client = new InboundClient('127.0.0.1', 8021, 'password');
        $connector = $this->getAbstractMock(ConnectorInterface::class);

        $this->setPropertyValue($client, 'connector', $connector);

        $stream = $this->getAbstractMock(ConnectionInterface::class);

        $connector->method('connect')
            ->with('tcp://127.0.0.1:8021')
            ->willReturn(resolve($stream));

        $promise = $client->connect();

        $esl = $this->getMock(AsyncConnection::class);

        $this->setPropertyValue($client, 'esl', $esl);

        $ret = new stdClass;
        $ret->client = $client;
        $ret->stream = $stream;
        $ret->esl = $esl;
        $ret->promise = $promise;

        return $ret;
    }
}
