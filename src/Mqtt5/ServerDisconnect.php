<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\PropertyIdentifier;

/**
 * Typed server-initiated MQTT 5 DISCONNECT event.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class ServerDisconnect
{
    public function __construct(private int $reasonCode, private Properties $properties)
    {
    }

    public function getReasonCode(): int
    {
        return $this->reasonCode;
    }

    public function getProperties(): Properties
    {
        return $this->properties;
    }

    public function getServerReference(): ?string
    {
        return $this->properties->get(PropertyIdentifier::SERVER_REFERENCE);
    }
}
