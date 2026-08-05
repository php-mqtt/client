<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Mqtt5;

use PhpMqtt\Client\Exceptions\MalformedPacketException;
use PhpMqtt\Client\Exceptions\ProtocolErrorException;
use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Packets\AcknowledgementPacket;
use PhpMqtt\Client\Protocol\Packets\AuthPacket;
use PhpMqtt\Client\Protocol\Packets\ConnectPacket;
use PhpMqtt\Client\Protocol\Packets\ConnAckPacket;
use PhpMqtt\Client\Protocol\Packets\ControlPacket;
use PhpMqtt\Client\Protocol\Packets\DisconnectPacket;
use PhpMqtt\Client\Protocol\Packets\EmptyPacket;
use PhpMqtt\Client\Protocol\Packets\PublishPacket;
use PhpMqtt\Client\Protocol\Packets\ResultPacket;
use PhpMqtt\Client\Protocol\Packets\SubscribePacket;
use PhpMqtt\Client\Protocol\Packets\SubscriptionRequest;
use PhpMqtt\Client\Protocol\Packets\UnsubscribePacket;
use PhpMqtt\Client\Protocol\Packets\Will;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\PropertyIdentifier;
use PhpMqtt\Client\Protocol\Qos;
use PhpMqtt\Client\Protocol\ReasonCode;
use PhpMqtt\Client\Protocol\Wire\BinaryReader;
use PhpMqtt\Client\Protocol\Wire\BinaryWriter;
use PhpMqtt\Client\Protocol\Wire\PacketFramer;

/**
 * Complete MQTT 5 control-packet encoder and decoder.
 *
 * @package PhpMqtt\Client\Protocol\Mqtt5
 */
final class PacketCodec
{
    private PropertyCodec $propertyCodec;

    public function __construct(
        ?PropertyCodec $propertyCodec = null,
        private int $maximumOutboundPacketSize = PacketFramer::MAXIMUM_PACKET_SIZE
    )
    {
        $this->propertyCodec = $propertyCodec ?? new PropertyCodec();
        $this->setMaximumOutboundPacketSize($maximumOutboundPacketSize);
    }

    public function setMaximumOutboundPacketSize(int $maximumPacketSize): void
    {
        if ($maximumPacketSize < 2 || $maximumPacketSize > PacketFramer::MAXIMUM_PACKET_SIZE) {
            throw new \InvalidArgumentException('The outbound packet size limit is outside the MQTT range.');
        }

        $this->maximumOutboundPacketSize = $maximumPacketSize;
    }

