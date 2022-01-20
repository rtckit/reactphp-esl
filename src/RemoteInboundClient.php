<?php
/**
 * RTCKit\React\ESL\RemoteInboundClient Class
 */
declare(strict_types = 1);

namespace RTCKit\React\ESL;

use Evenement\EventEmitter;
use RTCKit\ESL\Response;

/**
 * Remote Inbound ESL Client class
 */
class RemoteInboundClient extends EventEmitter
{
    protected bool $authenticated = false;

    protected AsyncConnection $esl;

    /**
     * RemoteInboundClient constructor
     *
     * @param AsyncConnection $esl
     */
    public function __construct(AsyncConnection $esl)
    {
        $this->esl = $esl;
    }

    /**
     * Retrieves authentication status
     *
     * @return bool
     */
    public function getAuthenticated(): bool
    {
        return $this->authenticated;
    }

    /**
     * Sets authentication status
     *
     * @param bool $authenticated
     */
    public function setAuthenticated(bool $authenticated): void
    {
        $this->authenticated = $authenticated;
    }

    /**
     * Sends ESL messages
     *
     * @param Response $response
     */
    public function send(Response $response): void
    {
        $this->esl->emit($response);
    }

    /**
     * Terminates the TCP connection
     */
    public function close(): void
    {
        $this->esl->close();
    }
}
