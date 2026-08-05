# php-mqtt/client

[![Latest Stable Version](https://poser.pugx.org/php-mqtt/client/v)](https://packagist.org/packages/php-mqtt/client)
[![Total Downloads](https://poser.pugx.org/php-mqtt/client/downloads)](https://packagist.org/packages/php-mqtt/client)
[![Coverage](https://sonarcloud.io/api/project_badges/measure?project=php-mqtt_client&metric=coverage)](https://sonarcloud.io/dashboard?id=php-mqtt_client)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=php-mqtt_client&metric=alert_status)](https://sonarcloud.io/dashboard?id=php-mqtt_client)
[![Maintainability Rating](https://sonarcloud.io/api/project_badges/measure?project=php-mqtt_client&metric=sqale_rating)](https://sonarcloud.io/dashboard?id=php-mqtt_client)
[![Reliability Rating](https://sonarcloud.io/api/project_badges/measure?project=php-mqtt_client&metric=reliability_rating)](https://sonarcloud.io/dashboard?id=php-mqtt_client)
[![Security Rating](https://sonarcloud.io/api/project_badges/measure?project=php-mqtt_client&metric=security_rating)](https://sonarcloud.io/dashboard?id=php-mqtt_client)
[![Vulnerabilities](https://sonarcloud.io/api/project_badges/measure?project=php-mqtt_client&metric=vulnerabilities)](https://sonarcloud.io/dashboard?id=php-mqtt_client)
[![License](https://poser.pugx.org/php-mqtt/client/license)](https://packagist.org/packages/php-mqtt/client)

[`php-mqtt/client`](https://packagist.org/packages/php-mqtt/client) was created by, and is maintained
by [Marvin Mall](https://github.com/namoshek).
It allows you to connect to MQTT 3.1, MQTT 3.1.1, and MQTT 5.0 brokers to publish messages and subscribe to topics.
The current implementation supports all QoS levels ([with limitations](#limitations)).

## Installation

The package is available on [packagist.org](https://packagist.org/packages/php-mqtt/client) and can be installed using `composer`:

```bash
composer require php-mqtt/client
```

The package requires PHP version 8.0 or higher.

## Usage

In the following, only a few very basic examples are given. For more elaborate examples, have a look at the
[`php-mqtt/client-examples` repository](https://github.com/php-mqtt/client-examples).

### Publish

A very basic publish example using QoS 0 requires only three steps: connect, publish and disconnect

```php
$server   = 'some-broker.example.com';
$port     = 1883;
$clientId = 'test-publisher';

$mqtt = new \PhpMqtt\Client\MqttClient($server, $port, $clientId);
$mqtt->connect();
$mqtt->publish('php-mqtt/client/test', 'Hello World!', 0);
$mqtt->disconnect();
```

If you do not want to pass a `$clientId`, a random one will be generated for you. This will basically force a clean session implicitly.

Be also aware that most of the methods can throw exceptions. The above example does not add any exception handling for brevity.

Be aware that for publications with QoS levels 1 or 2 a later call to `loop` method is required in order for the additional acknowledgement messages from the broker to be correctly processed. Not doing so will avoid QoS 2 from being delivered at all and QoS 1 messages might or might not arrive depending on specific broker implementation details.

An example:

```php
$server   = 'some-broker.example.com';
$port     = 1883;
$clientId = 'test-publisher';

$mqtt = new \PhpMqtt\Client\MqttClient($server, $port, $clientId);
$mqtt->connect();
$mqtt->publish('php-mqtt/client/test', 'Hello Exactly Once World!', 2);
// You can set a third optional parameter as a timeout
$mqtt->loop(true, true);
$mqtt->disconnect();
```

### Subscribe

Subscribing is a little more complex than publishing as it requires to run an event loop which reads, parses and handles messages from the broker:

```php
$server   = 'some-broker.example.com';
$port     = 1883;
$clientId = 'test-subscriber';

$mqtt = new \PhpMqtt\Client\MqttClient($server, $port, $clientId);
$mqtt->connect();
$mqtt->subscribe('php-mqtt/client/test', function ($topic, $message, $retained, $matchedWildcards) {
    echo sprintf("Received message on topic [%s]: %s\n", $topic, $message);
}, 0);
$mqtt->loop(true);
$mqtt->disconnect();
```

While the loop is active, you can use `$mqtt->interrupt()` to send an interrupt signal to the loop.
This will terminate the loop before it starts its next iteration. You can call this method using `pcntl_signal(SIGINT, $handler)` for example:

```php
pcntl_async_signals(true);

$clientId = 'test-subscriber';

$mqtt = new \PhpMqtt\Client\MqttClient($server, $port, $clientId);
pcntl_signal(SIGINT, function (int $signal, $info) use ($mqtt) {
    $mqtt->interrupt();
});
$mqtt->connect();
$mqtt->subscribe('php-mqtt/client/test', function ($topic, $message, $retained, $matchedWildcards) {
    echo sprintf("Received message on topic [%s]: %s\n", $topic, $message);
}, 0);
$mqtt->loop(true);
$mqtt->disconnect();
```

### Client Settings

As shown in the examples above, the `MqttClient` takes the server, port and client id as first, second and third parameter.
As fourth parameter, the protocol level can be passed. Supported constants are `MqttClient::MQTT_3_1`,
`MqttClient::MQTT_3_1_1`, and `MqttClient::MQTT_5_0`. The default remains MQTT 3.1 for backward compatibility.
A fifth parameter allows passing a repository (currently, only a `MemoryRepository` is available by default).
Lastly, a logger can be passed as sixth parameter. If none is given, a null logger is used instead.

Example:

```php
$mqtt = new \PhpMqtt\Client\MqttClient(
    $server, 
    $port, 
    $clientId,
    \PhpMqtt\Client\MqttClient::MQTT_3_1,
    new \PhpMqtt\Client\Repositories\MemoryRepository(),
    new Logger()
);
```

The `Logger` must implement the `Psr\Log\LoggerInterface`.

### Connection Settings

The `connect()` method of the `MqttClient` takes two optional parameters:

1. A `ConnectionSettings` instance
2. A `boolean` flag indicating whether a clean session should be requested (a random client id does this implicitly)

Example:

```php
$mqtt = new \PhpMqtt\Client\MqttClient($server, $port, $clientId);

$connectionSettings = (new \PhpMqtt\Client\ConnectionSettings)
    ->setConnectTimeout(3)
    ->setUseTls(true)
    ->setTlsSelfSignedAllowed(true);
    
$mqtt->connect($connectionSettings, true);
```

The `ConnectionSettings` class provides a few settings through a fluent interface. The type itself is immutable,
and a new `ConnectionSettings` instance will be created for each added option.
This also prevents changes to the connection settings after a connection has been established.

The following is a complete list of options with their respective default:

```php
$connectionSettings = (new \PhpMqtt\Client\ConnectionSettings)

    // The username used for authentication when connecting to the broker.
    ->setUsername(null)
    
    // The password used for authentication when connecting to the broker.
    ->setPassword(null)
    
    // Whether to use a blocking socket when publishing messages or not.
    // Normally, this setting can be ignored. When publishing large messages with multiple kilobytes in size,
    // a blocking socket may be required if the receipt buffer of the broker is not large enough.
    //
    // Note: This setting has no effect on subscriptions, only on the publishing of messages.
    ->useBlockingSocket(false)
    
    // The connect timeout defines the maximum amount of seconds the client will try to establish
    // a socket connection with the broker. The value cannot be less than 1 second.
    ->setConnectTimeout(60)
    
    // The socket timeout is the maximum amount of idle time in seconds for the socket connection.
    // If no data is read or sent for the given amount of seconds, the socket will be closed.
    // The value cannot be less than 1 second.
    ->setSocketTimeout(5)
    
    // The resend timeout is the number of seconds the client will wait before sending a duplicate
    // of pending messages without acknowledgement. The value cannot be less than 1 second.
    ->setResendTimeout(10)
    
    // This flag determines whether the client will try to reconnect automatically
    // if it notices a disconnect while sending data.
    // The setting cannot be used together with the clean session flag.
    ->setReconnectAutomatically(false)
    
    // Defines the maximum number of reconnect attempts until the client gives up.
    // This setting is only relevant if setReconnectAutomatically() is set to true.
    ->setMaxReconnectAttempts(3)
    
    // Defines the delay between reconnect attempts in milliseconds.
    // This setting is only relevant if setReconnectAutomatically() is set to true.
    ->setDelayBetweenReconnectAttempts(0)
    
    // The keep alive interval is the number of seconds the client will wait without sending a message
    // until it sends a keep alive signal (ping) to the broker. The value cannot be less than 1 second
    // and may not be higher than 65535 seconds. A reasonable value is 10 seconds (the default).
    ->setKeepAliveInterval(10)
    
    // If the broker should publish a last will message in the name of the client when the client
    // disconnects abruptly, this setting defines the topic on which the message will be published.
    //
    // A last will message will only be published if both this setting as well as the last will
    // message are configured.
    ->setLastWillTopic(null)
    
    // If the broker should publish a last will message in the name of the client when the client
    // disconnects abruptly, this setting defines the message which will be published.
    //
    // A last will message will only be published if both this setting as well as the last will
    // topic are configured.
    ->setLastWillMessage(null)
    
    // The quality of service level the last will message of the client will be published with,
    // if it gets triggered.
    ->setLastWillQualityOfService(0)
    
    // This flag determines if the last will message of the client will be retained, if it gets
    // triggered. Using this setting can be handy to signal that a client is offline by publishing
    // a retained offline state in the last will and an online state as first message on connect.
    ->setRetainLastWill(false)
    
    // This flag determines if TLS should be used for the connection. The port which is used to
    // connect to the broker must support TLS connections.
    ->setUseTls(false)
    
    // This flag determines if the peer certificate is verified, if TLS is used.
    ->setTlsVerifyPeer(true)
    
    // This flag determines if the peer name is verified, if TLS is used.
    ->setTlsVerifyPeerName(true)
    
    // This flag determines if self signed certificates of the peer should be accepted.
    // Setting this to TRUE implies a security risk and should be avoided for production
    // scenarios and public services.
    ->setTlsSelfSignedAllowed(false)
    
    // The path to a Certificate Authority certificate which is used to verify the peer
    // certificate, if TLS is used.
    ->setTlsCertificateAuthorityFile(null)
    
    // The path to a directory containing Certificate Authority certificates which are
    // used to verify the peer certificate, if TLS is used.
    ->setTlsCertificateAuthorityPath(null)
    
    // The path to a client certificate file used for authentication, if TLS is used.
    //
    // The client certificate must be PEM encoded. It may optionally contain the
    // certificate chain of issuers.
    ->setTlsClientCertificateFile(null)
    
    // The path to a client certificate key file used for authentication, if TLS is used.
    //
    // This option requires ConnectionSettings::setTlsClientCertificateFile() to be used as well.
    ->setTlsClientCertificateKeyFile(null)
    
    // The passphrase used to decrypt the private key of the client certificate,
    // which in return is used for authentication, if TLS is used.
    //
    // This option requires ConnectionSettings::setTlsClientCertificateFile() and
    // ConnectionSettings::setTlsClientCertificateKeyFile() to be used as well.
    ->setTlsClientCertificateKeyPassphrase(null);

     // The TLS ALPN is used to establish a TLS encrypted mqtt connection on port 443,
     // which usually is reserved for TLS encrypted HTTP traffic.
     ->setTlsAlpn(null);
```

### Hooks

The client includes a flexible and powerful hook system to allow custom behaviors during different stages of the MQTT lifecycle. Hooks are registered using closures and can be added or removed dynamically at runtime.

> 💡 All hooks receive the MQTT client instance (`MqttClient`) as their first argument, allowing full access to the client's capabilities from within the hook.

> 💡 Each hook is executed in a `try-catch` block to ensure no individual exception can crash the loop or hook processing.

#### Loop Event Hooks

Called on each iteration of the MQTT client's loop. This hook is especially useful to implement timeouts or other deadlock-prevention logic.

##### Register

```php
$callback = function (MqttClient $mqtt, float $elapsedTime) {
    echo "Running for {$elapsedTime} seconds already.";
};

$mqtt->registerLoopEventHandler($callback);
```

##### Unregister

```php
$mqtt->unregisterLoopEventHandler($callback); // Unregister specific event handler
$mqtt->unregisterLoopEventHandler(); // Unregister all event handlers
```

#### Publish Event Hooks

Triggered every time a message is published to the broker. This hook is useful to implement centralized logging or metrics.

##### Register

```php
$callback = function (
    MqttClient $mqtt,
    string $topic,
    string $message,
    ?int $messageId,
    int $qualityOfService,
    bool $retain
) {
    echo "Published to [{$topic}]: {$message}";
};

$mqtt->registerPublishEventHandler($callback);
```

##### Unregister

```php
$mqtt->unregisterPublishEventHandler($callback); // Unregister specific event handler
$mqtt->unregisterPublishEventHandler(); // Unregister all event handlers
```

#### Message Received Hooks

Executed when a message is received from the broker as part of a subscription. This hook is useful to implement centralized logging or metrics.

##### Register

```php
$callback = function (
    MqttClient $mqtt,
    string $topic,
    string $message,
    int $qualityOfService,
    bool $retained
) {
    echo "Message on [{$topic}]: {$message}";
};

$mqtt->registerMessageReceivedEventHandler($callback);
```

##### Unregister

```php
$mqtt->unregisterMessageReceivedEventHandler($callback); // Unregister specific event handler
$mqtt->unregisterMessageReceivedEventHandler(); // Unregister all event handlers
```

#### Connected Hooks

Invoked when the client connects to the broker (initial or auto-reconnect).

##### Register

```php
$callback = function (MqttClient $mqtt, bool $isAutoReconnect) {
    echo $isAutoReconnect ? "Auto-reconnected!" : "Connected!";
};

$mqtt->registerConnectedEventHandler($callback);
```

##### Unregister

```php
$mqtt->unregisterConnectedEventHandler($callback); // Unregister specific event handler
$mqtt->unregisterConnectedEventHandler(); // Unregister all event handlers
```

### MQTT 5

Select MQTT 5 explicitly; the constructor and basic API remain unchanged:

```php
use PhpMqtt\Client\MqttClient;

$mqtt = new MqttClient($server, $port, $clientId, MqttClient::MQTT_5_0);
$mqtt->connect();
$mqtt->publish('example/topic', 'payload');
$mqtt->disconnect();
```

The optional `Contracts\Mqtt5Client` API exposes typed options, results, properties, and callbacks without
changing `Contracts\MqttClient`. Properties are ordered and preserve duplicate User Properties and
Subscription Identifiers:

```php
use PhpMqtt\Client\Mqtt5\ConnectionOptions;
use PhpMqtt\Client\Mqtt5\PublishOptions;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\PropertyIdentifier;

$connectionProperties = Properties::empty()
    ->with(PropertyIdentifier::SESSION_EXPIRY_INTERVAL, 3600)
    ->with(PropertyIdentifier::RECEIVE_MAXIMUM, 20)
    ->with(PropertyIdentifier::MAXIMUM_PACKET_SIZE, 1048576)
    ->with(PropertyIdentifier::TOPIC_ALIAS_MAXIMUM, 10);

$result = $mqtt->connectWithOptions(null, true, new ConnectionOptions($connectionProperties));

$publishProperties = Properties::empty()
    ->with(PropertyIdentifier::CONTENT_TYPE, 'application/json')
    ->with(PropertyIdentifier::MESSAGE_EXPIRY_INTERVAL, 60)
    ->with(PropertyIdentifier::USER_PROPERTY, ['trace-id', 'example']);

$mqtt->publishWithOptions(
    'example/topic',
    '{"status":"ok"}',
    1,
    false,
    new PublishOptions($publishProperties)
);
```

`ConnectionResult` exposes Session Present, an assigned client identifier, CONNACK properties, Server Keep
Alive, and negotiated feature limits. Batched subscriptions use `SubscribeOptions` and per-filter
`SubscriptionOptions`. Typed callbacks are available for incoming publications, operation results, server
DISCONNECT, and AUTH while legacy callback signatures remain unchanged.

For MQTT 5, the existing `$useCleanSession` argument means **Clean Start**. Session lifetime is controlled
separately by the Session Expiry Interval property. Automatic reconnect uses CONNACK Session Present to resume
in-flight state or replay known subscriptions when the broker has lost the session.

Enhanced authentication is configured with `AuthenticationOptions` and an implementation of
`Contracts\AuthenticationHandler`. Redirect reasons and Server Reference are exposed to the caller; this
client does not automatically connect to another host.

## Features

- Supported MQTT Versions
  - [x] v3 (just don't use v3.1 features like username & password)
  - [x] v3.1
  - [x] v3.1.1
  - [x] v5.0
- Transport
  - [x] TCP (unsecured)
  - [x] TLS (secured, verifies the peer using a certificate authority file)
  - [ ] WebSocket (not advertised or implemented)
- Connect
  - [x] Last Will
  - [x] Message Retention
  - [x] Authentication (username & password)
  - [x] TLS encrypted connections
  - [x] Clean Session / MQTT 5 Clean Start
  - [x] MQTT 5 enhanced authentication and negotiated capabilities
- Publish
  - [x] QoS Level 0
  - [x] QoS Level 1 (limitation: no persisted state across sessions)
  - [x] QoS Level 2 (limitation: no persisted state across sessions)
- Subscribe
  - [x] QoS Level 0
  - [x] QoS Level 1
  - [x] QoS Level 2 (limitation: no persisted state across sessions)
- Packet size: MQTT protocol maximum plus lower negotiated MQTT 5 inbound/outbound limits
- Logging possible (`Psr\Log\LoggerInterface` can be passed to the client)
- Persistence Drivers
  - [x] In-Memory Driver
  - [ ] Redis Driver
  
## Limitations
  
- Message flows with a QoS level higher than 0 are not persisted as the default implementation uses an in-memory repository for data.
  To avoid issues with broken message flows, use the clean session flag to indicate that you don't care about old data.
  It will not only instruct the broker to consider the connection new (without previous state), but will also reset the registered repository.
- `MemoryRepository` state is lost when the PHP process exits. A legacy custom repository is wrapped by
  `LegacyRepositoryAdapter`; MQTT 5 queue metadata and the adapter's subscription index are process-local.
- TCP and TLS are supported. WebSocket transport is conditional work and is not currently advertised.
- Wire diagnostics do not log CONNECT, AUTH, credentials, or payload bytes. Applications should apply the
  same redaction policy to custom loggers and callbacks.
  
## Developing & Testing

### Certificates (TLS)

To run the tests (especially the TLS tests), you will need to create certificates. A command has been provided for this:

```sh
sh create-certificates.sh
```

This will create all required certificates in the `.ci/tls/` directory. The same script is used for continuous integration as well.

### MQTT Broker for Testing

Running the tests expects an MQTT broker to be running. The easiest way to run an MQTT broker is through Docker:

```sh
docker run --rm -it \
  -p 1883:1883 \
  -p 1884:1884 \
  -p 8883:8883 \
  -p 8884:8884 \
  -v $(pwd)/.ci/tls:/mosquitto-certs \
  -v $(pwd)/.ci/mosquitto.conf:/mosquitto/config/mosquitto.conf \
  -v $(pwd)/.ci/mosquitto.passwd:/mosquitto/config/mosquitto.passwd \
  eclipse-mosquitto:2.1.2-alpine
```

When run from the project directory, this will spawn a Mosquitto MQTT broker configured with the generated TLS certificates and a custom configuration.

In case you intend to run a different broker or using a different method, or use a public broker instead,
you will need to adjust the environment variables defined in `phpunit.xml` accordingly.

Run broker-free checks with `composer test:cs` and `composer test:unit`. Run the broker-backed suite with
`composer test:feature`. MQTT 5 release conformance is tracked in
[`docs/mqtt5-compliance.md`](docs/mqtt5-compliance.md).

## License

`php-mqtt/client` is open-sourced software licensed under the [MIT license](LICENSE.md).
