<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Contracts;

use PhpMqtt\Client\Mqtt5\AuthenticationOptions;
use PhpMqtt\Client\Mqtt5\ConnectionOptions;
use PhpMqtt\Client\Mqtt5\ConnectionResult;
use PhpMqtt\Client\Mqtt5\DisconnectOptions;
use PhpMqtt\Client\Mqtt5\PublishOptions;
use PhpMqtt\Client\Mqtt5\SubscribeOptions;
use PhpMqtt\Client\Mqtt5\UnsubscribeOptions;
use PhpMqtt\Client\Protocol\Packets\ControlPacket;

/**
 * MQTT 5 additions used by the client while retaining MessageProcessor compatibility.
 *
 * @package PhpMqtt\Client\Contracts
 */
interface Mqtt5MessageProcessor extends MessageProcessor
{
    public function setClientId(string $clientId): void;

    public function setConnectionOptions(?ConnectionOptions $options): void;

    public function processConnectionHandshake(string $message): ?string;

    public function getConnectionResult(): ?ConnectionResult;

    public function getLastPacket(): ?ControlPacket;

    public function buildPublishMessageWithOptions(
        string $topic,
        string $message,
        int $qualityOfService,
        bool $retain,
        ?int $messageId,
        PublishOptions $options,
        bool $isDuplicate = false
    ): string;

    public function buildSubscribeMessageWithOptions(int $messageId, SubscribeOptions $options): string;

    public function buildUnsubscribeMessageWithOptions(int $messageId, UnsubscribeOptions $options): string;

    public function buildDisconnectMessageWithOptions(DisconnectOptions $options): string;

    public function buildAuthenticationMessage(AuthenticationOptions $options, int $reasonCode): string;

    public function respondToAuthentication(): ?string;
}
