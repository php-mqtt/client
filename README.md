# php-mqtt/client

[![Latest Stable Version](https://poser.pugx.org/php-mqtt/client/v)](//packagist.org/packages/php-mqtt/client)
[![Total Downloads](https://poser.pugx.org/php-mqtt/client/downloads)](//packagist.org/packages/php-mqtt/client)
![Tests](https://github.com/php-mqtt/client/workflows/Tests/badge.svg)
[![License](https://poser.pugx.org/php-mqtt/client/license)](//packagist.org/packages/php-mqtt/client)

[`php-mqtt/client`](https://packagist.org/packages/php-mqtt/client) was created by, and is maintained by [Namoshek](https://github.com/namoshek).
It allows you to connect to an MQTT broker where you can publish messages and subscribe to topics.
The implementation supports all QoS levels ([with limitations](#limitations)).

## Installation

```bash
composer require php-mqtt/client
```

This library requires PHP version 7.2 or higher.

## Usage

### Publish

A very basic publish example requires only three steps: connect, publish and close

```php
$server   = 'some-broker.example.com';
$port     = 1883;
$clientId = 'test-publisher';

$mqtt = new \PhpMqtt\Client\MQTTClient($server, $port, $clientId);
$mqtt->connect();
$mqtt->publish('php-mqtt/client/test', 'Hello World!', 0);
$mqtt->close();
```

If you do not want to pass a `$clientId`, a random one will be generated for you. This will basically force a clean session implicitly.

Be also aware that most of the methods can throw exceptions. The above example does not add any exception handling for brevity.

### Subscribe

Subscribing is a little more complex than publishing as it requires to run an event loop:

```php
$clientId = 'test-subscriber';

$mqtt = new \PhpMqtt\Client\MQTTClient($server, $port, $clientId);
$mqtt->connect();
$mqtt->subscribe('php-mqtt/client/test', function ($topic, $message) {
    echo sprintf("Received message on topic [%s]: %s\n", $topic, $message);
}, 0);
$mqtt->loop(true);
```

While the loop is active, you can use `$mqtt->interrupt()` to send an interrupt signal to the loop.
This will terminate the loop before it starts its next iteration. You can call this method using `pcntl_signal(SIGINT, $handler)` for example:

```php
pcntl_async_signals(true);

$clientId = 'test-subscriber';

$mqtt = new \PhpMqtt\Client\MQTTClient($server, $port, $clientId);
pcntl_signal(SIGINT, function (int $signal, $info) use ($mqtt) {
    $mqtt->interrupt();
});
$mqtt->connect();
$mqtt->subscribe('php-mqtt/client/test', function ($topic, $message) {
    echo sprintf("Received message on topic [%s]: %s\n", $topic, $message);
}, 0);
$mqtt->loop(true);
$mqtt->close();
```

### Client Settings

As shown in the examples above, the `MQTTClient` takes the server, port and client id as first, second and third parameter.
As fourth parameter, the path to a CA file can be passed which will enable TLS and is used to verify the peer.
A fifth parameter allows passing a repository (currently, only a `MemoryRepository` is available by default).
Lastly, a logger can be passed as sixth parameter. If none is given, a null logger is used instead.

Example:
```php
$mqtt = new \PhpMqtt\Client\MQTTClient(
    $server, 
    $port, 
    $clientId,
    '/path/to/ca/file',
    new \PhpMqtt\Client\Repositories\MemoryRepository(),
    new Logger()
);
```

The logger must implement the `Psr\Log\LoggerInterface`.

### Connection Settings

The `connect()` method of the `MQTTClient` takes four optional parameters:
1. Username
2. Password
3. A `ConnectionSettings` instance
4. A `boolean` flag indicating whether a clean session should be requested (a random client id does this implicitly)

Example:
```php
$mqtt = new \PhpMqtt\Client\MQTTClient($server, $port, $clientId);

$connectionSettings = new \PhpMqtt\Client\ConnectionSettings();
$mqtt->connect($username, $password, $connectionSettings, true);
```

The `ConnectionSettings` class has the following constructor and defaults:
```php
public function __construct(
    int $qualityOfService = 0,
    bool $retain = false,
    bool $blockSocket = false,
    int $socketTimeout = 5,
    int $keepAlive = 10,
    int $resendTimeout = 10,
    string $lastWillTopic = null,
    string $lastWillMessage = null,
    bool $useTls = false,
    bool $tlsVerifyPeer = true,
    bool $tlsVerifyName = true,
    string $tlsClientCertificateFile = null,
    string $tlsClientCertificateKeyFile = null,
    string $tlsClientCertificatePassphrase = null
) { ... }
```

## Features

- MQTT Versions
  - [x] v3 (just don't use v3.1 features like username & password)
  - [x] v3.1
  - [ ] v3.1.1
  - [ ] v5.0
- Transport
  - [x] TCP (unsecured)
  - [x] TLS (secured, verifies the peer using a certificate authority file)
- Connect
  - [x] Last Will
  - [x] Last Will Topic
  - [x] Last Will Message
  - [x] Required QoS
  - [x] Message Retention
  - [x] Authentication (username & password)
  - [ ] Clean Session (can be set and sent, but the client has no persistence for QoS 2 messages)
- Publish
  - [x] QoS Level 0
  - [x] QoS Level 1 (limitation: no persisted state across sessions)
  - [x] QoS Level 2 (limitation: no persisted state across sessions)
- Subscribe
  - [x] QoS Level 0
  - [x] QoS Level 1
  - [x] QoS Level 2 (limitation: no persisted state across sessions)
- Supported Message Length: unlimited _(no limits enforced, although the MQTT protocol supports only up to 256MB which one shouldn't use even remotely anyway)_
- Logging possible (`Psr\Log\LoggerInterface` can be passed to the client)
- Persistence Drivers
  - [x] In-Memory Driver
  - [ ] Redis Driver
  
## Limitations

- There is no guarantee that message identifiers are not used twice (while the first usage is still pending).
  The current implementation uses a simple counter which resets after all 65535 identifiers were used.
  This means that as long as the client isn't used to an extent where acknowledgements are open for a very long time, you should be fine.
  This also only affects QoS levels higher than 0, as QoS level 0 is a simple fire and forget mode.
- Message flows with a QoS level higher than 0 are not persisted as the default implementation uses an in-memory repository for data.
  To avoid issues with broken message flows, use the clean session flag to indicate that you don't care about old data.

## License

`php-mqtt/client` is open-sourced software licensed under the [MIT license](LICENSE.md).
