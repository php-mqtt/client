<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Packets;

use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\ReasonCode;

/**
 * MQTT 5 DISCONNECT packet.
 *
 * @package PhpMqtt\Client\Protocol\Packets
 */
final class DisconnectPacket extends AbstractControlPacket
{
    public function __construct(private int $reasonCode, Properties $properties)
    {
        parent::__construct(PacketType::DISCONNECT, $properties);
        ReasonCode::assertAllowed(PacketType::DISCONNECT, $reasonCode);
    }

    public function getReasonCode(): int
    {
        return $this->reasonCode;
    }
}
