<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\Protocol as ProtocolAbstract;
use PhpMqtt\Protocol\Packet;

class Protocol
        extends ProtocolAbstract
{
    public function packet(string $type): Packet
    {
        switch ($type) {
        case 'CONNECT':
            return new Packet_CONNECT();

        case 'CONNACK':
            return new Packet_CONNACK();

        case 'PUBLISH':
            return new Packet_PUBLISH();
        }
    }
}
