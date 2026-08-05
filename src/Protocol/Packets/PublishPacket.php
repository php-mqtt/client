<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Packets;

use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\Qos;
use PhpMqtt\Client\Protocol\Topic;

/**
 * MQTT 5 PUBLISH packet.
 *
 * @package PhpMqtt\Client\Protocol\Packets
 */
final class PublishPacket extends AbstractControlPacket
{
    public function __construct(
        private string $topic,
        private string $payload,
        private int $qualityOfService,
        private bool $retain,
        private bool $duplicate,
        private ?int $packetIdentifier,
        Properties $properties
    )
    {
        parent::__construct(PacketType::PUBLISH, $properties);
        Qos::assertValid($qualityOfService);
        Topic::assertValidName($topic, true);

        if ($qualityOfService === Qos::AT_MOST_ONCE && ($packetIdentifier !== null || $duplicate)) {
            throw new \InvalidArgumentException('QoS 0 PUBLISH cannot carry a packet identifier or DUP flag.');
        }

        if ($qualityOfService > Qos::AT_MOST_ONCE && ($packetIdentifier === null || $packetIdentifier < 1 || $packetIdentifier > 0xFFFF)) {
            throw new \InvalidArgumentException('QoS 1 and 2 PUBLISH require a non-zero packet identifier.');
        }
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function getQualityOfService(): int
    {
        return $this->qualityOfService;
    }

    public function shouldRetain(): bool
    {
        return $this->retain;
    }

    public function isDuplicate(): bool
    {
        return $this->duplicate;
    }

    public function getPacketIdentifier(): ?int
    {
        return $this->packetIdentifier;
    }
}
