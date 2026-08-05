<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Mqtt5;

use PhpMqtt\Client\Exceptions\MalformedPacketException;
use PhpMqtt\Client\Exceptions\ProtocolErrorException;
use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\Property;
use PhpMqtt\Client\Protocol\PropertyIdentifier;
use PhpMqtt\Client\Protocol\Topic;
use PhpMqtt\Client\Protocol\Wire\BinaryReader;
use PhpMqtt\Client\Protocol\Wire\BinaryWriter;

/**
 * Encodes and validates packet-specific MQTT 5 properties.
 *
 * @package PhpMqtt\Client\Protocol\Mqtt5
 */
final class PropertyCodec
{
    public const CONTEXT_WILL = 'will';

    public const DIRECTION_CLIENT_TO_SERVER = 'client-to-server';
    public const DIRECTION_SERVER_TO_CLIENT = 'server-to-client';

    private const TYPE_BYTE    = 'byte';
    private const TYPE_UINT16  = 'uint16';
    private const TYPE_UINT32  = 'uint32';
    private const TYPE_VARINT  = 'varint';
    private const TYPE_BINARY  = 'binary';
    private const TYPE_UTF8    = 'utf8';
    private const TYPE_PAIR    = 'pair';

    /** @var array<int, string> */
    private const TYPES = [
        PropertyIdentifier::PAYLOAD_FORMAT_INDICATOR => self::TYPE_BYTE,
        PropertyIdentifier::MESSAGE_EXPIRY_INTERVAL => self::TYPE_UINT32,
        PropertyIdentifier::CONTENT_TYPE => self::TYPE_UTF8,
        PropertyIdentifier::RESPONSE_TOPIC => self::TYPE_UTF8,
        PropertyIdentifier::CORRELATION_DATA => self::TYPE_BINARY,
        PropertyIdentifier::SUBSCRIPTION_IDENTIFIER => self::TYPE_VARINT,
        PropertyIdentifier::SESSION_EXPIRY_INTERVAL => self::TYPE_UINT32,
        PropertyIdentifier::ASSIGNED_CLIENT_IDENTIFIER => self::TYPE_UTF8,
        PropertyIdentifier::SERVER_KEEP_ALIVE => self::TYPE_UINT16,
        PropertyIdentifier::AUTHENTICATION_METHOD => self::TYPE_UTF8,
        PropertyIdentifier::AUTHENTICATION_DATA => self::TYPE_BINARY,
        PropertyIdentifier::REQUEST_PROBLEM_INFORMATION => self::TYPE_BYTE,
        PropertyIdentifier::WILL_DELAY_INTERVAL => self::TYPE_UINT32,
        PropertyIdentifier::REQUEST_RESPONSE_INFORMATION => self::TYPE_BYTE,
        PropertyIdentifier::RESPONSE_INFORMATION => self::TYPE_UTF8,
        PropertyIdentifier::SERVER_REFERENCE => self::TYPE_UTF8,
        PropertyIdentifier::REASON_STRING => self::TYPE_UTF8,
        PropertyIdentifier::RECEIVE_MAXIMUM => self::TYPE_UINT16,
        PropertyIdentifier::TOPIC_ALIAS_MAXIMUM => self::TYPE_UINT16,
        PropertyIdentifier::TOPIC_ALIAS => self::TYPE_UINT16,
        PropertyIdentifier::MAXIMUM_QOS => self::TYPE_BYTE,
        PropertyIdentifier::RETAIN_AVAILABLE => self::TYPE_BYTE,
        PropertyIdentifier::USER_PROPERTY => self::TYPE_PAIR,
        PropertyIdentifier::MAXIMUM_PACKET_SIZE => self::TYPE_UINT32,
        PropertyIdentifier::WILDCARD_SUBSCRIPTION_AVAILABLE => self::TYPE_BYTE,
        PropertyIdentifier::SUBSCRIPTION_IDENTIFIER_AVAILABLE => self::TYPE_BYTE,
        PropertyIdentifier::SHARED_SUBSCRIPTION_AVAILABLE => self::TYPE_BYTE,
    ];

