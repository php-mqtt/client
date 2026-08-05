<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Contracts;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Mqtt5\AuthenticationOptions;
use PhpMqtt\Client\Mqtt5\ConnectionOptions;
use PhpMqtt\Client\Mqtt5\ConnectionResult;
use PhpMqtt\Client\Mqtt5\DisconnectOptions;
use PhpMqtt\Client\Mqtt5\PublishOptions;
use PhpMqtt\Client\Mqtt5\SubscribeOptions;
use PhpMqtt\Client\Mqtt5\UnsubscribeOptions;

/**
 * Optional advanced MQTT 5 client capabilities.
 *
 * The original MqttClient contract deliberately remains unchanged.
 *
 * @package PhpMqtt\Client\Contracts
 */
interface Mqtt5Client
{
    public function connectWithOptions(
        ?ConnectionSettings $settings = null,
        bool $cleanStart = false,
        ?ConnectionOptions $options = null
    ): ConnectionResult;

    public function publishWithOptions(
        string $topic,
        string $message,
        int $qualityOfService = 0,
        bool $retain = false,
        ?PublishOptions $options = null
    ): ?int;

    public function subscribeWithOptions(SubscribeOptions $options): int;

    public function unsubscribeWithOptions(UnsubscribeOptions $options): int;

    public function disconnectWithOptions(?DisconnectOptions $options = null): void;

    public function authenticate(AuthenticationOptions $options): void;

    public function getConnectionResult(): ?ConnectionResult;

    public function registerIncomingPublicationEventHandler(\Closure $callback): Mqtt5Client;

    public function unregisterIncomingPublicationEventHandler(?\Closure $callback = null): Mqtt5Client;

    public function registerOperationResultEventHandler(\Closure $callback): Mqtt5Client;

    public function unregisterOperationResultEventHandler(?\Closure $callback = null): Mqtt5Client;

    public function registerServerDisconnectEventHandler(\Closure $callback): Mqtt5Client;

    public function unregisterServerDisconnectEventHandler(?\Closure $callback = null): Mqtt5Client;

    public function registerAuthenticationEventHandler(\Closure $callback): Mqtt5Client;

    public function unregisterAuthenticationEventHandler(?\Closure $callback = null): Mqtt5Client;
}
