<?php
/**
 * RTCKit\React\ESL\OutboundServer Class
 */
declare(strict_types = 1);

namespace RTCKit\React\ESL;

use RTCKit\React\ESL\Exception\ReactESLException;

use Evenement\EventEmitter;
use React\Socket\{
    ServerInterface,
    SocketServer
};
use React\Stream\DuplexStreamInterface;
use RTCKit\ESL\{
    AbstractHeader,
    Request,
    Response
};
use Closure;
use Throwable;

/**
 * Outbound ESL Server class
 */
class OutboundServer extends EventEmitter
{
    use RequestorTrait;

    protected string $host;

    protected int $port;

    protected ServerInterface $socket;

    /**
     * OutboundServer constructor
     *
     * @param string $host
     * @param int $port
     */
    public function __construct(string $host, int $port)
    {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * Binds the TCP socket
     */
    public function listen(?ServerInterface $socket = null): void
    {
        if (isset($socket)) {
            $this->socket = $socket;
        } else {
            $this->socket = new SocketServer("tcp://{$this->host}:{$this->port}");
        }

        $this->socket->on('connection', Closure::fromCallable([$this, 'connectionHandler']));
        $this->socket->on('error', function(\Throwable $t) {
            $this->emit('error', [$t]);
        });
    }

    /**
     * Handles inbound TCP connections
     *
     * @param DuplexStreamInterface $stream
     */
    protected function connectionHandler(DuplexStreamInterface $stream): void
    {
        $esl = new AsyncConnection(AsyncConnection::OUTBOUND_SERVER);
        $client = new RemoteOutboundClient($esl);

        $stream->on('data', function (string $chunk) use ($client, $esl) {
            try {
                $esl->consume($chunk, $responses);
            } catch (Throwable $t) {
                $client->emit('error', [$t]);

                return;
            }

            assert(!is_null($responses));

            foreach ($responses as $response) {
                if (!$client->getConnected()) {
                    $client->setConnected(true);
                    $this->emit('connect', [$client, $response]);

                    continue;
                }

                if (
                    ($response instanceof Response\TextEventJson) ||
                    ($response instanceof Response\TextEventPlain) ||
                    ($response instanceof Response\TextEventXml)
                ) {
                    $client->emit('event', [$response]);

                    continue;
                }

                if ($response instanceof Response\TextDisconnectNotice) {
                    $this->emit('disconnect', [$client, $response]);

                    continue;
                }

                if (empty($client->queue)) {
                    $contentType = $response->getHeader(AbstractHeader::CONTENT_TYPE) ?? '';

                    $client->emit(
                        'error',
                        [new ReactESLException(
                            'Unexpected reply received (ENOMSG) ' . $contentType,
                            defined('SOCKET_ENOMSG') ? SOCKET_ENOMSG : 42
                        )]
                    );
                } else {
                    $deferred = array_shift($client->queue);
                    $deferred->resolve($response);
                }
            }
        });

        $stream->on('close', function () use ($client) {
            $this->emit('disconnect', [$client]);
        });

        $esl->setStream($stream);
        $esl->emit(new Request\Connect);
    }

    /**
     * Retrieves bound socket address
     *
     * @return ?string
     */
    public function getAddress(): ?string
    {
        return $this->socket->getAddress();
    }

    /**
     * Terminates the TCP socket
     */
    public function close(): void
    {
        $this->socket->close();
    }
}
