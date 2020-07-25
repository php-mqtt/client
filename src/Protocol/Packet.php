<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol;

/**
 * MQTT packet representation
 *
 * A packet instance represents a packet as it appears "on the wire", thus it
 * depends on actual version of the MQTT protocol in use.
 *
 * As it maps the packet 1:1, the encoding/decoding procedures can convert the
 * packet from binary form to logical representation and vice-versa without
 * any loss of information.
 *
 */
abstract class Packet
        implements \JsonSerializable
{
    /**
     * Message type enumeration
     */
    const TYPE = 0;

    /**
     * ...
     *
     * @var bool
     */
    public $dup = false;

    /**
     * ...
     *
     * @var int
     */
    public $qos = Protocol::QOS_AT_MOST_ONCE;

    /**
     * ...
     *
     * @var bool
     */
    public $retain = false;


    public static function decode(): Packet
    {
    }


    public function __construct()
    {
    }


    public function encode(): string
    {
        $packet = $this->encodeInternal();

        /* add first byte and packet size */
        $firstbyte = (static::TYPE << 4) |         // Packet type
                     ($this->dup ? 0x08 : 0) |     // DUP flag
                     ($this->qos & 0x03 << 1) |    // QoS flags
                     ($this->retain ? 0x01 : 0);   // Retain flag

        return chr($firstbyte) . DataEncoder::varint(strlen($packet)) . $packet;
    }

    abstract protected function encodeInternal(): string;

    abstract protected function decodeInternal(string $data): void;

    public function __set($name, $value)
    {
        trigger_error("Unsupported field [" . get_class($this) . "::$name]", E_USER_NOTICE);
    }

    public function jsonSerialize()
    {
        $props = get_object_vars($this);
        return $props;
    }

    public function __toString()
    {
        return "[packet " . basename(get_class($this)) . "]" .
            ($this->dup ? " [DUP]" : "") .
            ($this->qos ? " [QoS " . $this->qos . "]" : "") .
            ($this->retain ? " [RETAIN]" : "") . "\n" . json_encode($this, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
