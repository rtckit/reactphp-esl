<?php
/**
 * RTCKit\React\ESL\OutboundClient Class
 */
declare(strict_types = 1);

namespace RTCKit\React\ESL;

use RTCKit\React\ESL\Exception\ReactESLException;

use Evenement\EventEmitter;
use React\Promise\{
    Deferred,
    PromiseInterface
};
use React\Stream\DuplexStreamInterface;
use React\Socket\{
    Connector,
    ConnectorInterface
};
use RTCKit\ESL\Request;
use Closure;
use Throwable;

/**
 * Outbound ESL Client class
 */
class OutboundClient extends EventEmitter
{
    protected string $host;

    protected int $port;

    protected bool $connected = false;

    protected AsyncConnection $esl;

    protected ConnectorInterface $connector;

    protected DuplexStreamInterface $stream;

    protected Deferred $deferredConnect;

    /**
     * OutboundClient constructor
     *
     * @param string $host
     * @param int $port
     */
    public function __construct(string $host, int $port)
    {
        $this->host = $host;
        $this->port = $port;

        $this->connector = new Connector;
    }

    /**
     * Initiates the TCP connection
     *
     * @return PromiseInterface
     */
    public function connect(): PromiseInterface
    {
        $this->deferredConnect = new Deferred;

        $this->connector
            ->connect("tcp://{$this->host}:{$this->port}")
            ->then(function (DuplexStreamInterface $conn) {
                $this->stream = $conn;
                $this->esl = new AsyncConnection(AsyncConnection::OUTBOUND_CLIENT);
                $this->esl->setStream($this->stream);
                $this->stream->on('data', Closure::fromCallable([$this, 'dataHandler']));
            })
            ->otherwise(function (Throwable $t) {
                $this->deferredConnect->reject($t);
            });

        return $this->deferredConnect->promise();
    }

    /**
     * Processes raw inbound bytes
     *
     * @param string $chunk
     */
    protected function dataHandler(string $chunk): void
    {
        try {
            $this->esl->consume($chunk, $requests);
        } catch (Throwable $t) {
            $this->emit('error', [$t]);

            return;
        }

        assert(!is_null($requests));

        foreach ($requests as $request) {
            if (!$this->connected) {
                if ($request instanceof Request\Connect) {
                    $this->connected = true;
                    $this->deferredConnect->resolve();
                }

                continue;
            }

            $this->emit('request', [$request]);
        }
    }

    /**
     * Closes the TCP connection
     */
    public function close(): void
    {
        $this->stream->close();
    }
}
