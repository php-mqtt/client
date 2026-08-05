<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Packets;

use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\Topic;

/**
 * MQTT 5 UNSUBSCRIBE packet.
 *
 * @package PhpMqtt\Client\Protocol\Packets
 */
final class UnsubscribePacket extends AbstractControlPacket
{
    /** @var string[] */
    private array $topicFilters;

    /**
     * @param string[] $topicFilters
     */
    public function __construct(private int $packetIdentifier, array $topicFilters, Properties $properties)
    {
        parent::__construct(PacketType::UNSUBSCRIBE, $properties);

        if ($packetIdentifier < 1 || $packetIdentifier > 0xFFFF || count($topicFilters) === 0) {
            throw new \InvalidArgumentException('UNSUBSCRIBE requires an identifier and at least one topic filter.');
        }

        foreach ($topicFilters as $topicFilter) {
            Topic::assertValidFilter($topicFilter);
        }

        $this->topicFilters = array_values($topicFilters);
    }

    public function getPacketIdentifier(): int
    {
        return $this->packetIdentifier;
    }

    /**
     * @return string[]
     */
    public function getTopicFilters(): array
    {
        return $this->topicFilters;
    }
}
