<?php

declare(strict_types = 1);

namespace RTCKit\React\ESL\Examples;

error_reporting(-1);

require(__DIR__ . '/../vendor/autoload.php');

use Clue\React\Stdio\Stdio;
use RTCKit\ESL;
use RTCKit\React\ESL\InboundClient;

$stdio = new Stdio;
$client = new InboundClient('127.0.0.1', 8021, 'ClueCon');
$client
    ->connect()
    ->then(function (InboundClient $client) {
        $request = new ESL\Request\Api;
        $request->setParameters('switchname');

        return $client->api($request);
    })
    ->then(function (ESL\Response $response) use ($client, $stdio) {
        $stdio->setPrompt('freeswitch@' . trim($response->getBody()) . '> ');

        $client->log();

        $stdio->on('data', function ($line) use ($client, $stdio) {
            $line = trim($line);

            if (($line === 'exit') || ($line === '...')) {
                echo PHP_EOL;
                exit(0);
            }

            $client->api((new ESL\Request\Api)->setParameters($line))
                ->then (function (ESL\Response $response) use ($stdio) {
                    $stdio->write($response->getBody() . PHP_EOL);
                });
        });

        $client->on('log', function ($log) use ($stdio) {
            $stdio->write($log->getBody());
        });

        $client->on('disconnect', function (?ESL\Response $response = null) use ($stdio) {
            if (!isset($response)) {
                echo PHP_EOL . 'FreeSWITCH disconnected unexpectedly' . PHP_EOL;
                exit(1);
            }

            echo PHP_EOL . 'FreeSWITCH disconnected gracefully' . PHP_EOL;
            exit(0);
        });
    })
    ->otherwise(function (\Throwable $t) {
        echo 'Yikes! ' . $t->getMessage() . PHP_EOL;
        exit(1);
    });
