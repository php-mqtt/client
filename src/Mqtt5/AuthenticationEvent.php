<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\PropertyIdentifier;

/**
 * Typed MQTT 5 AUTH challenge or reauthentication event.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class AuthenticationEvent
{
    public function __construct(private int $reasonCode, private Properties $properties)
    {
    }

    public function getReasonCode(): int
    {
        return $this->reasonCode;
    }

    public function getMethod(): ?string
    {
        return $this->properties->get(PropertyIdentifier::AUTHENTICATION_METHOD);
    }

    public function getData(): ?string
    {
        return $this->properties->get(PropertyIdentifier::AUTHENTICATION_DATA);
    }

    public function getProperties(): Properties
    {
        return $this->properties;
    }
}