    /** @var array<int|string, int[]> */
    private const ALLOWED = [
        PacketType::CONNECT => [
            0x11, 0x15, 0x16, 0x17, 0x19, 0x21, 0x22, 0x26, 0x27,
        ],
        self::CONTEXT_WILL => [
            0x01, 0x02, 0x03, 0x08, 0x09, 0x18, 0x26,
        ],
        PacketType::CONNACK => [
            0x11, 0x12, 0x13, 0x15, 0x16, 0x1A, 0x1C, 0x1F, 0x21, 0x22,
            0x24, 0x25, 0x26, 0x27, 0x28, 0x29, 0x2A,
        ],
        PacketType::PUBLISH => [
            0x01, 0x02, 0x03, 0x08, 0x09, 0x0B, 0x23, 0x26,
        ],
        PacketType::PUBACK => [0x1F, 0x26],
        PacketType::PUBREC => [0x1F, 0x26],
        PacketType::PUBREL => [0x1F, 0x26],
        PacketType::PUBCOMP => [0x1F, 0x26],
        PacketType::SUBSCRIBE => [0x0B, 0x26],
        PacketType::SUBACK => [0x1F, 0x26],
        PacketType::UNSUBSCRIBE => [0x26],
        PacketType::UNSUBACK => [0x1F, 0x26],
        PacketType::DISCONNECT => [0x11, 0x1C, 0x1F, 0x26],
        PacketType::AUTH => [0x15, 0x16, 0x1F, 0x26],
    ];

    /** @var int[] */
    private const BOOLEAN_PROPERTIES = [
        PropertyIdentifier::PAYLOAD_FORMAT_INDICATOR,
        PropertyIdentifier::REQUEST_PROBLEM_INFORMATION,
        PropertyIdentifier::REQUEST_RESPONSE_INFORMATION,
        PropertyIdentifier::RETAIN_AVAILABLE,
        PropertyIdentifier::WILDCARD_SUBSCRIPTION_AVAILABLE,
        PropertyIdentifier::SUBSCRIPTION_IDENTIFIER_AVAILABLE,
        PropertyIdentifier::SHARED_SUBSCRIPTION_AVAILABLE,
    ];

    /**
     * Returns a Variable Byte Integer property length followed by encoded properties.
     *
     * @param int|string $context
     */
    public function encode(
        Properties $properties,
        $context,
        string $direction = self::DIRECTION_CLIENT_TO_SERVER
    ): string
    {
        $this->validate($properties, $context, $direction);
        $writer = new BinaryWriter();

        foreach ($properties as $property) {
            $writer->writeVariableByteInteger($property->getIdentifier());
            $this->writeValue($writer, $property);
        }

        $body   = $writer->getBuffer();
        $result = new BinaryWriter();

        return $result->writeVariableByteInteger(strlen($body))->writeRaw($body)->getBuffer();
    }

    /**
     * @param int|string $context
     */
    public function decode(
        BinaryReader $reader,
        $context,
        string $direction = self::DIRECTION_SERVER_TO_CLIENT
    ): Properties
    {
        $propertyLength = $reader->readVariableByteInteger();
        $propertyReader = $reader->createLimitedReader($propertyLength);
        $properties     = [];

        while ($propertyReader->hasRemaining()) {
            $identifier = $propertyReader->readVariableByteInteger();

            if (!isset(self::TYPES[$identifier])) {
                throw new MalformedPacketException(sprintf('Unknown MQTT 5 property identifier [0x%02X].', $identifier));
            }

            $properties[] = new Property($identifier, $this->readValue($propertyReader, $identifier));
        }

        $result = new Properties($properties);

        try {
            $this->validate($result, $context, $direction);
        } catch (\InvalidArgumentException $exception) {
            throw new ProtocolErrorException($exception->getMessage());
        }

        return $result;
    }

    /**
     * @param int|string $context
     */
    public function validate(
        Properties $properties,
        $context,
        string $direction = self::DIRECTION_CLIENT_TO_SERVER
    ): void
    {
        if (!isset(self::ALLOWED[$context])) {
            throw new \InvalidArgumentException('No MQTT 5 property rules exist for this packet context.');
        }

        if (!in_array($direction, [self::DIRECTION_CLIENT_TO_SERVER, self::DIRECTION_SERVER_TO_CLIENT], true)) {
            throw new \InvalidArgumentException('Invalid MQTT packet direction.');
        }

        $seen = [];

        foreach ($properties as $property) {
            $identifier = $property->getIdentifier();

            if (!isset(self::TYPES[$identifier]) || !in_array($identifier, self::ALLOWED[$context], true)) {
                throw new \InvalidArgumentException(sprintf(
                    'Property [0x%02X] is not permitted for this MQTT packet.',
                    $identifier
                ));
            }

            if (isset($seen[$identifier]) && !$this->mayRepeat($identifier, $context, $direction)) {
                throw new \InvalidArgumentException(sprintf(
                    'Property [0x%02X] may occur at most once in this MQTT packet.',
                    $identifier
                ));
            }

            $seen[$identifier] = true;
            $this->validateValue($property);
        }

        if ($properties->has(PropertyIdentifier::AUTHENTICATION_DATA)
            && !$properties->has(PropertyIdentifier::AUTHENTICATION_METHOD)) {
            throw new \InvalidArgumentException('Authentication Data requires an Authentication Method in the same packet.');
        }

        if ($context === PacketType::PUBLISH
            && $direction === self::DIRECTION_CLIENT_TO_SERVER
            && $properties->has(PropertyIdentifier::SUBSCRIPTION_IDENTIFIER)) {
            throw new \InvalidArgumentException('A client-to-server PUBLISH cannot contain a Subscription Identifier.');
        }
    }

