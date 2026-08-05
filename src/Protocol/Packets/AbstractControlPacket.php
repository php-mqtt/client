<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Packets;

use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Properties;

/**
 * Common immutable MQTT control packet state.
 *
 * @package PhpMqtt\Client\Protocol\Packets
 */
abstract class AbstractControlPacket implements ControlPacket
{
    public function __construct(private int $type, private Properties $properties)
    {
        if (!PacketType::isValid($type)) {
            throw new \InvalidArgumentException(sprintf('Invalid MQTT packet type [%d].', $type));
        }
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getProperties(): Properties
    {
        return $this->properties;
    }
}
