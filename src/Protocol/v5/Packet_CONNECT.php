<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol\v5;

use PhpMqtt\Protocol\Packet;
use PhpMqtt\Protocol\Message;
use PhpMqtt\Protocol\DataEncoder;
use PhpMqtt\Protocol\DataDecoder;

/**
 * MQTT v5.0 - Client request to connect to Server
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
     * Session expiration
     *
     * @var int
     */
    public $sessionExpiration = null;

    /**
     * Receive Maximum
     *
     * @var int
     */
    public $receiveMaximum = null;

    /**
     * Maximum packet size (1-??)
     *
     * Default protocol value: null (unlimited)
     *
     * @var ?int
     */
    public $maximumPacketSize = null;

    /**
     * Topic alias maximum (0-65535)
     *
     * Default protocol value: 0
     *
     * @var ?int
     */
    public $topicAliasMaximum = null;

    /**
     * ...
     *
     * @var ?bool
     */
    public $requestResponseInformation = null;

    /**
     * ...
     *
     * @var ?bool
     */
    public $requestProblemInformation = null;

    /**
     * User properties
     *
     * @var array<string, string>
     */
    public $userProperties = array();

    /**
     * @inheritdoc
     */
    protected function encodeInternal(): string
    {
        /* (header bytes 1-7) standard packet header and version */
        $header = "\x00\x04MQTT\x05";

        /* (header byte 8) 3.1.2.3 Connect Flags */
        $connflags = 0;

        if ($this->username !== null)
            $connflags |= 0x80;

        if ($this->password !== null)
            $connflags |= 0x40;

        if ($this->will)
            $connflags |= ($this->will->hasRetain() << 5) |
                      ($this->will->getQoS() << 3) |
                      0x04;

        if ($this->clean)
            $connflags |= 0x02;

        $header .= chr($connflags);


        /* (bytes 9-10) keep alive interval */
        $header .= DataEncoder::uint16($this->keepAlive);


        /* 3.1.2.11  CONNECT Properties */
        $header_props = "";

        /* 3.1.2.11.2  Session Expiry Interval */
        if ($this->sessionExpiration !== null)
            $header_props .= chr(0x11) . DataEncoder::uint32($this->sessionExpiration);

        /* 3.1.2.11.3  Receive Maximum */
        if ($this->receiveMaximum !== null)
            $header_props .= chr(0x21) . DataEncoder::uint16($this->receiveMaximum);

        /* 3.1.2.11.4  Maximum Packet Size */
        if ($this->maximumPacketSize !== null)
            $header_props .= chr(0x27) . DataEncoder::uint32($this->maximumPacketSize);

        /* 3.1.2.11.5  Topic Alias Maximum */
        if ($this->topicAliasMaximum !== null)
            $header_props .= chr(0x22) . DataEncoder::uint16($this->topicAliasMaximum);

        /* 3.1.2.11.6  Request Response Information */
        if ($this->requestResponseInformation !== null)
            $header_props .= chr(0x19) . ($this->requestResponseInformation ? chr(1) : chr(0));

        /* 3.1.2.11.7  Request Problem Information */
        if ($this->requestProblemInformation !== null)
            $header_props .= chr(0x17) . ($this->requestProblemInformation ? chr(1) : chr(0));

        /* 3.1.2.11.8  User Property */
        foreach ($this->userProperties as $name => $value) {
            $header_props .= chr(0x26) . DataEncoder::utf8pair($name, $value);
        }

        /* 3.1.2.11.9  Authentication Method */
        if ($this->authenticationMethod !== null) {
            $header_props .= chr(0x15) . DataEncoder::utf8string($this->authenticationMethod);
        }

        /* 3.1.2.11.10  Authentication Data */
        if ($this->authenticationData !== null) {
            $header_props .= chr(0x16) . DataEncoder::binary($this->authenticationData);
        }


        /* wrapping up header properties */
        $header .= DataEncoder::varint(strlen($header_props)) . $header_props;


        /* 3.1.3  CONNECT Payload */
        $payload = "";

        /* 3.1.3.1  Client Identifier (ClientID) */
        $payload .= DataEncoder::utf8string($this->clientId());

        // FIXME: will properties ??

        /* 3.1.3.5  User Name */
        if ($this->username !== null) {
            $payload .= DataEncoder::utf8string($this->username);
        }

        /* 3.1.3.6  Password */
        if ($this->password !== null) {
            $payload .= DataEncoder::utf8string($this->password);
        }

        return $header . $payload;
    }

    /**
     * @inheritdoc
     */
    protected function decodeInternal(string $data): void
    {
        /* FIXME to-be-implemented */
    }
}
