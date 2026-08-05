<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Packets;

use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\ReasonCode;

/**
 * MQTT 5 PUBACK, PUBREC, PUBREL, or PUBCOMP packet.
 *
 * @package PhpMqtt\Client\Protocol\Packets
 */
final class AcknowledgementPacket extends AbstractControlPacket
{
    private const TYPES = [
        PacketType::PUBACK,
        PacketType::PUBREC,
        PacketType::PUBREL,
        PacketType::PUBCOMP,
    ];

    public function __construct(int $type, private int $packetIdentifier, private int $reasonCode, Properties $properties)
    {
        parent::__construct($type, $properties);

        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Invalid acknowledgement packet type.');
        }

        self::assertPacketIdentifier($packetIdentifier);
        ReasonCode::assertAllowed($type, $reasonCode);
    }

    public function getPacketIdentifier(): int
    {
        return $this->packetIdentifier;
    }

    public function getReasonCode(): int
    {
        return $this->reasonCode;
    }

    private static function assertPacketIdentifier(int $packetIdentifier): void
    {
        if ($packetIdentifier < 1 || $packetIdentifier > 0xFFFF) {
            throw new \InvalidArgumentException('The packet identifier must be between 1 and 65535.');
        }
    }
}