    public function encode(
        ControlPacket $packet,
        string $direction = PropertyCodec::DIRECTION_CLIENT_TO_SERVER
    ): string
    {
        $this->assertPacketDirection($packet->getType(), $direction);

        switch ($packet->getType()) {
            case PacketType::CONNECT:
                $this->assertInstanceOf($packet, ConnectPacket::class);
                [$flags, $body] = [0, $this->encodeConnect($packet)];
                break;

            case PacketType::CONNACK:
                $this->assertInstanceOf($packet, ConnAckPacket::class);
                [$flags, $body] = [0, $this->encodeConnAck($packet)];
                break;

            case PacketType::PUBLISH:
                $this->assertInstanceOf($packet, PublishPacket::class);
                [$flags, $body] = $this->encodePublish($packet, $direction);
                break;

            case PacketType::PUBACK:
            case PacketType::PUBREC:
            case PacketType::PUBREL:
            case PacketType::PUBCOMP:
                $this->assertInstanceOf($packet, AcknowledgementPacket::class);
                [$flags, $body] = [PacketType::requiredFlags($packet->getType()), $this->encodeAcknowledgement($packet, $direction)];
                break;

            case PacketType::SUBSCRIBE:
                $this->assertInstanceOf($packet, SubscribePacket::class);
                [$flags, $body] = [2, $this->encodeSubscribe($packet)];
                break;

            case PacketType::SUBACK:
            case PacketType::UNSUBACK:
                $this->assertInstanceOf($packet, ResultPacket::class);
                [$flags, $body] = [0, $this->encodeResult($packet, $direction)];
                break;

            case PacketType::UNSUBSCRIBE:
                $this->assertInstanceOf($packet, UnsubscribePacket::class);
                [$flags, $body] = [2, $this->encodeUnsubscribe($packet)];
                break;

            case PacketType::PINGREQ:
            case PacketType::PINGRESP:
                $this->assertInstanceOf($packet, EmptyPacket::class);
                [$flags, $body] = [0, ''];
                break;

            case PacketType::DISCONNECT:
                $this->assertInstanceOf($packet, DisconnectPacket::class);
                [$flags, $body] = [0, $this->encodeDisconnect($packet, $direction)];
                break;

            case PacketType::AUTH:
                $this->assertInstanceOf($packet, AuthPacket::class);
                [$flags, $body] = [0, $this->encodeAuth($packet, $direction)];
                break;

            default:
                throw new \InvalidArgumentException('Unsupported MQTT packet type.');
        }

        $header = (new BinaryWriter())
            ->writeByte(($packet->getType() << 4) | $flags)
            ->writeVariableByteInteger(strlen($body))
            ->getBuffer();
        $result = $header . $body;

        if (strlen($result) > $this->maximumOutboundPacketSize) {
            throw new \InvalidArgumentException(sprintf(
                'Encoded packet length [%d] exceeds the negotiated outbound maximum [%d].',
                strlen($result),
                $this->maximumOutboundPacketSize
            ));
        }

        return $result;
    }

    public function decode(
        string $packet,
        string $direction = PropertyCodec::DIRECTION_SERVER_TO_CLIENT
    ): ControlPacket
    {
        $reader    = new BinaryReader($packet);
        $firstByte = $reader->readByte();
        $type      = $firstByte >> 4;
        $flags     = $firstByte & 0x0F;

        if (!PacketType::isValid($type)) {
            throw new MalformedPacketException(sprintf('Reserved MQTT packet type [%d].', $type));
        }

        $this->assertPacketDirection($type, $direction);
        $this->validateFixedHeaderFlags($type, $flags);

        $remainingLength = $reader->readVariableByteInteger();
        if ($remainingLength !== $reader->getRemainingLength()) {
            throw new MalformedPacketException('Remaining Length does not match the packet size.');
        }

        $body = $reader->createLimitedReader($remainingLength);

        try {
            switch ($type) {
                case PacketType::CONNECT:
                    $result = $this->decodeConnect($body);
                    break;

                case PacketType::CONNACK:
                    $result = $this->decodeConnAck($body);
                    break;

                case PacketType::PUBLISH:
                    $result = $this->decodePublish($body, $flags, $direction);
                    break;

                case PacketType::PUBACK:
                case PacketType::PUBREC:
                case PacketType::PUBREL:
                case PacketType::PUBCOMP:
                    $result = $this->decodeAcknowledgement($body, $type, $direction);
                    break;

                case PacketType::SUBSCRIBE:
                    $result = $this->decodeSubscribe($body);
                    break;

                case PacketType::SUBACK:
                case PacketType::UNSUBACK:
                    $result = $this->decodeResult($body, $type, $direction);
                    break;

                case PacketType::UNSUBSCRIBE:
                    $result = $this->decodeUnsubscribe($body);
                    break;

                case PacketType::PINGREQ:
                case PacketType::PINGRESP:
                    if ($body->hasRemaining()) {
                        throw new MalformedPacketException('PING packets must have a zero Remaining Length.');
                    }
                    $result = new EmptyPacket($type);
                    break;

                case PacketType::DISCONNECT:
                    $result = $this->decodeDisconnect($body, $direction);
                    break;

                case PacketType::AUTH:
                    $result = $this->decodeAuth($body, $direction);
                    break;

                default:
                    throw new MalformedPacketException('Unsupported MQTT packet type.');
            }
        } catch (\InvalidArgumentException $exception) {
            throw new ProtocolErrorException($exception->getMessage());
        }

        if ($body->hasRemaining()) {
            throw new MalformedPacketException(sprintf(
                '%s packet contains unexpected trailing bytes.',
                PacketType::name($type)
            ));
        }

        return $result;
    }

