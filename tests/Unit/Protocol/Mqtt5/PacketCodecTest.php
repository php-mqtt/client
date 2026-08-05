<?php

declare(strict_types=1);

namespace Tests\Unit\Protocol\Mqtt5;

use PhpMqtt\Client\Exceptions\MalformedPacketException;
use PhpMqtt\Client\Protocol\Mqtt5\PacketCodec;
use PhpMqtt\Client\Protocol\Mqtt5\PropertyCodec;
use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Packets\AcknowledgementPacket;
use PhpMqtt\Client\Protocol\Packets\AuthPacket;
use PhpMqtt\Client\Protocol\Packets\ConnectPacket;
use PhpMqtt\Client\Protocol\Packets\ConnAckPacket;
use PhpMqtt\Client\Protocol\Packets\DisconnectPacket;
use PhpMqtt\Client\Protocol\Packets\EmptyPacket;
use PhpMqtt\Client\Protocol\Packets\PublishPacket;
use PhpMqtt\Client\Protocol\Packets\ResultPacket;
use PhpMqtt\Client\Protocol\Packets\SubscribePacket;
use PhpMqtt\Client\Protocol\Packets\SubscriptionRequest;
use PhpMqtt\Client\Protocol\Packets\UnsubscribePacket;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\ReasonCode;
use PHPUnit\Framework\TestCase;

class PacketCodecTest extends TestCase
{
    /**
     * @dataProvider clientPacketVectors
     */
    public function test_client_packet_golden_vectors(object $packet, string $expectedHex): void
    {
        $codec = new PacketCodec();
        $wire  = $codec->encode($packet);

        $this->assertSame($expectedHex, bin2hex($wire));
        $this->assertSame(
            get_class($packet),
            get_class($codec->decode($wire, PropertyCodec::DIRECTION_CLIENT_TO_SERVER))
        );
    }

    public function clientPacketVectors(): array
    {
        $empty = Properties::empty();

        return [
            'CONNECT' => [
                new ConnectPacket('client', true, 10, $empty),
                '101300044d5154540502000a000006636c69656e74',
            ],
            'PUBLISH' => [new PublishPacket('a', 'b', 0, false, false, null, $empty), '30050001610062'],
            'PUBACK compact' => [
                new AcknowledgementPacket(PacketType::PUBACK, 42, ReasonCode::SUCCESS, $empty),
                '4002002a',
            ],
            'PUBREC reason without properties' => [
                new AcknowledgementPacket(PacketType::PUBREC, 42, ReasonCode::UNSPECIFIED_ERROR, $empty),
                '5003002a80',
            ],
            'PUBREL' => [
                new AcknowledgementPacket(PacketType::PUBREL, 42, ReasonCode::SUCCESS, $empty),
                '6202002a',
            ],
            'PUBCOMP' => [
                new AcknowledgementPacket(PacketType::PUBCOMP, 42, ReasonCode::SUCCESS, $empty),
                '7002002a',
            ],
            'SUBSCRIBE' => [
                new SubscribePacket(42, [new SubscriptionRequest('a')], $empty),
                '8207002a0000016100',
            ],
            'UNSUBSCRIBE' => [new UnsubscribePacket(42, ['a'], $empty), 'a206002a00000161'],
            'PINGREQ' => [new EmptyPacket(PacketType::PINGREQ), 'c000'],
            'DISCONNECT compact' => [new DisconnectPacket(ReasonCode::NORMAL_DISCONNECTION, $empty), 'e000'],
            'AUTH compact' => [new AuthPacket(ReasonCode::SUCCESS, $empty), 'f000'],
        ];
    }

    /**
     * @dataProvider serverPacketVectors
     */
    public function test_server_packet_golden_vectors(object $packet, string $expectedHex): void
    {
        $codec = new PacketCodec();
        $wire  = $codec->encode($packet, PropertyCodec::DIRECTION_SERVER_TO_CLIENT);

        $this->assertSame($expectedHex, bin2hex($wire));
        $this->assertSame(get_class($packet), get_class($codec->decode($wire)));
    }

    public function serverPacketVectors(): array
    {
        $empty = Properties::empty();

        return [
            'CONNACK' => [new ConnAckPacket(false, ReasonCode::SUCCESS, $empty), '2003000000'],
            'SUBACK' => [new ResultPacket(PacketType::SUBACK, 42, [0], $empty), '9004002a0000'],
            'UNSUBACK' => [new ResultPacket(PacketType::UNSUBACK, 42, [0], $empty), 'b004002a0000'],
            'PINGRESP' => [new EmptyPacket(PacketType::PINGRESP), 'd000'],
        ];
    }

    public function test_compact_disconnect_forms_are_decoded(): void
    {
        $codec = new PacketCodec();

        $this->assertSame(ReasonCode::SUCCESS, $codec->decode(hex2bin('e000'))->getReasonCode());
        $this->assertSame(
            ReasonCode::SERVER_SHUTTING_DOWN,
            $codec->decode(hex2bin('e0018b'))->getReasonCode()
        );
    }

    /**
     * @dataProvider malformedPackets
     */
    public function test_malformed_packets_are_rejected(string $hex): void
    {
        $this->expectException(MalformedPacketException::class);

        (new PacketCodec())->decode(hex2bin($hex));
    }

    public function malformedPackets(): array
    {
        return [
            'invalid flags' => ['d100'],
            'non-canonical remaining length' => ['d08000'],
            'truncated' => ['20030000'],
            'trailing bytes' => ['d00000'],
        ];
    }
}
