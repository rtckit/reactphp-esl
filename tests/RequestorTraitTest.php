<?php

declare(strict_types = 1);

namespace RTCKit\React\ESL\Tests;

use RTCKit\React\ESL\{
    AsyncConnection,
    RequestorTrait
};

use RTCKit\ESL;
use function React\Promise\resolve;

/**
 * Class RequestorTraitTest.
 *
 * @covers \RTCKit\React\ESL\RequestorTrait
 */
class RequestorTraitTest extends TestCase
{
    private $requestor;

    protected function setUp(): void
    {
        $this->requestor = $this->getMockForTrait(RequestorTrait::class, [], '', true, true, true, ['enqueue']);
    }

    public function testApi(): void
    {
        $request = new ESL\Request\Api;

        $this->requestor
            ->expects($this->once())
            ->method('enqueue')
            ->with($request)
            ->willReturn(resolve());

        $this->requestor->api($request);
    }

    public function testBgApi(): void
    {
        $request = new ESL\Request\BgApi;

        $this->requestor
            ->expects($this->once())
            ->method('enqueue')
            ->with($request)
            ->willReturn(resolve());

        $this->requestor->bgapi($request);
    }

    public function testDivertEvents(): void
    {
        $parameters = 'ALL';
        $request = (new ESL\Request\DivertEvents)->setParameters($parameters);

        $this->requestor
            ->expects($this->once())
            ->method('enqueue')
            ->with($request)
            ->willReturn(resolve());

        $this->requestor->divertEvents($parameters);
    }

    public function testEvent(): void
    {
        $parameters = 'ALL';
        $request = (new ESL\Request\Event)->setParameters($parameters);

        $this->requestor
            ->expects($this->once())
            ->method('enqueue')
            ->with($request)
            ->willReturn(resolve());

        $this->requestor->event($parameters);
    }

    public function testExit(): void
    {
        $this->requestor
            ->expects($this->once())
            ->method('enqueue')
            ->with(new ESL\Request\Eksit)
            ->willReturn(resolve());

        $this->requestor->exit();
    }

    public function testLinger(): void
    {
        $this->requestor
            ->expects($this->once())
            ->method('enqueue')
            ->with(new ESL\Request\Linger)
            ->willReturn(resolve());

        $this->requestor->linger();
    }

    public function testLog(): void
    {
        $this->requestor
            ->expects($this->once())
            ->method('enqueue')
            ->with(new ESL\Request\Log)
            ->willReturn(resolve());

        $this->requestor->log();
    }

    public function testMyEvents(): void
    {
        $parameters = 'ALL';
        $request = (new ESL\Request\MyEvents)->setParameters($parameters);

        $this->requestor
            ->expects($this->once())
            ->method('enqueue')
            ->with($request)
            ->willReturn(resolve());

        $this->requestor->myEvents($parameters);
    }

    public function testResume(): void
    {
        $this->requestor
            ->expects($this->once())
            ->method('enqueue')
            ->with(new ESL\Request\Resume)
            ->willReturn(resolve());

        $this->requestor->resume();
    }

    public function testSendMsg(): void
    {
        $request = new ESL\Request\SendMsg;

        $this->requestor
            ->expects($this->once())
            ->method('enqueue')
            ->with($request)
            ->willReturn(resolve());

        $this->requestor->sendMsg($request);
    }

    public function testEnqueue(): void
    {
        $requestor = new class {
            use RequestorTrait;
        };

        $esl = $this->getMockSetMethods(AsyncConnection::class, ['emit']);
        $this->setPropertyValue($requestor, 'esl', $esl);

        $request = (new ESL\Request\Api)->setParameters('version');

        $esl->expects($this->once())
            ->method('emit')
            ->with($request);

        $enqueue = $this->getMethod($requestor, 'enqueue');

        $promise = $enqueue->invokeArgs($requestor, [$request]);

        $requestor->queue[0]->resolve((new ESL\Response\ApiResponse)->setBody('+OK'));

        $promise->then(function (ESL\Response $response) {
            $this->assertEquals('+OK', $response->getBody());
        });
    }
}
