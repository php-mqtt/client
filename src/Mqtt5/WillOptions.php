<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

use PhpMqtt\Client\Protocol\Packets\Will;
use PhpMqtt\Client\Protocol\Properties;

/**
 * Immutable MQTT 5 Will options.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class WillOptions
{
    private Properties $properties;

    public function __construct(
        private string $topic,
        private string $payload,
        private int $qualityOfService = 0,
        private bool $retain = false,
        ?Properties $properties = null
    )
    {
        $this->properties = $properties ?? Properties::empty();

        new Will($topic, $payload, $qualityOfService, $retain, $this->properties);
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
