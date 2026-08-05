<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol;

/**
 * MQTT 5 reason codes and packet-specific validation.
 *
 * @package PhpMqtt\Client\Protocol
 */
final class ReasonCode
{
    public const SUCCESS                                = 0x00;
    public const NORMAL_DISCONNECTION                   = 0x00;
    public const GRANTED_QOS_0                          = 0x00;
    public const GRANTED_QOS_1                          = 0x01;
    public const GRANTED_QOS_2                          = 0x02;
    public const DISCONNECT_WITH_WILL_MESSAGE           = 0x04;
    public const NO_MATCHING_SUBSCRIBERS                = 0x10;
    public const NO_SUBSCRIPTION_EXISTED                = 0x11;
    public const CONTINUE_AUTHENTICATION                = 0x18;
    public const REAUTHENTICATE                         = 0x19;
    public const UNSPECIFIED_ERROR                      = 0x80;
    public const MALFORMED_PACKET                       = 0x81;
    public const PROTOCOL_ERROR                         = 0x82;
    public const IMPLEMENTATION_SPECIFIC_ERROR          = 0x83;
    public const UNSUPPORTED_PROTOCOL_VERSION           = 0x84;
    public const CLIENT_IDENTIFIER_NOT_VALID            = 0x85;
    public const BAD_USER_NAME_OR_PASSWORD              = 0x86;
    public const NOT_AUTHORIZED                         = 0x87;
    public const SERVER_UNAVAILABLE                     = 0x88;
    public const SERVER_BUSY                            = 0x89;
    public const BANNED                                 = 0x8A;
    public const SERVER_SHUTTING_DOWN                   = 0x8B;
    public const BAD_AUTHENTICATION_METHOD              = 0x8C;
    public const KEEP_ALIVE_TIMEOUT                     = 0x8D;
    public const SESSION_TAKEN_OVER                     = 0x8E;
    public const TOPIC_FILTER_INVALID                   = 0x8F;
    public const TOPIC_NAME_INVALID                     = 0x90;
    public const PACKET_IDENTIFIER_IN_USE               = 0x91;
    public const PACKET_IDENTIFIER_NOT_FOUND            = 0x92;
    public const RECEIVE_MAXIMUM_EXCEEDED               = 0x93;
    public const TOPIC_ALIAS_INVALID                    = 0x94;
    public const PACKET_TOO_LARGE                       = 0x95;
    public const MESSAGE_RATE_TOO_HIGH                  = 0x96;
    public const QUOTA_EXCEEDED                         = 0x97;
    public const ADMINISTRATIVE_ACTION                  = 0x98;
    public const PAYLOAD_FORMAT_INVALID                 = 0x99;
    public const RETAIN_NOT_SUPPORTED                   = 0x9A;
    public const QOS_NOT_SUPPORTED                      = 0x9B;
    public const USE_ANOTHER_SERVER                     = 0x9C;
    public const SERVER_MOVED                           = 0x9D;
    public const SHARED_SUBSCRIPTIONS_NOT_SUPPORTED     = 0x9E;
    public const CONNECTION_RATE_EXCEEDED               = 0x9F;
    public const MAXIMUM_CONNECT_TIME                   = 0xA0;
    public const SUBSCRIPTION_IDENTIFIERS_NOT_SUPPORTED = 0xA1;
    public const WILDCARD_SUBSCRIPTIONS_NOT_SUPPORTED   = 0xA2;

    /** @var array<int, int[]> */
    private const ALLOWED = [
        PacketType::CONNACK => [
            0x00, 0x80, 0x81, 0x82, 0x83, 0x84, 0x85, 0x86, 0x87, 0x88, 0x89,
            0x8A, 0x8C, 0x90, 0x95, 0x97, 0x99, 0x9A, 0x9B, 0x9C, 0x9D, 0x9F,
        ],
        PacketType::PUBACK => [0x00, 0x10, 0x80, 0x83, 0x87, 0x90, 0x91, 0x97, 0x99],
        PacketType::PUBREC => [0x00, 0x10, 0x80, 0x83, 0x87, 0x90, 0x91, 0x97, 0x99],
        PacketType::PUBREL => [0x00, 0x92],
        PacketType::PUBCOMP => [0x00, 0x92],
        PacketType::SUBACK => [0x00, 0x01, 0x02, 0x80, 0x83, 0x87, 0x8F, 0x91, 0x97, 0x9E, 0xA1, 0xA2],
        PacketType::UNSUBACK => [0x00, 0x11, 0x80, 0x83, 0x87, 0x8F, 0x91],
        PacketType::DISCONNECT => [
            0x00, 0x04, 0x80, 0x81, 0x82, 0x83, 0x87, 0x89, 0x8B, 0x8D, 0x8E,
            0x8F, 0x90, 0x93, 0x94, 0x95, 0x96, 0x97, 0x98, 0x99, 0x9A, 0x9B,
            0x9C, 0x9D, 0x9E, 0x9F, 0xA0, 0xA1, 0xA2,
        ],
        PacketType::AUTH => [0x00, 0x18, 0x19],
    ];

    private function __construct()
    {
    }

    public static function isError(int $reasonCode): bool
    {
        return $reasonCode >= 0x80;
    }

    public static function assertAllowed(int $packetType, int $reasonCode): void
    {
        if (!isset(self::ALLOWED[$packetType]) || !in_array($reasonCode, self::ALLOWED[$packetType], true)) {
            throw new \InvalidArgumentException(sprintf(
                'Reason code [0x%02X] is not valid for %s.',
                $reasonCode,
                PacketType::name($packetType)
            ));
        }
    }
}
