<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v3;

use PhpMqtt\Protocol\Packet;

/**
 * MQTT v5.0 - Client request to connect to Server
 */
class Packet_PUBLISH
        extends Packet
{
    /**
     * Message type enumeration
     */
    const TYPE = 1;

    /**
     * ...
     *
     * @var string
     */
    public $topic = "";

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
     * ...
     *
     * @var string
     */
    public $clientId = "";

    protected function encodeInternal(): string
    {
        /* (bytes 1-8) standard packet header and version */
        $header = "\x00\x06MQIsdp\x03";
        $pkt_payload = "";


        /* variable header */
        $header = "";


        $header .= DataEncoder::utf8string($this->topic);






        /* (byte 8) connect flags */
        $flags = 0;
        if ($this->username !== null)
            $flags |= 0x80;
        if ($this->password !== null)
            $flags |= 0x40;
        if ($this->will)
            $flags |= ($this->will->hasRetain() << 5) |
                      ($this->will->getQoS() << 3) |
                      0x04;
        if ($this->clean)
            $flags |= 0x02;
        $header .= chr($flags);

        /* (bytes 9-10) keep alive interval */
        $header .= DataEncoder::uint16($this->keepAlive);

        /* (payload) client id */
        // if ((strlen($this->clientId) < 1) ||
            // (strlen($this->clientId) > 32))
          // throw new ProtocolException("Client ID length must 1-32 bytes long");
        $pkt_payload .= DataEncoder::utf8string($this->clientId);

        /* (payload) username */
        if ($this->username !== null)
            $pkt_payload .= DataEncoder::utf8string($this->username);

        /* (payload) password */
        if ($this->password !== null)
            $pkt_payload .= DataEncoder::utf8string($this->password);

        /* (payload) will topic and message */
        if ($this->will !== null) {
            $pkt_payload .= DataEncoder::utf8string($this->will->getTopic());
            $pkt_payload .= DataEncoder::utf8string($this->will->getMessage());
        }

        foreach ($this->userProperties as $name => $value) {
            $pkt_props .= chr(0x26) . DataEncoder::utf8pair($name, $value);
        }

        /* 3.3.2.3.8  Subscription Identifier */
        if ($this->subscriptionId !== null) {
            assert($this->subscriptionId >= 1);
            assert($this->subscriptionId <= 268435455);
            $pkt_props .= chr(0x0b) . DataEncoder::varint($this->subscriptionId);
        }

        /* 3.3.2.3.9  Content Type */
        if ($this->contentType !== null) {
            // FIXME: is it legit to send empty string?
            $pkt_props .= chr(0x03) . DataEncoder::utf8string($this->contentType);
        }


        return $header . $payload
    }

}
