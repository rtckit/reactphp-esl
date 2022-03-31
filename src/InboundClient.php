<?php
/**
 * RTCKit\React\ESL\InboundClient Class
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
use RTCKit\ESL\{
    AbstractHeader,
    MessageInterface,
    Request,
    Response,
    ResponseInterface
};
use Closure;
use Throwable;

/**
 * Inbound ESL Client class
 */
class InboundClient extends EventEmitter
{
    use RequestorTrait;

    protected string $host;

    protected int $port;

    protected string $user;

    protected string $password;

    protected bool $authenticated = false;

    protected ConnectorInterface $connector;

    protected DuplexStreamInterface $stream;

    protected Deferred $deferredConnect;

    /**
     * InboundClient constructor
     *
     * @param string $host
     * @param int $port
     * @param string $userOrPassword
     * @param null|string $password
     */
    public function __construct(string $host, int $port, string $userOrPassword, ?string $password = null)
    {
        $this->host = $host;
        $this->port = $port;

        if (!isset($password)) {
            $this->password = $userOrPassword;
        } else {
            $this->user = $userOrPassword;
            $this->password = $password;
        }

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
                $this->esl = new AsyncConnection(AsyncConnection::INBOUND_CLIENT);
                $this->esl->setStream($this->stream);
                $this->stream->on('data', Closure::fromCallable([$this, 'dataHandler']));
                $this->stream->on('close', function () {
                    $this->emit('disconnect');
                });
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
        $responses = [];

        try {
            $ret = $this->esl->consume($chunk, $responses);
        } catch (Throwable $t) {
            $this->emit('error', [$t]);

            return;
        }

        assert(!is_null($responses));

        foreach ($responses as $response) {
            if (!$this->authenticated) {
                $this->preAuthHandler($response);

                continue;
            }

            if (
                ($response instanceof Response\TextEventJson) ||
                ($response instanceof Response\TextEventPlain) ||
                ($response instanceof Response\TextEventXml)
            ) {
                $this->emit('event', [$response]);

                continue;
            }

            if ($response instanceof Response\LogData) {
                $this->emit('log', [$response]);

                continue;
            }

            if ($response instanceof Response\TextDisconnectNotice) {
                $this->emit('disconnect', [$response]);

                continue;
            }

            $deferred = array_shift($this->queue);

            if (is_null($deferred)) {
                $contentType = $response->getHeader(AbstractHeader::CONTENT_TYPE) ?? '';

                $this->emit(
                    'error',
                    [new ReactESLException(
                        'Unexpected reply received (ENOMSG) ' . $contentType,
                        defined('SOCKET_ENOMSG') ? (int)SOCKET_ENOMSG : 42
                    )]
                );
            } else {
                $deferred->resolve($response);
            }
        }
    }

    /**
     * Processes early inbound messages (authentication)
     *
     * @param MessageInterface $response
     */
    public function preAuthHandler(MessageInterface $response): void
    {
        if ($response instanceof Response\AuthRequest) {
            $request = new Request\Auth;

            if (isset($this->user)) {
                $request->setParameters("{$this->user}:{$this->password}");
            } else {
                $request->setParameters("{$this->password}");
            }

            $this->esl->emit($request);
        } else if ($response instanceof Response\CommandReply) {
            if ($response->getHeader(AbstractHeader::REPLY_TEXT) === '+OK accepted') {
                $this->authenticated = true;
                $this->deferredConnect->resolve($this);
            } else {
                $this->deferredConnect->reject(new ReactESLException('Authentication failed'));
            }
        } else if ($response instanceof Response\TextDisconnectNotice) {
            $this->emit('disconnect', [$response]);
        } else if ($response instanceof Response\TextRudeRejection) {
            $this->deferredConnect->reject(new ReactESLException('Access denied'));
        } else {
            $this->deferredConnect->reject(new ReactESLException('Unexpected response (expecting auth/request)'));
        }
    }

    /**
     * Retrieves bound socket address
     *
     * @return ?string
     */
    public function getAddress(): ?string
    {
        return $this->esl->getAddress();
    }

    /**
     * Closes the TCP connection
     */
    public function close(): void
    {
        $this->stream->close();
    }
}
