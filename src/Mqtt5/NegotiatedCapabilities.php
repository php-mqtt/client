<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\PropertyIdentifier;
use PhpMqtt\Client\Protocol\Wire\PacketFramer;

/**
 * Server limits and feature flags negotiated by CONNACK.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class NegotiatedCapabilities
{
    public function __construct(
        private int $receiveMaximum = 65535,
        private int $maximumQualityOfService = 2,
        private bool $retainAvailable = true,
        private int $maximumPacketSize = PacketFramer::MAXIMUM_PACKET_SIZE,
        private int $topicAliasMaximum = 0,
        private bool $wildcardSubscriptionsAvailable = true,
        private bool $subscriptionIdentifiersAvailable = true,
        private bool $sharedSubscriptionsAvailable = true,
        private ?int $serverKeepAlive = null
    )
    {
    }

    public static function fromProperties(Properties $properties): self
    {
        return new self(
            $properties->get(PropertyIdentifier::RECEIVE_MAXIMUM) ?? 65535,
            $properties->get(PropertyIdentifier::MAXIMUM_QOS) ?? 2,
            ($properties->get(PropertyIdentifier::RETAIN_AVAILABLE) ?? 1) === 1,
            $properties->get(PropertyIdentifier::MAXIMUM_PACKET_SIZE) ?? PacketFramer::MAXIMUM_PACKET_SIZE,
            $properties->get(PropertyIdentifier::TOPIC_ALIAS_MAXIMUM) ?? 0,
            ($properties->get(PropertyIdentifier::WILDCARD_SUBSCRIPTION_AVAILABLE) ?? 1) === 1,
            ($properties->get(PropertyIdentifier::SUBSCRIPTION_IDENTIFIER_AVAILABLE) ?? 1) === 1,
            ($properties->get(PropertyIdentifier::SHARED_SUBSCRIPTION_AVAILABLE) ?? 1) === 1,
            $properties->get(PropertyIdentifier::SERVER_KEEP_ALIVE)
        );
    }

    public function getReceiveMaximum(): int
    {
        return $this->receiveMaximum;
    }

    public function getMaximumQualityOfService(): int
    {
        return $this->maximumQualityOfService;
    }

    public function isRetainAvailable(): bool
    {
        return $this->retainAvailable;
    }

    public function getMaximumPacketSize(): int
    {
        return $this->maximumPacketSize;
    }

    public function getTopicAliasMaximum(): int
    {
        return $this->topicAliasMaximum;
    }

    public function areWildcardSubscriptionsAvailable(): bool
    {
        return $this->wildcardSubscriptionsAvailable;
    }

    public function areSubscriptionIdentifiersAvailable(): bool
    {
        return $this->subscriptionIdentifiersAvailable;
    }

    public function areSharedSubscriptionsAvailable(): bool
    {
        return $this->sharedSubscriptionsAvailable;
    }

    public function getServerKeepAlive(): ?int
    {
        return $this->serverKeepAlive;
    }
}
