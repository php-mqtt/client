<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Packets;

use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Properties;

/**
 * MQTT PINGREQ or PINGRESP packet.
 *
 * @package PhpMqtt\Client\Protocol\Packets
 */
final class EmptyPacket extends AbstractControlPacket
{
    public function __construct(int $type)
    {
        if (!in_array($type, [PacketType::PINGREQ, PacketType::PINGRESP], true)) {
            throw new \InvalidArgumentException('Invalid empty MQTT packet type.');
        }

        parent::__construct($type, Properties::empty());
    }
}
