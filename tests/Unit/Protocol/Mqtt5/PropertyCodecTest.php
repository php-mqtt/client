<?php

declare(strict_types=1);

namespace Tests\Unit\Protocol\Mqtt5;

use PhpMqtt\Client\Exceptions\ProtocolErrorException;
use PhpMqtt\Client\Protocol\Mqtt5\PropertyCodec;
use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\PropertyIdentifier;
use PhpMqtt\Client\Protocol\Wire\BinaryReader;
use PHPUnit\Framework\TestCase;

class PropertyCodecTest extends TestCase
{
    /**
     * @dataProvider everyPropertyGroup
     *
     * @param int|string $context
     */
    public function test_every_mqtt5_property_type_round_trips(
        Properties $properties,
        $context,
        string $direction
    ): void
    {
        $codec   = new PropertyCodec();
        $encoded = $codec->encode($properties, $context, $direction);
        $decoded = $codec->decode(new BinaryReader($encoded), $context, $direction);

        $this->assertEquals($properties->toArray(), $decoded->toArray());
    }

    public function everyPropertyGroup(): array
    {
        $id = PropertyIdentifier::class;

        return [
            'CONNECT' => [
                Properties::empty()
                    ->with($id::SESSION_EXPIRY_INTERVAL, 60)
                    ->with($id::RECEIVE_MAXIMUM, 10)
                    ->with($id::MAXIMUM_PACKET_SIZE, 1024)
                    ->with($id::TOPIC_ALIAS_MAXIMUM, 4)
                    ->with($id::REQUEST_RESPONSE_INFORMATION, 1)
                    ->with($id::REQUEST_PROBLEM_INFORMATION, 1)
                    ->with($id::USER_PROPERTY, ['name', 'value'])
                    ->with($id::AUTHENTICATION_METHOD, 'SCRAM')
                    ->with($id::AUTHENTICATION_DATA, 'initial'),
                PacketType::CONNECT,
                PropertyCodec::DIRECTION_CLIENT_TO_SERVER,
            ],
            'Will' => [
                Properties::empty()
                    ->with($id::WILL_DELAY_INTERVAL, 1)
                    ->with($id::PAYLOAD_FORMAT_INDICATOR, 1)
                    ->with($id::MESSAGE_EXPIRY_INTERVAL, 30)
                    ->with($id::CONTENT_TYPE, 'text/plain')
                    ->with($id::RESPONSE_TOPIC, 'response/topic')
                    ->with($id::CORRELATION_DATA, 'correlation')
                    ->with($id::USER_PROPERTY, ['name', 'value']),
                PropertyCodec::CONTEXT_WILL,
                PropertyCodec::DIRECTION_CLIENT_TO_SERVER,
            ],
            'CONNACK' => [
                Properties::empty()
                    ->with($id::SESSION_EXPIRY_INTERVAL, 60)
                    ->with($id::RECEIVE_MAXIMUM, 10)
                    ->with($id::MAXIMUM_QOS, 1)
                    ->with($id::RETAIN_AVAILABLE, 1)
                    ->with($id::MAXIMUM_PACKET_SIZE, 1024)
                    ->with($id::ASSIGNED_CLIENT_IDENTIFIER, 'assigned')
                    ->with($id::TOPIC_ALIAS_MAXIMUM, 4)
                    ->with($id::REASON_STRING, 'connected')
                    ->with($id::USER_PROPERTY, ['name', 'value'])
                    ->with($id::WILDCARD_SUBSCRIPTION_AVAILABLE, 1)
                    ->with($id::SUBSCRIPTION_IDENTIFIER_AVAILABLE, 1)
                    ->with($id::SHARED_SUBSCRIPTION_AVAILABLE, 1)
                    ->with($id::SERVER_KEEP_ALIVE, 20)
                    ->with($id::RESPONSE_INFORMATION, 'response')
                    ->with($id::SERVER_REFERENCE, 'mqtt.example.com')
                    ->with($id::AUTHENTICATION_METHOD, 'SCRAM')
                    ->with($id::AUTHENTICATION_DATA, 'final'),
                PacketType::CONNACK,
                PropertyCodec::DIRECTION_SERVER_TO_CLIENT,
            ],
            'PUBLISH' => [
                Properties::empty()
                    ->with($id::PAYLOAD_FORMAT_INDICATOR, 1)
                    ->with($id::MESSAGE_EXPIRY_INTERVAL, 30)
                    ->with($id::TOPIC_ALIAS, 1)
                    ->with($id::RESPONSE_TOPIC, 'response/topic')
                    ->with($id::CORRELATION_DATA, 'correlation')
                    ->with($id::USER_PROPERTY, ['name', 'value'])
                    ->with($id::SUBSCRIPTION_IDENTIFIER, 1)
                    ->with($id::SUBSCRIPTION_IDENTIFIER, 2)
                    ->with($id::CONTENT_TYPE, 'text/plain'),
                PacketType::PUBLISH,
                PropertyCodec::DIRECTION_SERVER_TO_CLIENT,
            ],
            'acknowledgement' => [
                Properties::empty()
                    ->with($id::REASON_STRING, 'ok')
                    ->with($id::USER_PROPERTY, ['name', 'value']),
                PacketType::PUBACK,
                PropertyCodec::DIRECTION_SERVER_TO_CLIENT,
            ],
            'SUBSCRIBE' => [
                Properties::empty()
                    ->with($id::SUBSCRIPTION_IDENTIFIER, 1)
                    ->with($id::USER_PROPERTY, ['name', 'value']),
                PacketType::SUBSCRIBE,
                PropertyCodec::DIRECTION_CLIENT_TO_SERVER,
            ],
            'DISCONNECT' => [
                Properties::empty()
                    ->with($id::SESSION_EXPIRY_INTERVAL, 0)
                    ->with($id::SERVER_REFERENCE, 'mqtt.example.com')
                    ->with($id::REASON_STRING, 'moving')
                    ->with($id::USER_PROPERTY, ['name', 'value']),
                PacketType::DISCONNECT,
                PropertyCodec::DIRECTION_SERVER_TO_CLIENT,
            ],
        ];
    }

