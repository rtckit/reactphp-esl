<?php

declare(strict_types = 1);

namespace RTCKit\React\ESL\Examples;

error_reporting(-1);

require(__DIR__ . '/../vendor/autoload.php');

use React\EventLoop\Loop;
use React\Promise\Deferred;
use RTCKit\ESL;
use RTCKit\React\ESL\{
    InboundServer,
    RemoteInboundClient
};
use function Clue\React\Block\await;

$clients = [];
$server = new InboundServer('127.0.0.1', 8021);

$server->on('auth', function (RemoteInboundClient $client, ESL\Request\Auth $request) use (&$clients) {
    $password = $request->getParameters();

    if (!isset($password) || ($password !== 'ClueCon')) {
        $client->send((new ESL\Response\CommandReply)->setHeader('reply-text', '-ERR invalid'));
        $client->close();

        return;
    }

    $client->send((new ESL\Response\CommandReply)->setHeader('reply-text', '+OK accepted'));
    $client->setAuthenticated(true);

    $clients[spl_object_id($client)] = $client;

    $client->on('request', function (ESL\Request $request) use ($client) {
        switch (get_class($request)) {
            case ESL\Request\Api::class:
                switch ($request->getParameters()) {
                    case 'version':
                        $client->send(
                            (new ESL\Response\ApiResponse)->setBody(
                                "FakeSWITCH Version 1.10.8-release-20-0000000000~64bit (-release-20-0000000000 64bit)\n"
                            )
                        );
                        return;

                    case 'switchname':
                    case 'hostname':
                        $client->send((new ESL\Response\ApiResponse)->setBody('fakeswitch'));
                        return;

                    /* Implement more API functions here */
                }

            /* Implement more ESL commands here */

            default:
                $client->send((new ESL\Response\CommandReply)->setHeader('Reply-Text', '-ERR command not found'));
                return;
        }
    });
});

$server->on('disconnect', function (RemoteInboundClient $client) use (&$clients) {
    unset($clients[spl_object_id($client)]);
});

Loop::addSignal(SIGINT, function () use (&$clients) {
    echo 'Caught SIGINT, shutting down ...' . PHP_EOL;

    $done = new Deferred;

    foreach ($clients as $client) {
        $client->send(new ESL\Response\TextDisconnectNotice);
    }

    Loop::futureTick(function() use ($done) {
        $done->resolve();
    });

    await($done->promise());

    Loop::stop();
    exit(0);
});

$server->listen();

echo 'Entering event loop. Press Ctrl+C to bail' . PHP_EOL;
