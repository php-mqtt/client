<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Packets;

use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\ReasonCode;

/**
 * MQTT 5 SUBACK or UNSUBACK packet.
 *
 * @package PhpMqtt\Client\Protocol\Packets
 */
final class ResultPacket extends AbstractControlPacket
{
    /** @var int[] */
    private array $reasonCodes;

    /**
     * @param int[] $reasonCodes
     */
    public function __construct(int $type, private int $packetIdentifier, array $reasonCodes, Properties $properties)
    {
        parent::__construct($type, $properties);

        if (!in_array($type, [PacketType::SUBACK, PacketType::UNSUBACK], true)
            || $packetIdentifier < 1
            || $packetIdentifier > 0xFFFF
            || count($reasonCodes) === 0) {
            throw new \InvalidArgumentException('Invalid acknowledgement result packet.');
        }

        foreach ($reasonCodes as $reasonCode) {
            ReasonCode::assertAllowed($type, $reasonCode);
        }

        $this->reasonCodes = array_values($reasonCodes);
    }

    public function getPacketIdentifier(): int
    {
        return $this->packetIdentifier;
    }

    /**
     * @return int[]
     */
    public function getReasonCodes(): array
    {
        return $this->reasonCodes;
    }
}