    private function encodeConnect(ConnectPacket $packet): string
    {
        $writer = new BinaryWriter();
        $writer->writeUtf8String('MQTT')->writeByte(5);

        $flags = $packet->usesCleanStart() ? 0x02 : 0;
        $will  = $packet->getWill();

        if ($will !== null) {
            $flags |= 0x04 | ($will->getQualityOfService() << 3);
            if ($will->shouldRetain()) {
                $flags |= 0x20;
            }
        }

        if ($packet->getPassword() !== null) {
            $flags |= 0x40;
        }

        if ($packet->getUsername() !== null) {
            $flags |= 0x80;
        }

        $writer
            ->writeByte($flags)
            ->writeUInt16($packet->getKeepAlive())
            ->writeRaw($this->propertyCodec->encode(
                $packet->getProperties(),
                PacketType::CONNECT,
                PropertyCodec::DIRECTION_CLIENT_TO_SERVER
            ))
            ->writeUtf8String($packet->getClientId());

        if ($will !== null) {
            $writer
                ->writeRaw($this->propertyCodec->encode(
                    $will->getProperties(),
                    PropertyCodec::CONTEXT_WILL,
                    PropertyCodec::DIRECTION_CLIENT_TO_SERVER
                ))
                ->writeUtf8String($will->getTopic())
                ->writeBinaryData($will->getPayload());
        }

        if ($packet->getUsername() !== null) {
            $writer->writeUtf8String($packet->getUsername());
        }

        if ($packet->getPassword() !== null) {
            $writer->writeBinaryData($packet->getPassword());
        }

        return $writer->getBuffer();
    }

    private function decodeConnect(BinaryReader $reader): ConnectPacket
    {
        if ($reader->readUtf8String() !== 'MQTT' || $reader->readByte() !== 5) {
            throw new ProtocolErrorException('CONNECT does not declare MQTT protocol level 5.');
        }

        $flags = $reader->readByte();
        if (($flags & 0x01) !== 0
            || (($flags & 0x04) === 0 && ($flags & 0x38) !== 0)
            || (($flags & 0x18) === 0x18)) {
            throw new MalformedPacketException('CONNECT flags are invalid.');
        }

        $keepAlive = $reader->readUInt16();
        $properties = $this->propertyCodec->decode(
            $reader,
            PacketType::CONNECT,
            PropertyCodec::DIRECTION_CLIENT_TO_SERVER
        );
        $clientId = $reader->readUtf8String();
        $will     = null;

        if (($flags & 0x04) !== 0) {
            $willProperties = $this->propertyCodec->decode(
                $reader,
                PropertyCodec::CONTEXT_WILL,
                PropertyCodec::DIRECTION_CLIENT_TO_SERVER
            );
            $will = new Will(
                $reader->readUtf8String(),
                $reader->readBinaryData(),
                ($flags & 0x18) >> 3,
                ($flags & 0x20) !== 0,
                $willProperties
            );
        }

        $username = ($flags & 0x80) !== 0 ? $reader->readUtf8String() : null;
        $password = ($flags & 0x40) !== 0 ? $reader->readBinaryData() : null;

        return new ConnectPacket(
            $clientId,
            ($flags & 0x02) !== 0,
            $keepAlive,
            $properties,
            $will,
            $username,
            $password
        );
    }

    private function encodeConnAck(ConnAckPacket $packet): string
    {
        return (new BinaryWriter())
            ->writeByte($packet->isSessionPresent() ? 1 : 0)
            ->writeByte($packet->getReasonCode())
            ->writeRaw($this->propertyCodec->encode(
                $packet->getProperties(),
                PacketType::CONNACK,
                PropertyCodec::DIRECTION_SERVER_TO_CLIENT
            ))
            ->getBuffer();
    }

