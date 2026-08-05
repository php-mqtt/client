<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\ReasonCode;

/**
 * Immutable MQTT 5 DISCONNECT options.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class DisconnectOptions
{
    private Properties $properties;

    public function __construct(private int $reasonCode = ReasonCode::NORMAL_DISCONNECTION, ?Properties $properties = null)
    {
        ReasonCode::assertAllowed(PacketType::DISCONNECT, $reasonCode);
        $this->properties = $properties ?? Properties::empty();
    }

    public function getReasonCode(): int
    {
        return $this->reasonCode;
    }

    public function getProperties(): Properties
    {
        return $this->properties;
    }
}