    public function test_property_round_trip_preserves_order_and_duplicates(): void
    {
        $properties = Properties::empty()
            ->with(PropertyIdentifier::PAYLOAD_FORMAT_INDICATOR, 1)
            ->with(PropertyIdentifier::USER_PROPERTY, ['first', 'one'])
            ->with(PropertyIdentifier::USER_PROPERTY, ['second', 'two'])
            ->with(PropertyIdentifier::CONTENT_TYPE, 'application/json');
        $codec   = new PropertyCodec();
        $encoded = $codec->encode($properties, PacketType::PUBLISH);
        $decoded = $codec->decode(
            new BinaryReader($encoded),
            PacketType::PUBLISH,
            PropertyCodec::DIRECTION_CLIENT_TO_SERVER
        );

        $this->assertSame([
            ['first', 'one'],
            ['second', 'two'],
        ], $decoded->getAll(PropertyIdentifier::USER_PROPERTY));
        $this->assertSame('application/json', $decoded->get(PropertyIdentifier::CONTENT_TYPE));
    }

    public function test_duplicate_singleton_property_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PropertyCodec())->encode(
            Properties::empty()
                ->with(PropertyIdentifier::RECEIVE_MAXIMUM, 10)
                ->with(PropertyIdentifier::RECEIVE_MAXIMUM, 20),
            PacketType::CONNECT
        );
    }

    public function test_property_in_wrong_packet_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PropertyCodec())->encode(
            Properties::empty()->with(PropertyIdentifier::SERVER_KEEP_ALIVE, 10),
            PacketType::PUBLISH
        );
    }

    public function test_subscription_identifier_is_rejected_on_outgoing_publish(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new PropertyCodec())->encode(
            Properties::empty()->with(PropertyIdentifier::SUBSCRIPTION_IDENTIFIER, 1),
            PacketType::PUBLISH
        );
    }

    public function test_invalid_boolean_value_received_is_a_protocol_error(): void
    {
        $this->expectException(ProtocolErrorException::class);

        (new PropertyCodec())->decode(
            new BinaryReader(hex2bin('020102')),
            PacketType::PUBLISH,
            PropertyCodec::DIRECTION_SERVER_TO_CLIENT
        );
    }
}
