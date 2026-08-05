<?php

declare(strict_types=1);

namespace PhpMqtt\Client\MessageProcessors;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\Mqtt5MessageProcessor as Mqtt5MessageProcessorContract;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\InvalidMessageException;
use PhpMqtt\Client\Exceptions\ProtocolErrorException;
use PhpMqtt\Client\Message;
use PhpMqtt\Client\MessageType;
use PhpMqtt\Client\Mqtt5\AuthenticationEvent;
use PhpMqtt\Client\Mqtt5\AuthenticationOptions;
use PhpMqtt\Client\Mqtt5\ConnectionOptions;
use PhpMqtt\Client\Mqtt5\ConnectionResult;
use PhpMqtt\Client\Mqtt5\DisconnectOptions;
use PhpMqtt\Client\Mqtt5\PublishOptions;
use PhpMqtt\Client\Mqtt5\SubscribeOptions;
use PhpMqtt\Client\Mqtt5\UnsubscribeOptions;
use PhpMqtt\Client\Mqtt5Message;
use PhpMqtt\Client\Protocol\Mqtt5\PacketCodec;
use PhpMqtt\Client\Protocol\Mqtt5\PropertyCodec;
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
use PhpMqtt\Client\Protocol\ReasonCode;
use PhpMqtt\Client\Protocol\Wire\PacketFramer;
use PhpMqtt\Client\Subscription;
use Psr\Log\LoggerInterface;

/**
 * MQTT 5 compatibility processor backed by the typed packet codec.
 *
 * @package PhpMqtt\Client\MessageProcessors
 */
class Mqtt5MessageProcessor extends BaseMessageProcessor implements Mqtt5MessageProcessorContract
{
    private PacketFramer $framer;
    private PacketCodec $codec;
    private ?ConnectionOptions $connectionOptions = null;
    private ?ConnectionResult $connectionResult    = null;
    private ?ControlPacket $lastPacket             = null;

