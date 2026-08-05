<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use PhpMqtt\Client\Protocol\Packets\ControlPacket;

/**
 * Compatibility message carrying its typed MQTT 5 packet.
 *
 * @internal
 * @package PhpMqtt\Client
 */
class Mqtt5Message extends Message
{
    public function __construct(
        MessageType $type,
        private ControlPacket $packet,
        int $qualityOfService = 0,
        bool $retained = false
    )
    {
        parent::__construct($type, $qualityOfService, $retained);
    }

    public function getPacket(): ControlPacket
    {
        return $this->packet;
    }
}
