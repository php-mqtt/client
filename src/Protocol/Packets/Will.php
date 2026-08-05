<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Packets;

use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\Qos;
use PhpMqtt\Client\Protocol\Topic;

/**
 * MQTT CONNECT Will data.
 *
 * @package PhpMqtt\Client\Protocol\Packets
 */
final class Will
{
    public function __construct(
        private string $topic,
        private string $payload,
        private int $qualityOfService,
        private bool $retain,
        private Properties $properties
    )
    {
        Topic::assertValidName($topic);
        Qos::assertValid($qualityOfService);
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

    public function getProperties(): Properties
    {
        return $this->properties;
    }
}
