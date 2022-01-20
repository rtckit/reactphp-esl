# Asynchronous Event Socket Layer library for PHP

[![Build Status](https://app.travis-ci.com/rtckit/reactphp-esl.svg?branch=main)](https://app.travis-ci.com/rtckit/reactphp-esl)
[![Latest Stable Version](https://poser.pugx.org/rtckit/react-esl/v/stable.png)](https://packagist.org/packages/rtckit/react-esl)
[![Test Coverage](https://api.codeclimate.com/v1/badges/aff5ee8e8ef3b51689c2/test_coverage)](https://codeclimate.com/github/rtckit/reactphp-esl/test_coverage)
[![Maintainability](https://api.codeclimate.com/v1/badges/aff5ee8e8ef3b51689c2/maintainability)](https://codeclimate.com/github/rtckit/reactphp-esl/maintainability)
[![License](https://img.shields.io/badge/license-MIT-blue)](LICENSE)

## Quickstart

[FreeSWITCH](https://github.com/signalwire/freeswitch)'s Event Socket Layer is a TCP control interface enabling the development of complex dynamic dialplans/workflows. You can learn more about its [inbound mode](https://freeswitch.org/confluence/display/FREESWITCH/mod_event_socket) as well as its [outbound mode](https://freeswitch.org/confluence/display/FREESWITCH/Event+Socket+Outbound) on the FreeSWITCH website.

This library builds on top of [ReactPHP](https://reactphp.org/) and [RTCKit\ESL](https://github.com/rtckit/php-esl) and provides classes for four ESL elements: InboundClient and OutboundServer as well as InboundServer and OutboundClient. The former pair is more common and interfaces with FreeSWITCH for building RTC applications. The latter pair can be used to impersonate FreeSWITCH, for example in test suites, implementing message relays, security research etc. The directional terms (inbound/outbound) are relative to FreeSWITCH.

```

                         Inbound               Outbound

                 ┌──────────────────────┬─────────────────────┐
                 │                      │                     │
     Application │ InboundClient.php    │ OutboundServer.php  │
                 │                      │                     │
                 ├──────────────────────┼─────────────────────┤
                 │                      │                     │
     FreeSWITCH  │ InboundServer.php    │ OutboundClient.php  │
                 │                      │                     │
                 └──────────────────────┴─────────────────────┘
```

### Inbound Client Example

This usage mode refers to FreeSWITCH's inbound mode (usually listening on TCP 8021) and our application acts as the client. Typical interactions include issuing various requests and standing by for incoming events.

```php
/* Instantiate the object */
$client = new \RTCKit\React\ESL\InboundClient('127.0.0.1', 8021, 'ClueCon');
$client
    ->connect() /* Initiate the connection; the library handles the authentication process */
    ->then(function (\RTCKit\React\ESL\InboundClient $client) {
        /* At this point our connection is established and authenticated; we can fire up any requests */
        $request = new \RTCKit\ESL\Request\Api;
        $request->setParameters('switchname');

        return $client->api($request);
    })
    ->then(function (\RTCKit\ESL\Response $response) use ($client, $stdio) {
        $switchname = trim($response->getBody());

        echo 'Connected to ' . $switchname . PHP_EOL;

        /* Issue more requests here! */
    })
    ->otherwise(function (Throwable $t) {
        echo 'Something went wrong: ' . $t->getMessage() . PHP_EOL;
    });
```

### Outbound Server Example

In this mode, FreeSWITCH (acting as the client) connects to our application (usually listening on TCP 8084) when a dialplan invokes the `socket` application.

```php
/* Instantiate the object */
$server = new \RTCKit\React\ESL\OutboundServer('127.0.0.1', 8084);
/* Configure the handler */
$server->on('connect', function (\RTCKit\React\ESL\RemoteOutboundClient $client, \RTCKit\ESL\Response\CommandReply $response) {
    /* The library already sent the `connect` request at our behalf.
     * $response holds initial response. */
    $vars = $response->getHeaders();
    echo 'Outbound connection from ' . $vars['core-uuid'] . PHP_EOL;
    echo 'Call UUID ' . $vars['channel-unique-id'] . PHP_EOL;

    /* Issue requests */
    $client->resume();
    $client->linger();
    $client->myEvents('json');
    $client->divertEvents('on');

    /* Listen to events */
    $client->on('event', function (\RTCKit\ESL\Response\TextEventJson $response): void {
        /* Consume the event here! */
    });

    /* Add your business logic here */
    /* ... */

    /* Disconnect client */
    $client->close();
});
```

Lastly, the provided [examples](examples) are a good starting point.

## Requirements

**RTCKit\React\ESL** is compatible with PHP 7.4+.

## Installation

You can add the library as project dependency using [Composer](https://getcomposer.org/):

```sh
composer require rtckit/react-esl
```

If you only need the library during development, for instance when used in your test suite, then you should add it as a development-only dependency:

```sh
composer require --dev rtckit/react-esl
```

## Tests

To run the test suite, clone this repository and then install dependencies via Composer:

```sh
composer install
```

Then, go to the project root and run:

```bash
composer phpunit
```

### Static Analysis

In order to ensure high code quality, **RTCKit\React\ESL** uses [PHPStan](https://github.com/phpstan/phpstan) and [Psalm](https://github.com/vimeo/psalm):

```sh
composer phpstan
composer psalm
```

## License

MIT, see [LICENSE file](LICENSE).

### Acknowledgments

* [FreeSWITCH](https://github.com/signalwire/freeswitch), FreeSWITCH is a registered trademark of Anthony Minessale II

### Contributing

Bug reports (and small patches) can be submitted via the [issue tracker](https://github.com/rtckit/reactphp-esl/issues). Forking the repository and submitting a Pull Request is preferred for substantial patches.
