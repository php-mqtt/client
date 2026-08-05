<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

use PhpMqtt\Client\Protocol\Packets\PublishPacket;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\PropertyIdentifier;

/**
 * Typed MQTT 5 incoming publication.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class IncomingPublication
{
    public function __construct(private PublishPacket $packet)
    {
    }

    public function getTopic(): string
    {
        return $this->packet->getTopic();
    }

    public function getPayload(): string
    {
        return $this->packet->getPayload();
    }

    public function getQualityOfService(): int
    {
        return $this->packet->getQualityOfService();
    }

    public function isRetained(): bool
    {
        return $this->packet->shouldRetain();
    }

    public function isDuplicate(): bool
    {
        return $this->packet->isDuplicate();
    }

    public function getPacketIdentifier(): ?int
    {
        return $this->packet->getPacketIdentifier();
    }

    public function getProperties(): Properties
    {
        return $this->packet->getProperties();
    }

    /**
     * @return int[]
     */
    public function getSubscriptionIdentifiers(): array
    {
        return $this->packet->getProperties()->getAll(PropertyIdentifier::SUBSCRIPTION_IDENTIFIER);
    }
}
