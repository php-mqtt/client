<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Packets;

use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Properties;

/**
 * MQTT 5 SUBSCRIBE packet.
 *
 * @package PhpMqtt\Client\Protocol\Packets
 */
final class SubscribePacket extends AbstractControlPacket
{
    /** @var SubscriptionRequest[] */
    private array $subscriptions;

    /**
     * @param SubscriptionRequest[] $subscriptions
     */
    public function __construct(private int $packetIdentifier, array $subscriptions, Properties $properties)
    {
        parent::__construct(PacketType::SUBSCRIBE, $properties);

        if ($packetIdentifier < 1 || $packetIdentifier > 0xFFFF || count($subscriptions) === 0) {
            throw new \InvalidArgumentException('SUBSCRIBE requires an identifier and at least one topic filter.');
        }

        foreach ($subscriptions as $subscription) {
            if (!$subscription instanceof SubscriptionRequest) {
                throw new \InvalidArgumentException('Invalid SUBSCRIBE topic-filter entry.');
            }
        }

        $this->subscriptions = array_values($subscriptions);
    }

    public function getPacketIdentifier(): int
    {
        return $this->packetIdentifier;
    }

    /**
     * @return SubscriptionRequest[]
     */
    public function getSubscriptions(): array
    {
        return $this->subscriptions;
    }
}
