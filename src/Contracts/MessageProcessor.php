<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Contracts;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\InvalidMessageException;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\Exceptions\ProtocolViolationException;
use PhpMqtt\Client\Message;
use PhpMqtt\Client\Subscription;

/**
 * Implementations of this interface provide message parsing capabilities.
 * Services of this type are used by the {@see MqttClient} to implement multiple protocol versions.
 *
 * @package PhpMqtt\Client\Contracts
 */
interface MessageProcessor
{
    /**
     * Try to parse a message from the incoming buffer. If a message could be parsed successfully,
     * the given message parameter is set to the parsed message and the result is true.
     * If no message could be parsed, the result is false and the required bytes parameter indicates
     * how many bytes are missing for the message to be complete. If this parameter is set to -1,
     * it means we have no (or not yet) knowledge about the required bytes.
     *
     * @param string      $buffer
     * @param int         $bufferLength
     * @param string|null $message
     * @param int         $requiredBytes
     * @return bool
     */
    public function tryFindMessageInBuffer(string $buffer, int $bufferLength, string &$message = null, int &$requiredBytes = -1): bool;

    /**
     * Parses and validates the given message based on its message type and contents.
     * If no valid message could be found in the data, and no further action is required by the caller,
     * null is returned.
     *
     * @param string $message
     * @return Message|null
     * @throws InvalidMessageException
     * @throws ProtocolViolationException
     * @throws MqttClientException
     */
    public function parseAndValidateMessage(string $message): ?Message;

    /**
     * Builds a connect message from the given connection settings, taking the protocol
     * specifics into account.
     *
     * @param ConnectionSettings $connectionSettings
     * @param bool               $useCleanSession
     * @return string
     */
    public function buildConnectMessage(ConnectionSettings $connectionSettings, bool $useCleanSession = false): string;

    /**
     * Builds a ping request message.
     *
     * @return string
     */
    public function buildPingRequestMessage(): string;

    /**
     * Builds a ping response message.
     *
     * @return string
     */
    public function buildPingResponseMessage(): string;

    /**
     * Builds a disconnect message.
     *
     * @return string
     */
    public function buildDisconnectMessage(): string;

    /**
     * Builds a subscribe message from the given parameters.
     *
     * @param int            $messageId
     * @param Subscription[] $subscriptions
     * @param bool           $isDuplicate
     * @return string
     */
    public function buildSubscribeMessage(int $messageId, array $subscriptions, bool $isDuplicate = false): string;

    /**
     * Builds an unsubscribe message from the given parameters.
     *
     * @param int      $messageId
     * @param string[] $topics
     * @param bool     $isDuplicate
     * @return string
     */
    public function buildUnsubscribeMessage(int $messageId, array $topics, bool $isDuplicate = false): string;

    /**
     * Builds a publish message based on the given parameters.
     *
     * @param string   $topic
     * @param string   $message
     * @param int      $qualityOfService
     * @param bool     $retain
     * @param int|null $messageId
     * @param bool     $isDuplicate
     * @return string
     */
    public function buildPublishMessage(
        string $topic,
        string $message,
        int $qualityOfService,
        bool $retain,
        int $messageId = null,
        bool $isDuplicate = false
    ): string;

    /**
     * Builds a publish acknowledgement for the given message identifier.
     *
     * @param int $messageId
     * @return string
     */
    public function buildPublishAcknowledgementMessage(int $messageId): string;

    /**
     * Builds a publish received message for the given message identifier.
     *
     * @param int $messageId
     * @return string
     */
    public function buildPublishReceivedMessage(int $messageId): string;

    /**
     * Builds a publish release message for the given message identifier.
     *
     * @param int $messageId
     * @return string
     */
    public function buildPublishReleaseMessage(int $messageId): string;

    /**
     * Builds a publish complete message for the given message identifier.
     *
     * @param int $messageId
     * @return string
     */
    public function buildPublishCompleteMessage(int $messageId): string;

    /**
     * Handles the connect acknowledgement received from the broker. Exits normally if the
     * connection could be established successfully according to the response. Throws an
     * exception if the broker responded with an error.
     *
     * @param string $message
     * @throws ConnectingToBrokerFailedException
     */
    public function handleConnectAcknowledgement(string $message): void;
}
