<?php
/**
 * RTCKit\React\ESL\InboundServer Class
 */
declare(strict_types = 1);

namespace RTCKit\React\ESL;

use Evenement\EventEmitter;
use React\Socket\{
    ServerInterface,
    SocketServer
};
use React\Stream\DuplexStreamInterface;
use RTCKit\ESL\{
    Response,
    Request
};
use Closure;
use Throwable;

/**
 * Inbound ESL Server class
 */
class InboundServer extends EventEmitter
{
    protected string $host;

    protected int $port;

    protected ServerInterface $socket;

    /**
     * InboundServer constructor
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
        $esl = new AsyncConnection(AsyncConnection::INBOUND_SERVER);
        $client = new RemoteInboundClient($esl);

        $stream->on('data', function (string $chunk) use ($client, $esl) {
            try {
                $esl->consume($chunk, $requests);
            } catch (Throwable $t) {
                $client->emit('error', [$t]);

                return;
            }

            assert(!is_null($requests));

            if (!$client->getAuthenticated()) {
                if (isset($requests[0])) {
                    if ($requests[0] instanceof Request\Auth) {
                        $this->emit('auth', [$client, $requests[0]]);
                    } else {
                        $esl->emit((new Response\CommandReply)->setHeader('Reply-Text', '-ERR command not found'));
                    }
                }

                return;
            }

            foreach ($requests as $request) {
                $client->emit('request', [$request]);
            }
        });

        $stream->on('close', function () use ($client) {
            $this->emit('disconnect', [$client]);
        });

        $esl->setStream($stream);
        $esl->emit(new Response\AuthRequest);
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
