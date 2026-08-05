<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol;

/**
 * MQTT control packet type identifiers.
 *
 * @package PhpMqtt\Client\Protocol
 */
final class PacketType
{
    public const CONNECT     = 1;
    public const CONNACK     = 2;
    public const PUBLISH     = 3;
    public const PUBACK      = 4;
    public const PUBREC      = 5;
    public const PUBREL      = 6;
    public const PUBCOMP     = 7;
    public const SUBSCRIBE   = 8;
    public const SUBACK      = 9;
    public const UNSUBSCRIBE = 10;
    public const UNSUBACK    = 11;
    public const PINGREQ     = 12;
    public const PINGRESP    = 13;
    public const DISCONNECT  = 14;
    public const AUTH        = 15;

    /** @var array<int, string> */
    private const NAMES = [
        self::CONNECT => 'CONNECT',
        self::CONNACK => 'CONNACK',
        self::PUBLISH => 'PUBLISH',
        self::PUBACK => 'PUBACK',
        self::PUBREC => 'PUBREC',
        self::PUBREL => 'PUBREL',
        self::PUBCOMP => 'PUBCOMP',
        self::SUBSCRIBE => 'SUBSCRIBE',
        self::SUBACK => 'SUBACK',
        self::UNSUBSCRIBE => 'UNSUBSCRIBE',
        self::UNSUBACK => 'UNSUBACK',
        self::PINGREQ => 'PINGREQ',
        self::PINGRESP => 'PINGRESP',
        self::DISCONNECT => 'DISCONNECT',
        self::AUTH => 'AUTH',
    ];

    /** @var array<int, int> */
    private const REQUIRED_FLAGS = [
        self::CONNECT => 0,
        self::CONNACK => 0,
        self::PUBACK => 0,
        self::PUBREC => 0,
        self::PUBREL => 2,
        self::PUBCOMP => 0,
        self::SUBSCRIBE => 2,
        self::SUBACK => 0,
        self::UNSUBSCRIBE => 2,
        self::UNSUBACK => 0,
        self::PINGREQ => 0,
        self::PINGRESP => 0,
        self::DISCONNECT => 0,
        self::AUTH => 0,
    ];

    private function __construct()
    {
    }

    public static function isValid(int $type): bool
    {
        return isset(self::NAMES[$type]);
    }

    public static function name(int $type): string
    {
        if (!self::isValid($type)) {
            throw new \InvalidArgumentException(sprintf('Invalid MQTT packet type [%d].', $type));
        }

        return self::NAMES[$type];
    }

    public static function requiredFlags(int $type): ?int
    {
        return self::REQUIRED_FLAGS[$type] ?? null;
    }
}