    private function decodeConnAck(BinaryReader $reader): ConnAckPacket
    {
        $flags = $reader->readByte();
        if (($flags & 0xFE) !== 0) {
            throw new MalformedPacketException('CONNACK acknowledge flags contain reserved bits.');
        }

        $reasonCode = $reader->readByte();
        $properties = $this->propertyCodec->decode(
            $reader,
            PacketType::CONNACK,
            PropertyCodec::DIRECTION_SERVER_TO_CLIENT
        );

        return new ConnAckPacket(($flags & 0x01) !== 0, $reasonCode, $properties);
    }

    /**
     * @return array{int, string}
     */
    private function encodePublish(PublishPacket $packet, string $direction): array
    {
        $flags = ($packet->getQualityOfService() << 1) | ($packet->shouldRetain() ? 1 : 0);
        if ($packet->isDuplicate()) {
            $flags |= 0x08;
        }

        $writer = (new BinaryWriter())->writeUtf8String($packet->getTopic());
        if ($packet->getPacketIdentifier() !== null) {
            $writer->writeUInt16($packet->getPacketIdentifier());
        }

        $writer
            ->writeRaw($this->propertyCodec->encode($packet->getProperties(), PacketType::PUBLISH, $direction))
            ->writeRaw($packet->getPayload());

        if ($packet->getTopic() === '' && !$packet->getProperties()->has(PropertyIdentifier::TOPIC_ALIAS)) {
            throw new \InvalidArgumentException('An empty PUBLISH topic requires a Topic Alias.');
        }

        return [$flags, $writer->getBuffer()];
    }

    private function decodePublish(BinaryReader $reader, int $flags, string $direction): PublishPacket
    {
        $qualityOfService = ($flags & 0x06) >> 1;
        if ($qualityOfService === 3 || ($qualityOfService === 0 && ($flags & 0x08) !== 0)) {
            throw new MalformedPacketException('PUBLISH QoS and DUP flags are invalid.');
        }

        $topic            = $reader->readUtf8String();
        $packetIdentifier = $qualityOfService > 0 ? $this->readPacketIdentifier($reader) : null;
        $properties       = $this->propertyCodec->decode($reader, PacketType::PUBLISH, $direction);

        if ($topic === '' && !$properties->has(PropertyIdentifier::TOPIC_ALIAS)) {
            throw new ProtocolErrorException('An empty PUBLISH topic requires a Topic Alias.');
        }

        return new PublishPacket(
            $topic,
            $reader->readRemaining(),
            $qualityOfService,
            ($flags & 0x01) !== 0,
            ($flags & 0x08) !== 0,
            $packetIdentifier,
            $properties
        );
    }

    private function encodeAcknowledgement(AcknowledgementPacket $packet, string $direction): string
    {
        $writer = (new BinaryWriter())->writeUInt16($packet->getPacketIdentifier());

        if ($packet->getReasonCode() === ReasonCode::SUCCESS && count($packet->getProperties()) === 0) {
            return $writer->getBuffer();
        }

        $writer->writeByte($packet->getReasonCode());
        if (count($packet->getProperties()) > 0) {
            $writer->writeRaw($this->propertyCodec->encode($packet->getProperties(), $packet->getType(), $direction));
        }

        return $writer->getBuffer();
    }

    private function decodeAcknowledgement(BinaryReader $reader, int $type, string $direction): AcknowledgementPacket
    {
        $packetIdentifier = $this->readPacketIdentifier($reader);
        $reasonCode       = $reader->hasRemaining() ? $reader->readByte() : ReasonCode::SUCCESS;
        $properties       = $reader->hasRemaining()
            ? $this->propertyCodec->decode($reader, $type, $direction)
            : Properties::empty();

        return new AcknowledgementPacket($type, $packetIdentifier, $reasonCode, $properties);
    }

