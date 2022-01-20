<?php
/**
 * RTCKit\React\ESL\RemoteOutboundClient Class
 */
declare(strict_types = 1);

namespace RTCKit\React\ESL;

use Evenement\EventEmitter;

/**
 * Remote Outbound ESL Client class
 */
class RemoteOutboundClient extends EventEmitter
{
    use RequestorTrait;

    protected bool $connected = false;

    /**
     * RemoteOutboundClient constructor
     *
     * @param AsyncConnection $esl
     */
    public function __construct(AsyncConnection $esl)
    {
        $this->esl = $esl;
    }

    /**
     * Retrieves connected status
     *
     * @return bool
     */
    public function getConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Sets connected status
     *
     * @param bool $connected
     */
    public function setConnected(bool $connected): void
    {
        $this->connected = $connected;
    }

    /**
     * Terminates the TCP connection
     */
    public function close(): void
    {
        $this->esl->close();
    }
}
