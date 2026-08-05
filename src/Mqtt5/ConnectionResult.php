<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\PropertyIdentifier;

/**
 * Result of an MQTT 5 connection handshake.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class ConnectionResult
{
    private NegotiatedCapabilities $capabilities;

    public function __construct(
        private bool $sessionPresent,
        private int $reasonCode,
        private Properties $serverProperties
    )
    {
        $this->capabilities = NegotiatedCapabilities::fromProperties($serverProperties);
    }

    public function isSessionPresent(): bool
    {
        return $this->sessionPresent;
    }

    public function getReasonCode(): int
    {
        return $this->reasonCode;
    }

    public function getAssignedClientId(): ?string
    {
        return $this->serverProperties->get(PropertyIdentifier::ASSIGNED_CLIENT_IDENTIFIER);
    }

    public function getServerProperties(): Properties
    {
        return $this->serverProperties;
    }

    public function getCapabilities(): NegotiatedCapabilities
    {
        return $this->capabilities;
    }
}
