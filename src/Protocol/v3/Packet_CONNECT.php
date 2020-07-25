<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v3;

use PhpMqtt\Protocol\Packet;
use PhpMqtt\Protocol\Message;
use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;

/**
 * MQTT v3.1 - Client request to connect to Server
 */
class Packet_CONNECT
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 1;

    /**
     * Client username
     *
     * @var ?string = null;
     */
    public $username = null;

    /**
     * Client password
     *
     * @var ?string
     */
    public $password = null;

    /**
     * Clean session flag
     *
     * @var bool
     */
    public $cleanSession = false;

    /**
     * Last will message
     *
     * @var ?Message
     */
    public $will = null;

    /**
     * Keep alive interval
     *
     * @var ?int
     */
    public $keepAlive = null;

    /**
     * Client ID
     *
     * @var string
     */
    public $clientId = "";

    /**
     * @inheritdoc
     */
    protected function encodeInternal(): string
    {
        /* (header bytes 1-9) standard packet header and version */
        $header = "\x00\x06MQIsdp\x03";

        /* (header byte 10) connect flags */
        $connflags = 0;

        if ($this->username !== null)
            $connflags |= 0x80;

        if ($this->password !== null)
            $connflags |= 0x40;

        if ($this->will)
            $connflags |= ($this->will->hasRetain() << 5) |
                      ($this->will->getQoS() << 3) |
                      0x04;

        if ($this->cleanSession)
            $connflags |= 0x02;

        $header .= chr($connflags);

        /* (header bytes 11-12) keep alive interval */
        $header .= DataEncoder::uint16($this->keepAlive);


        /* (payload) initialization */
        $payload = "";

        /* (payload) client id */
        // if ((strlen($this->clientId) < 1) ||
            // (strlen($this->clientId) > 32))
          // throw new ProtocolException("Client ID length must 1-32 bytes long");
        $payload .= DataEncoder::utf8string($this->clientId);

        /* (payload) username */
        if ($this->username !== null) {
            $payload .= DataEncoder::utf8string($this->username);
        }

        /* (payload) password */
        if ($this->password !== null) {
            $payload .= DataEncoder::utf8string($this->password);
        }

        /* (payload) will topic and message */
        if ($this->will !== null) {
            $payload .= DataEncoder::utf8string($this->will->getTopic());
            $payload .= DataEncoder::utf8string($this->will->getMessage());
        }

        return $header . $payload;
    }

    /**
     * @inheritdoc
     */
    protected function decodeInternal(string $data): void
    {
        /* FIXME: to-be-implemented */
    }
}