    private function encodeSubscribe(SubscribePacket $packet): string
    {
        $writer = (new BinaryWriter())
            ->writeUInt16($packet->getPacketIdentifier())
            ->writeRaw($this->propertyCodec->encode(
                $packet->getProperties(),
                PacketType::SUBSCRIBE,
                PropertyCodec::DIRECTION_CLIENT_TO_SERVER
            ));

        foreach ($packet->getSubscriptions() as $subscription) {
            $options = $subscription->getQualityOfService()
                | ($subscription->usesNoLocal() ? 0x04 : 0)
                | ($subscription->usesRetainAsPublished() ? 0x08 : 0)
                | ($subscription->getRetainHandling() << 4);
            $writer->writeUtf8String($subscription->getTopicFilter())->writeByte($options);
        }

        return $writer->getBuffer();
    }

    private function decodeSubscribe(BinaryReader $reader): SubscribePacket
    {
        $packetIdentifier = $this->readPacketIdentifier($reader);
        $properties       = $this->propertyCodec->decode(
            $reader,
            PacketType::SUBSCRIBE,
            PropertyCodec::DIRECTION_CLIENT_TO_SERVER
        );
        $subscriptions = [];

        while ($reader->hasRemaining()) {
            $topicFilter = $reader->readUtf8String();
            $options     = $reader->readByte();

            if (($options & 0xC0) !== 0 || ($options & 0x03) === 3 || (($options >> 4) & 0x03) === 3) {
                throw new ProtocolErrorException('SUBSCRIBE options contain a reserved or invalid value.');
            }

            $subscriptions[] = new SubscriptionRequest(
                $topicFilter,
                $options & 0x03,
                ($options & 0x04) !== 0,
                ($options & 0x08) !== 0,
                ($options >> 4) & 0x03
            );
        }

        return new SubscribePacket($packetIdentifier, $subscriptions, $properties);
    }

    private function encodeResult(ResultPacket $packet, string $direction): string
    {
        $writer = (new BinaryWriter())
            ->writeUInt16($packet->getPacketIdentifier())
            ->writeRaw($this->propertyCodec->encode($packet->getProperties(), $packet->getType(), $direction));

        foreach ($packet->getReasonCodes() as $reasonCode) {
            $writer->writeByte($reasonCode);
        }

        return $writer->getBuffer();
    }

    private function decodeResult(BinaryReader $reader, int $type, string $direction): ResultPacket
    {
        $packetIdentifier = $this->readPacketIdentifier($reader);
        $properties       = $this->propertyCodec->decode($reader, $type, $direction);
        $reasonCodes      = [];

        while ($reader->hasRemaining()) {
            $reasonCodes[] = $reader->readByte();
        }

        return new ResultPacket($type, $packetIdentifier, $reasonCodes, $properties);
    }

    private function encodeUnsubscribe(UnsubscribePacket $packet): string
    {
        $writer = (new BinaryWriter())
            ->writeUInt16($packet->getPacketIdentifier())
            ->writeRaw($this->propertyCodec->encode(
                $packet->getProperties(),
                PacketType::UNSUBSCRIBE,
                PropertyCodec::DIRECTION_CLIENT_TO_SERVER
            ));

        foreach ($packet->getTopicFilters() as $topicFilter) {
            $writer->writeUtf8String($topicFilter);
        }

        return $writer->getBuffer();
    }

    private function decodeUnsubscribe(BinaryReader $reader): UnsubscribePacket
    {
        $packetIdentifier = $this->readPacketIdentifier($reader);
        $properties       = $this->propertyCodec->decode(
            $reader,
            PacketType::UNSUBSCRIBE,
            PropertyCodec::DIRECTION_CLIENT_TO_SERVER
        );
        $topicFilters = [];

        while ($reader->hasRemaining()) {
            $topicFilters[] = $reader->readUtf8String();
        }

        return new UnsubscribePacket($packetIdentifier, $topicFilters, $properties);
    }

    private function encodeDisconnect(DisconnectPacket $packet, string $direction): string
    {
        return $this->encodeReasonAndProperties(
            $packet->getReasonCode(),
            $packet->getProperties(),
            PacketType::DISCONNECT,
            $direction
        );
    }

