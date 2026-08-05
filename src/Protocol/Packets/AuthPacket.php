<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Packets;

use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\ReasonCode;

/**
 * MQTT 5 AUTH packet.
 *
 * @package PhpMqtt\Client\Protocol\Packets
 */
final class AuthPacket extends AbstractControlPacket
{
    public function __construct(private int $reasonCode, Properties $properties)
    {
        parent::__construct(PacketType::AUTH, $properties);
        ReasonCode::assertAllowed(PacketType::AUTH, $reasonCode);
    }

    public function getReasonCode(): int
    {
        return $this->reasonCode;
    }
}