    private function mayRepeat(int $identifier, $context, string $direction): bool
    {
        if ($identifier === PropertyIdentifier::USER_PROPERTY) {
            return true;
        }

        return $identifier === PropertyIdentifier::SUBSCRIPTION_IDENTIFIER
            && $context === PacketType::PUBLISH
            && $direction === self::DIRECTION_SERVER_TO_CLIENT;
    }

    private function validateValue(Property $property): void
    {
        $identifier = $property->getIdentifier();
        $value      = $property->getValue();
        $type       = self::TYPES[$identifier];

        if (in_array($type, [self::TYPE_BYTE, self::TYPE_UINT16, self::TYPE_UINT32, self::TYPE_VARINT], true)
            && !is_int($value)) {
            throw new \InvalidArgumentException('Integer MQTT property values must be integers.');
        }

        if (in_array($type, [self::TYPE_BINARY, self::TYPE_UTF8], true) && !is_string($value)) {
            throw new \InvalidArgumentException('String MQTT property values must be strings.');
        }

        if ($type === self::TYPE_PAIR
            && (!is_array($value) || count($value) !== 2 || !is_string($value[0] ?? null) || !is_string($value[1] ?? null))) {
            throw new \InvalidArgumentException('User Property values must be a two-string array.');
        }

        if (in_array($identifier, self::BOOLEAN_PROPERTIES, true) && !in_array($value, [0, 1], true)) {
            throw new \InvalidArgumentException('Boolean MQTT property values must be 0 or 1.');
        }

        if ($identifier === PropertyIdentifier::MAXIMUM_QOS && !in_array($value, [0, 1], true)) {
            throw new \InvalidArgumentException('Maximum QoS must be 0 or 1.');
        }

        if (in_array($identifier, [
            PropertyIdentifier::RECEIVE_MAXIMUM,
            PropertyIdentifier::TOPIC_ALIAS,
            PropertyIdentifier::MAXIMUM_PACKET_SIZE,
            PropertyIdentifier::SUBSCRIPTION_IDENTIFIER,
        ], true) && $value === 0) {
            throw new \InvalidArgumentException('This MQTT property value must be non-zero.');
        }

        if ($identifier === PropertyIdentifier::RESPONSE_TOPIC) {
            Topic::assertValidName($value);
        }

        $writer = new BinaryWriter();
        $this->writeValue($writer, $property);
    }

    private function writeValue(BinaryWriter $writer, Property $property): void
    {
        $value = $property->getValue();

        switch (self::TYPES[$property->getIdentifier()]) {
            case self::TYPE_BYTE:
                $writer->writeByte($value);
                break;

            case self::TYPE_UINT16:
                $writer->writeUInt16($value);
                break;

            case self::TYPE_UINT32:
                $writer->writeUInt32($value);
                break;

            case self::TYPE_VARINT:
                $writer->writeVariableByteInteger($value);
                break;

            case self::TYPE_BINARY:
                $writer->writeBinaryData($value);
                break;

            case self::TYPE_UTF8:
                $writer->writeUtf8String($value);
                break;

            case self::TYPE_PAIR:
                $writer->writeUtf8Pair($value[0], $value[1]);
                break;
        }
    }

    /**
     * @return int|string|string[]
     */
    private function readValue(BinaryReader $reader, int $identifier)
    {
        switch (self::TYPES[$identifier]) {
            case self::TYPE_BYTE:
                return $reader->readByte();

            case self::TYPE_UINT16:
                return $reader->readUInt16();

            case self::TYPE_UINT32:
                return $reader->readUInt32();

            case self::TYPE_VARINT:
                return $reader->readVariableByteInteger();

            case self::TYPE_BINARY:
                return $reader->readBinaryData();

            case self::TYPE_UTF8:
                return $reader->readUtf8String();

            case self::TYPE_PAIR:
                return $reader->readUtf8Pair();
        }

        throw new MalformedPacketException('Unknown MQTT property data type.');
    }
}
