<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v3;

use PhpMqtt\Protocol\Packet;

/**
 * MQTT v3.1 - Connect Acknowledgment
 */
class Packet_CONNACK
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 2;

    public $returnCode = null;


    public function encode(): string
    {
      $hdr = chr($this->returnCode);

    }

    public function decodeInternal(string $data): self
    {
      $pkt = new self();

      $pkt->returnCode = ord($data[1]);
    }


}