    private function decodeDisconnect(BinaryReader $reader, string $direction): DisconnectPacket
    {
        [$reasonCode, $properties] = $this->decodeReasonAndProperties(
            $reader,
            PacketType::DISCONNECT,
            $direction
        );

        return new DisconnectPacket($reasonCode, $properties);
    }

    private function encodeAuth(AuthPacket $packet, string $direction): string
    {
        return $this->encodeReasonAndProperties(
            $packet->getReasonCode(),
            $packet->getProperties(),
            PacketType::AUTH,
            $direction
        );
    }

    private function decodeAuth(BinaryReader $reader, string $direction): AuthPacket
    {
        [$reasonCode, $properties] = $this->decodeReasonAndProperties($reader, PacketType::AUTH, $direction);

        return new AuthPacket($reasonCode, $properties);
    }

    private function encodeReasonAndProperties(
        int $reasonCode,
        Properties $properties,
        int $type,
        string $direction
    ): string
    {
        if ($reasonCode === ReasonCode::SUCCESS && count($properties) === 0) {
            return '';
        }

        $writer = (new BinaryWriter())->writeByte($reasonCode);
        if (count($properties) > 0) {
            $writer->writeRaw($this->propertyCodec->encode($properties, $type, $direction));
        }

        return $writer->getBuffer();
    }

    /**
     * @return array{int, Properties}
     */
    private function decodeReasonAndProperties(BinaryReader $reader, int $type, string $direction): array
    {
        if (!$reader->hasRemaining()) {
            return [ReasonCode::SUCCESS, Properties::empty()];
        }

        $reasonCode = $reader->readByte();
        $properties = $reader->hasRemaining()
            ? $this->propertyCodec->decode($reader, $type, $direction)
            : Properties::empty();

        return [$reasonCode, $properties];
    }

    private function readPacketIdentifier(BinaryReader $reader): int
    {
        $packetIdentifier = $reader->readUInt16();

        if ($packetIdentifier === 0) {
            throw new ProtocolErrorException('A packet identifier cannot be zero.');
        }

        return $packetIdentifier;
    }

    private function validateFixedHeaderFlags(int $type, int $flags): void
    {
        if ($type === PacketType::PUBLISH) {
            return;
        }

        if ($flags !== PacketType::requiredFlags($type)) {
            throw new MalformedPacketException(sprintf(
                '%s fixed-header flags are invalid.',
                PacketType::name($type)
            ));
        }
    }

    private function assertPacketDirection(int $type, string $direction): void
    {
        $clientPackets = [
            PacketType::CONNECT,
            PacketType::PUBLISH,
            PacketType::PUBACK,
            PacketType::PUBREC,
            PacketType::PUBREL,
            PacketType::PUBCOMP,
            PacketType::SUBSCRIBE,
            PacketType::UNSUBSCRIBE,
            PacketType::PINGREQ,
            PacketType::DISCONNECT,
            PacketType::AUTH,
        ];
        $serverPackets = [
            PacketType::CONNACK,
            PacketType::PUBLISH,
            PacketType::PUBACK,
            PacketType::PUBREC,
            PacketType::PUBREL,
            PacketType::PUBCOMP,
            PacketType::SUBACK,
            PacketType::UNSUBACK,
            PacketType::PINGRESP,
            PacketType::DISCONNECT,
            PacketType::AUTH,
        ];

        $allowed = $direction === PropertyCodec::DIRECTION_CLIENT_TO_SERVER ? $clientPackets : $serverPackets;

        if (!in_array($type, $allowed, true)) {
            throw new ProtocolErrorException(sprintf(
                '%s is not valid in the %s direction.',
                PacketType::name($type),
                $direction
            ));
        }
    }

    private function assertInstanceOf(ControlPacket $packet, string $class): void
    {
        if (!$packet instanceof $class) {
            throw new \InvalidArgumentException(sprintf(
                'Packet type [%s] requires model [%s].',
                PacketType::name($packet->getType()),
                $class
            ));
        }
    }
}
