<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Packets;

use PhpMqtt\Client\Protocol\Properties;

/**
 * A typed MQTT control packet.
 *
 * @package PhpMqtt\Client\Protocol\Packets
 */
interface ControlPacket
{
    public function getType(): int;

    public function getProperties(): Properties;
}
