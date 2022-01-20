<?php
/**
 * RTCKit\React\ESL\RequestorTrait Class
 */
declare(strict_types = 1);

namespace RTCKit\React\ESL;

use React\Promise\{
    Deferred,
    PromiseInterface
};
use RTCKit\ESL\Request;

/**
 * Asynchronous ESL requestor trait
 */
trait RequestorTrait
{
    protected AsyncConnection $esl;

    /** @var array<Deferred> */
    public array $queue = [];

    /**
     * Issues an `api` command
     *
     * @param Request\Api $request
     * @return PromiseInterface
     */
    public function api(Request\Api $request): PromiseInterface
    {
        return $this->enqueue($request);
    }

    /**
     * Issues a `bgapi` command
     *
     * @param Request\BgApi $request
     * @return PromiseInterface
     */
    public function bgApi(Request\BgApi $request): PromiseInterface
    {
        return $this->enqueue($request);
    }

    /**
     * Issues a `divert_events` command
     *
     * @param string $parameters
     * @return PromiseInterface
     */
    public function divertEvents(string $parameters): PromiseInterface
    {
        return $this->enqueue((new Request\DivertEvents)->setParameters($parameters));
    }

    /**
     * Issues an `events` command
     *
     * @param string $parameters
     * @return PromiseInterface
     */
    public function event(string $parameters): PromiseInterface
    {
        return $this->enqueue((new Request\Event)->setParameters($parameters));
    }

    /**
     * Issues an `exit` command
     *
     * @return PromiseInterface
     */
    public function exit(): PromiseInterface
    {
        return $this->enqueue(new Request\Eksit);
    }

    /**
     * Issues a `linger` command
     *
     * @return PromiseInterface
     */
    public function linger(): PromiseInterface
    {
        return $this->enqueue(new Request\Linger);
    }

    /**
     * Issues a `log` command
     *
     * @return PromiseInterface
     */
    public function log(): PromiseInterface
    {
        return $this->enqueue(new Request\Log);
    }

    /**
     * Issues a `myevents` command
     *
     * @param string $parameters
     * @return PromiseInterface
     */
    public function myEvents(string $parameters): PromiseInterface
    {
        return $this->enqueue((new Request\MyEvents)->setParameters($parameters));
    }

    /**
     * Issues a `resume` command
     *
     * @return PromiseInterface
     */
    public function resume(): PromiseInterface
    {
        return $this->enqueue(new Request\Resume);
    }

    /**
     * Issues a `sendmsg` command
     *
     * @param Request\SendMsg $request
     * @return PromiseInterface
     */
    public function sendMsg(Request\SendMsg $request): PromiseInterface
    {
        return $this->enqueue($request);
    }

    /**
     * Adds a new request to the outgoing queue
     *
     * @param Request $request
     * @return PromiseInterface
     */
    protected function enqueue(Request $request): PromiseInterface
    {
        $deferred = new Deferred;
        $this->queue[] = $deferred;

        $this->esl->emit($request);

        return $deferred->promise();
    }
}