    public function __construct(private string $clientId, LoggerInterface $logger)
    {
        parent::__construct($logger);

        $this->framer = new PacketFramer();
        $this->codec  = new PacketCodec();
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function setConnectionOptions(?ConnectionOptions $options): void
    {
        $this->connectionOptions = $options;
        $this->connectionResult  = null;
        $this->lastPacket        = null;

        $maximumPacketSize = $options?->getProperties()->get(PropertyIdentifier::MAXIMUM_PACKET_SIZE);
        $this->framer->setMaximumPacketSize($maximumPacketSize ?? PacketFramer::MAXIMUM_PACKET_SIZE);
        $this->codec->setMaximumOutboundPacketSize(PacketFramer::MAXIMUM_PACKET_SIZE);
    }

    public function tryFindMessageInBuffer(
        string $buffer,
        int $bufferLength,
        ?string &$message = null,
        int &$requiredBytes = -1
    ): bool
    {
        if ($bufferLength !== strlen($buffer)) {
            $buffer = substr($buffer, 0, $bufferLength);
        }

        return $this->framer->tryExtract($buffer, $message, $requiredBytes);
    }

    public function buildConnectMessage(ConnectionSettings $connectionSettings, bool $useCleanSession = false): string
    {
        $properties     = $this->connectionOptions?->getProperties() ?? Properties::empty();
        $authentication = $this->connectionOptions?->getAuthentication();

        if ($authentication !== null) {
            $properties = $this->without($properties, [
                PropertyIdentifier::AUTHENTICATION_METHOD,
                PropertyIdentifier::AUTHENTICATION_DATA,
            ])->with(PropertyIdentifier::AUTHENTICATION_METHOD, $authentication->getMethod());

            if ($authentication->getData() !== null) {
                $properties = $properties->with(PropertyIdentifier::AUTHENTICATION_DATA, $authentication->getData());
            }
        }

        $willOptions = $this->connectionOptions?->getWill();
        if ($willOptions !== null) {
            $will = new Will(
                $willOptions->getTopic(),
                $willOptions->getPayload(),
                $willOptions->getQualityOfService(),
                $willOptions->shouldRetain(),
                $willOptions->getProperties()
            );
        } elseif ($connectionSettings->hasLastWill()) {
            $will = new Will(
                $connectionSettings->getLastWillTopic(),
                $connectionSettings->getLastWillMessage(),
                $connectionSettings->getLastWillQualityOfService(),
                $connectionSettings->shouldRetainLastWill(),
                Properties::empty()
            );
        } else {
            $will = null;
        }

        return $this->codec->encode(new ConnectPacket(
            $this->clientId,
            $useCleanSession,
            $connectionSettings->getKeepAliveInterval(),
            $properties,
            $will,
            $connectionSettings->getUsername(),
            $connectionSettings->getPassword()
        ));
    }

    public function buildPingRequestMessage(): string
    {
        return $this->codec->encode(new EmptyPacket(PacketType::PINGREQ));
    }

    public function buildPingResponseMessage(): string
    {
        return $this->codec->encode(
            new EmptyPacket(PacketType::PINGRESP),
            PropertyCodec::DIRECTION_SERVER_TO_CLIENT
        );
    }

    public function buildDisconnectMessage(): string
    {
        return $this->buildDisconnectMessageWithOptions(new DisconnectOptions());
    }

    public function buildDisconnectMessageWithOptions(DisconnectOptions $options): string
    {
        return $this->codec->encode(new DisconnectPacket($options->getReasonCode(), $options->getProperties()));
    }

    public function buildSubscribeMessage(int $messageId, array $subscriptions, bool $isDuplicate = false): string
    {
        $options = [];
        foreach ($subscriptions as $subscription) {
            if (!$subscription instanceof Subscription) {
                throw new \InvalidArgumentException('Invalid legacy subscription.');
            }

            $options[] = new \PhpMqtt\Client\Mqtt5\SubscriptionOptions(
                $subscription->getTopicFilter(),
                $subscription->getQualityOfServiceLevel(),
                $subscription->getCallback()
            );
        }

        return $this->buildSubscribeMessageWithOptions($messageId, new SubscribeOptions($options));
    }

    public function buildSubscribeMessageWithOptions(int $messageId, SubscribeOptions $options): string
    {
        $subscriptions = array_map(
            static fn (\PhpMqtt\Client\Mqtt5\SubscriptionOptions $subscription): SubscriptionRequest =>
                $subscription->toPacketSubscription(),
            $options->getSubscriptions()
        );

        return $this->codec->encode(new SubscribePacket($messageId, $subscriptions, $options->getProperties()));
    }

    public function buildUnsubscribeMessage(int $messageId, array $topics, bool $isDuplicate = false): string
    {
        return $this->buildUnsubscribeMessageWithOptions($messageId, new UnsubscribeOptions($topics));
    }

    public function buildUnsubscribeMessageWithOptions(int $messageId, UnsubscribeOptions $options): string
    {
        return $this->codec->encode(new UnsubscribePacket(
            $messageId,
            $options->getTopicFilters(),
            $options->getProperties()
        ));
    }

    public function buildPublishMessage(
        string $topic,
        string $message,
        int $qualityOfService,
        bool $retain,
        ?int $messageId = null,
        bool $isDuplicate = false
    ): string
    {
        return $this->buildPublishMessageWithOptions(
            $topic,
            $message,
            $qualityOfService,
            $retain,
            $messageId,
            new PublishOptions(),
            $isDuplicate
        );
    }

    public function buildPublishMessageWithOptions(
        string $topic,
        string $message,
        int $qualityOfService,
        bool $retain,
        ?int $messageId,
        PublishOptions $options,
        bool $isDuplicate = false
    ): string
    {
        return $this->codec->encode(new PublishPacket(
            $topic,
            $message,
            $qualityOfService,
            $retain,
            $isDuplicate,
            $qualityOfService > 0 ? $messageId : null,
            $options->getProperties()
        ));
    }

    public function buildPublishAcknowledgementMessage(int $messageId): string
    {
        return $this->buildAcknowledgement(PacketType::PUBACK, $messageId);
    }

    public function buildPublishReceivedMessage(int $messageId): string
    {
        return $this->buildAcknowledgement(PacketType::PUBREC, $messageId);
    }

    public function buildPublishReleaseMessage(int $messageId): string
    {
        return $this->buildAcknowledgement(PacketType::PUBREL, $messageId);
    }

    public function buildPublishCompleteMessage(int $messageId): string
    {
        return $this->buildAcknowledgement(PacketType::PUBCOMP, $messageId);
    }

    private function buildAcknowledgement(int $type, int $messageId, int $reasonCode = ReasonCode::SUCCESS): string
    {
        return $this->codec->encode(new AcknowledgementPacket(
            $type,
            $messageId,
            $reasonCode,
            Properties::empty()
        ));
    }

    public function parseAndValidateMessage(string $message): ?Message
    {
        $packet           = $this->codec->decode($message);
        $this->lastPacket = $packet;

        switch ($packet->getType()) {
            case PacketType::PUBLISH:
                /** @var PublishPacket $packet */
                return (new Mqtt5Message(
                    MessageType::PUBLISH(),
                    $packet,
                    $packet->getQualityOfService(),
                    $packet->shouldRetain()
                ))
                    ->setMessageId($packet->getPacketIdentifier())
                    ->setTopic($packet->getTopic())
                    ->setContent($packet->getPayload());

            case PacketType::PUBACK:
                return $this->acknowledgementMessage(MessageType::PUBLISH_ACKNOWLEDGEMENT(), $packet);

            case PacketType::PUBREC:
                return $this->acknowledgementMessage(MessageType::PUBLISH_RECEIPT(), $packet);

            case PacketType::PUBREL:
                return $this->acknowledgementMessage(MessageType::PUBLISH_RELEASE(), $packet);

            case PacketType::PUBCOMP:
                return $this->acknowledgementMessage(MessageType::PUBLISH_COMPLETE(), $packet);

            case PacketType::SUBACK:
                /** @var ResultPacket $packet */
                return (new Mqtt5Message(MessageType::SUBSCRIBE_ACKNOWLEDGEMENT(), $packet))
                    ->setMessageId($packet->getPacketIdentifier())
                    ->setAcknowledgedQualityOfServices($packet->getReasonCodes());

            case PacketType::UNSUBACK:
                /** @var ResultPacket $packet */
                return (new Mqtt5Message(MessageType::UNSUBSCRIBE_ACKNOWLEDGEMENT(), $packet))
                    ->setMessageId($packet->getPacketIdentifier())
                    ->setAcknowledgedQualityOfServices($packet->getReasonCodes());

            case PacketType::PINGRESP:
                return new Mqtt5Message(MessageType::PING_RESPONSE(), $packet);

            case PacketType::DISCONNECT:
                return new Mqtt5Message(MessageType::DISCONNECT(), $packet);

            case PacketType::AUTH:
                return new Mqtt5Message(MessageType::AUTHENTICATION(), $packet);

            default:
                throw new ProtocolErrorException(sprintf(
                    'Unexpected %s packet received from the broker.',
                    PacketType::name($packet->getType())
                ));
        }
    }

    private function acknowledgementMessage(MessageType $type, ControlPacket $packet): Mqtt5Message
    {
        if (!$packet instanceof AcknowledgementPacket) {
            throw new InvalidMessageException('Expected a publish acknowledgement packet.');
        }

        return (new Mqtt5Message($type, $packet))->setMessageId($packet->getPacketIdentifier());
    }

    public function handleConnectAcknowledgement(string $message): void
    {
        $response = $this->processConnectionHandshake($message);

        if ($response !== null || $this->connectionResult === null) {
            throw new ConnectingToBrokerFailedException(
                ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_FAILED,
                'The broker requested enhanced authentication outside the MQTT 5 handshake flow.'
            );
        }
    }

    public function processConnectionHandshake(string $message): ?string
    {
        $packet           = $this->codec->decode($message);
        $this->lastPacket = $packet;

        if ($packet instanceof AuthPacket) {
            return $this->respondToAuthentication();
        }

        if (!$packet instanceof ConnAckPacket) {
            throw new ConnectingToBrokerFailedException(
                ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_FAILED,
                sprintf('Expected CONNACK or AUTH; received %s.', PacketType::name($packet->getType()))
            );
        }

        $this->connectionResult = new ConnectionResult(
            $packet->isSessionPresent(),
            $packet->getReasonCode(),
            $packet->getProperties()
        );

        if (ReasonCode::isError($packet->getReasonCode())) {
            throw $this->connectionFailure($packet->getReasonCode());
        }

        $this->codec->setMaximumOutboundPacketSize(
            $this->connectionResult->getCapabilities()->getMaximumPacketSize()
        );

        return null;
    }

    public function getConnectionResult(): ?ConnectionResult
    {
        return $this->connectionResult;
    }

    public function getLastPacket(): ?ControlPacket
    {
        return $this->lastPacket;
    }

    public function buildAuthenticationMessage(AuthenticationOptions $options, int $reasonCode): string
    {
        $properties = $this->without($options->getProperties(), [
            PropertyIdentifier::AUTHENTICATION_METHOD,
            PropertyIdentifier::AUTHENTICATION_DATA,
        ])->with(PropertyIdentifier::AUTHENTICATION_METHOD, $options->getMethod());

        if ($options->getData() !== null) {
            $properties = $properties->with(PropertyIdentifier::AUTHENTICATION_DATA, $options->getData());
        }

        return $this->codec->encode(new AuthPacket($reasonCode, $properties));
    }

    public function respondToAuthentication(): ?string
    {
        if (!$this->lastPacket instanceof AuthPacket) {
            return null;
        }

        $handler = $this->connectionOptions?->getAuthenticationHandler();
        if ($handler === null) {
            throw new ProtocolErrorException('The broker requested enhanced authentication without a configured handler.');
        }

        $response = $handler->respond(new AuthenticationEvent(
            $this->lastPacket->getReasonCode(),
            $this->lastPacket->getProperties()
        ));

        if ($response === null) {
            return null;
        }

        return $this->buildAuthenticationMessage($response, ReasonCode::CONTINUE_AUTHENTICATION);
    }

    private function connectionFailure(int $reasonCode): ConnectingToBrokerFailedException
    {
        $exceptionCode = ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_FAILED;

        if ($reasonCode === ReasonCode::UNSUPPORTED_PROTOCOL_VERSION) {
            $exceptionCode = ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_PROTOCOL_VERSION;
        } elseif ($reasonCode === ReasonCode::CLIENT_IDENTIFIER_NOT_VALID) {
            $exceptionCode = ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_IDENTIFIER_REJECTED;
        } elseif (in_array($reasonCode, [ReasonCode::SERVER_UNAVAILABLE, ReasonCode::SERVER_BUSY], true)) {
            $exceptionCode = ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_BROKER_UNAVAILABLE;
        } elseif ($reasonCode === ReasonCode::BAD_USER_NAME_OR_PASSWORD) {
            $exceptionCode = ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_INVALID_CREDENTIALS;
        } elseif ($reasonCode === ReasonCode::NOT_AUTHORIZED) {
            $exceptionCode = ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_UNAUTHORIZED;
        }

        return new ConnectingToBrokerFailedException(
            $exceptionCode,
            sprintf('The MQTT 5 broker rejected the connection with reason code [0x%02X].', $reasonCode)
        );
    }

    /**
     * @param int[] $identifiers
     */
    private function without(Properties $properties, array $identifiers): Properties
    {
        $result = Properties::empty();

        foreach ($properties as $property) {
            if (!in_array($property->getIdentifier(), $identifiers, true)) {
                $result = $result->withProperty($property);
            }
        }

        return $result;
    }
}
