<?php
/**
 * RTCKit\React\ESL\AsyncConnection Class
 */
declare(strict_types = 1);

namespace RTCKit\React\ESL;

use React\Socket\ConnectionInterface;
use React\Stream\DuplexStreamInterface;
use RTCKit\ESL\Connection;

/**
 * Asynchronous ESL connection class
 */
class AsyncConnection extends Connection
{
    protected DuplexStreamInterface $stream;

    /**
     * Assigns the ReactPHP TCP connector
     *
     * @param DuplexStreamInterface $stream
     */
    public function setStream(DuplexStreamInterface $stream): void
    {
        $this->stream = $stream;
    }

    /**
     * Performs the actual write I/O
     *
     * @param string $bytes
     */
    protected function emitBytes(string $bytes): void
    {
        $this->stream->write($bytes);
    }

    /**
     * Closes the TCP connection
     */
    public function close(): void
    {
        $this->stream->close();
    }

    /**
     * Retrieves the bound socket address
     *
     * @return ?string
     */
    public function getAddress(): ?string
    {
        if ($this->stream instanceof ConnectionInterface) {
            if (($this->role === self::INBOUND_CLIENT) || ($this->role === self::OUTBOUND_CLIENT)) {
                return $this->stream->getLocalAddress();
            } else {
                return $this->stream->getRemoteAddress();
            }
        }

        return null;
    }
}
