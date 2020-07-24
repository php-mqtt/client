<?php

declare(strict_types=1);

namespace PhpMqtt\Client\MessageProcessors;

use PhpMqtt\Client\Concerns\TranscodesData;
use PhpMqtt\Client\Concerns\WorksWithBuffers;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\MessageProcessor;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\Exceptions\PendingPublishConfirmationAlreadyExistsException;
use PhpMqtt\Client\Exceptions\UnexpectedAcknowledgementException;
use Psr\Log\LoggerInterface;

/**
 * This message processor implements the MQTT protocol version 3.
 *
 * @package PhpMqtt\Client\MessageProcessors
 */
class Mqtt31MessageProcessor implements MessageProcessor
{
    use TranscodesData,
        WorksWithBuffers;

    const QOS_AT_MOST_ONCE  = 0;
    const QOS_AT_LEAST_ONCE = 1;
    const QOS_EXACTLY_ONCE  = 2;

    /** @var MqttClient */
    private $client;

    /** @var LoggerInterface */
    private $logger;

    /**
     * Mqtt3MessageProcessor constructor.
     *
     * @param MqttClient      $client
     * @param LoggerInterface $logger
     */
    public function __construct(MqttClient $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * {@inheritDoc}
     */
    public function tryParseMessage(string $buffer, int $bufferLength, string &$message = null, int &$requiredBytes = -1): bool
    {
        // If we received no input, we can return immediately without doing work.
        if ($bufferLength === 0) {
            return false;
        }

        // If we received not at least the fixed header with one length indicating byte,
        // we know that there can't be a valid message in the buffer. So we return early.
        if ($bufferLength < 2) {
            return false;
        }

        // Read the second byte of the message to get the remaining length.
        // If the continuation bit (8) is set on the length byte, another byte will be read as length.
        $byteIndex       = 1;
        $remainingLength = 0;
        $multiplier      = 1;
        do {
            // If the buffer has no more data, but we need to read more for the length header,
            // we cannot give useful information about the remaining length and exit early.
            if ($byteIndex + 1 > $bufferLength) {
                return false;
            }

            // Otherwise, we can take seven bits to calculate the length and the remaining eighth bit
            // as continuation bit.
            $digit            = ord($buffer[$byteIndex]);
            $remainingLength += ($digit & 127) * $multiplier;
            $multiplier      *= 128;
            $byteIndex++;
        } while (($digit & 128) !== 0);

        // At this point, we can now tell whether the remaining length amount of bytes are available
        // or not. If not, we return the amount of bytes required for the message to be complete.
        $requiredBufferLength = $byteIndex + 1 + $remainingLength;
        if ($requiredBufferLength > $bufferLength) {
            $requiredBytes = $requiredBufferLength;
            return false;
        }

        // Now that we have a full message in the buffer, we can set the output and return.
        $message = substr($buffer, $byteIndex, $remainingLength);
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function handleMessage(string $message): void
    {
        $result = $this->tryDecodeMessage($message, $command, $qualityOfService, $data);

        if ($result === false) {
            // TODO: throw invalid message exception
            throw new \Exception('Invalid message');
        }

        // Ensure the command is supported by this version of the protocol.
        if ($command <= 0 || $command > 15) {
            $this->logger->error('Reserved command received from the broker. Supported are commands (including) 1-14.', [
                'command' => $command,
            ]);
            return;
        }

        // Then handle the command accordingly.
        switch($command){
            case 2:
                throw new UnexpectedAcknowledgementException(
                    UnexpectedAcknowledgementException::EXCEPTION_ACK_CONNECT,
                    'We unexpectedly received a connection acknowledgement.'
                );
            case 3:
                $this->handlePublishedMessage($data, $qualityOfService);
                break;
            case 4:
                $this->handlePublishAcknowledgement($data);
                break;
            case 5:
                $this->handlePublishReceipt($data);
                break;
            case 6:
                $this->handlePublishRelease($data);
                break;
            case 7:
                $this->handlePublishCompletion($data);
                break;
            case 9:
                $this->handleSubscribeAcknowledgement($data);
                break;
            case 11:
                $this->handleUnsubscribeAcknowledgement($data);
                break;
            case 12:
                $this->handlePingRequest();
                break;
            case 13;
                $this->handlePingAcknowledgement();
                break;
            default:
                $this->logger->debug('Received message with unsupported command [{command}]. Skipping.', ['command' => $command]);
                break;
        }
    }

    /**
     * Attempt to decode the given message. If successful, the result is true and the reference
     * parameters are set accordingly. Otherwise, false is returned and the reference parameters
     * remain untouched.
     *
     * @param string      $message
     * @param int|null    $command
     * @param int|null    $qualityOfService
     * @param string|null $data
     * @return bool
     */
    protected function tryDecodeMessage(string $message, int &$command = null, int &$qualityOfService = null, string &$data = null): bool
    {
        // If we received no input, we can return immediately without doing work.
        if (strlen($message) === 0) {
            return false;
        }

        // If we received not at least the fixed header with one length indicating byte,
        // we know that there can't be a valid message in the buffer. So we return early.
        if (strlen($message) < 2) {
            return false;
        }

        // Read the first byte of a message (command and flags).
        $byte             = $message[0];
        $command          = (int)(ord($byte) / 16);
        $qualityOfService = (ord($byte) & 0x06) >> 1;

        // Read the second byte of a message (remaining length).
        // If the continuation bit (8) is set on the length byte, another byte will be read as length.
        $byteIndex       = 1;
        $remainingLength = 0;
        $multiplier      = 1;
        do {
            // If the buffer has no more data, but we need to read more for the length header,
            // we cannot give useful information about the remaining length and exit early.
            if ($byteIndex + 1 > strlen($message)) {
                return false;
            }

            // Otherwise, we can take seven bits to calculate the length and the remaining eighth bit
            // as continuation bit.
            $digit            = ord($message[$byteIndex]);
            $remainingLength += ($digit & 127) * $multiplier;
            $multiplier      *= 128;
            $byteIndex++;
        } while (($digit & 128) !== 0);

        // At this point, we can now tell whether the remaining length amount of bytes are available
        // or not. If not, the message is incomplete.
        $requiredBytes = $byteIndex + 1 + $remainingLength;
        if ($requiredBytes > strlen($message)) {
            return false;
        }

        // Set the output data based on the calculated bytes.
        $data = substr($message, $byteIndex, $remainingLength);

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function buildConnectMessage(ConnectionSettings $connectionSettings, bool $useCleanSession = false): string
    {
        $i = 0;
        $buffer = '';

        // The protocol name and version.
        $buffer .= chr(0x00); $i++; // length of protocol name 1
        $buffer .= chr(0x06); $i++; // length of protocol name 2
        $buffer .= chr(0x4d); $i++; // protocol name: M
        $buffer .= chr(0x51); $i++; // protocol name: Q
        $buffer .= chr(0x49); $i++; // protocol name: I
        $buffer .= chr(0x73); $i++; // protocol name: s
        $buffer .= chr(0x64); $i++; // protocol name: d
        $buffer .= chr(0x70); $i++; // protocol name: p
        $buffer .= chr(0x03); $i++; // protocol version (3)

        // Build connection flags based on the connection settings.
        $flags   = $this->buildConnectionFlags($connectionSettings, $useCleanSession);
        $buffer .= chr($flags); $i++;

        // Encode and add the keep alive interval.
        $buffer .= chr($connectionSettings->getKeepAliveInterval() >> 8); $i++;
        $buffer .= chr($connectionSettings->getKeepAliveInterval() & 0xff); $i++;

        // Encode and add the client identifier.
        $clientIdPart = $this->buildLengthPrefixedString($this->client->getClientId());
        $buffer      .= $clientIdPart;
        $i           += strlen($clientIdPart);

        // Encode and add the last will topic and message, if configured.
        if ($connectionSettings->hasLastWill()) {
            $topicPart = $this->buildLengthPrefixedString($connectionSettings->getLastWillTopic());
            $buffer   .= $topicPart;
            $i        += strlen($topicPart);

            $messagePart = $this->buildLengthPrefixedString($connectionSettings->getLastWillMessage());
            $buffer     .= $messagePart;
            $i          += strlen($messagePart);
        }

        // Encode and add the credentials, if configured.
        if ($connectionSettings->getUsername() !== null) {
            $usernamePart = $this->buildLengthPrefixedString($connectionSettings->getUsername());
            $buffer      .= $usernamePart;
            $i           += strlen($usernamePart);
        }
        if ($connectionSettings->getPassword() !== null) {
            $passwordPart = $this->buildLengthPrefixedString($connectionSettings->getPassword());
            $buffer      .= $passwordPart;
            $i           += strlen($passwordPart);
        }

        // The header consists of the message type 0x10 and the length.
        $header = chr(0x10) . chr($i);

        return $header . $buffer;
    }

    /**
     * Builds the connection flags from the inputs and settings.
     *
     * @param ConnectionSettings $connectionSettings
     * @param bool               $useCleanSession
     * @return int
     */
    protected function buildConnectionFlags(ConnectionSettings $connectionSettings, bool $useCleanSession = false): int
    {
        $flags = 0;

        if ($useCleanSession) {
            $this->logger->debug('Using the [clean session] flag for the connection.');
            $flags += 1 << 1; // set the `clean session` flag
        }

        if ($connectionSettings->hasLastWill()) {
            $this->logger->debug('Using the [will] flag for the connection.');
            $flags += 1 << 2; // set the `will` flag

            if ($$connectionSettings->getQualityOfService() > self::QOS_AT_MOST_ONCE) {
                $this->logger->debug('Using QoS level [{qos}] for the connection.', ['qos' => $$connectionSettings->getQualityOfService()]);
                $flags += $$connectionSettings->getQualityOfService() << 3; // set the `qos` bits
            }

            if ($$connectionSettings->shouldRetain()) {
                $this->logger->debug('Using the [retain] flag for the connection.');
                $flags += 1 << 5; // set the `retain` flag
            }
        }

        if ($connectionSettings->getPassword() !== null) {
            $this->logger->debug('Using the [password] flag for the connection.');
            $flags += 1 << 6; // set the `has password` flag
        }

        if ($connectionSettings->getUsername() !== null) {
            $this->logger->debug('Using the [username] flag for the connection.');
            $flags += 1 << 7; // set the `has username` flag
        }

        return $flags;
    }

    /**
     * {@inheritDoc}
     */
    public function handleConnectAcknowledgement(string $message): void
    {
        if (strlen($message) !== 4 || ($messageType = ord($message[0]) >> 4) !== 2) {
            $this->logger->error('Expected connect acknowledgement; received a different response else.', ['messageType' => $messageType ?? null]);

            throw new ConnectingToBrokerFailedException(
                ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_FAILED,
                'A connection could not be established. Expected connect acknowledgement; received a different response else.'
            );
        }

        $errorCode = ord($message[3]);
        $logContext = ['errorCode' => sprintf('0x%02X', $errorCode)];

        switch ($errorCode) {
            case 0x00:
                $this->logger->info('Connection with broker established successfully.', $logContext);
                break;

            case 0x01:
                $this->logger->error('The broker does not support MQTT v3.1.', $logContext);
                throw new ConnectingToBrokerFailedException(
                    ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_PROTOCOL_VERSION,
                    'The configured broker does not support MQTT v3.1.'
                );

            case 0x02:
                $this->logger->error('The broker rejected the sent identifier.', $logContext);
                throw new ConnectingToBrokerFailedException(
                    ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_IDENTIFIER_REJECTED,
                    'The configured broker rejected the sent identifier.'
                );

            case 0x03:
                $this->logger->error('The broker is currently unavailable.', $logContext);
                throw new ConnectingToBrokerFailedException(
                    ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_BROKER_UNAVAILABLE,
                    'The configured broker is currently unavailable.'
                );

            case 0x04:
                $this->logger->error('The broker reported the credentials as invalid.', $logContext);
                throw new ConnectingToBrokerFailedException(
                    ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_INVALID_CREDENTIALS,
                    'The configured broker reported the credentials as invalid.'
                );

            case 0x05:
                $this->logger->error('The broker responded with unauthorized.', $logContext);
                throw new ConnectingToBrokerFailedException(
                    ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_UNAUTHORIZED,
                    'The configured broker responded with unauthorized.'
                );

            default:
                $this->logger->error('The broker responded with an invalid error code [{errorCode}].', $logContext);
                throw new ConnectingToBrokerFailedException(
                    ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_FAILED,
                    'The configured broker responded with an invalid error code. A connection could not be established.'
                );
        }
    }

    /**
     * Builds a ping message.
     *
     * @return string
     */
    public function buildPingMessage(): string
    {
        return chr(0xc0) . chr(0x00);
    }

    /**
     * Builds a disconnect message.
     *
     * @return string
     */
    public function buildDisconnectMessage(): string
    {
        return chr(0xe0) . chr(0x00);
    }

    /**
     * {@inheritDoc}
     */
    public function buildSubscribeMessage(int $messageId, string $topic, int $qualityOfService): string
    {
        // Encode the message id, it always consists of two bytes.
        $buffer = $this->encodeMessageId($messageId);
        $i      = 2;

        // Encode the topic as length prefixed string.
        $topicPart = $this->buildLengthPrefixedString($topic);
        $buffer   .= $topicPart;
        $i        += strlen($topicPart);

        // Encode the quality of service level.
        $buffer   .= chr($qualityOfService); $i++;

        // The header consists of the message type 0x82 and the length.
        $header  = chr(0x82) . chr($i);

        return $header . $buffer;
    }

    /**
     * {@inheritDoc}
     */
    public function buildUnsubscribeMessage(int $messageId, string $topic, bool $isDuplicate = false): string
    {
        // Encode the message id, it always consists of two bytes.
        $buffer = $this->encodeMessageId($messageId);
        $i      = 2;

        // Encode the topic as length prefixed string.
        $topicPart = $this->buildLengthPrefixedString($topic);
        $buffer   .= $topicPart;
        $i        += strlen($topicPart);

        // The header consists of the message type 0xa2 and the length.
        // Additionally, the first byte may contain the duplicate flag.
        $command = 0xa2 | ($isDuplicate ? 1 << 3 : 0);
        $header  = chr($command) . chr($i);

        return $header . $buffer;
    }

    /**
     * {@inheritDoc}
     */
    public function buildPublishMessage(
        string $topic,
        string $message,
        int $qualityOfService,
        bool $retain,
        int $messageId = null,
        bool $isDuplicate = false
    ): string
    {
        // Encode the topic as length prefixed string.
        $topicPart = $this->buildLengthPrefixedString($topic);
        $buffer    = $topicPart;
        $i         = strlen($topicPart);

        // Encode the message id, if given. It always consists of two bytes.
        if ($messageId !== null)
        {
            $buffer .= $this->encodeMessageId($messageId);
            $i      += 2;
        }

        // Add the message without encoding.
        $buffer .= $message;
        $i      += strlen($message);

        // Encode the command with supported flags.
        $command = 0x30;
        if ($retain) {
            $command += 1 << 0;
        }
        if ($qualityOfService > self::QOS_AT_MOST_ONCE) {
            $command += $qualityOfService << 1;
        }
        if ($isDuplicate) {
            $command += 1 << 3;
        }

        // Build the header from the command and the encoded message length.
        $header = chr($command) . $this->encodeMessageLength($i);

        return $header . $buffer;
    }

    /**
     * {@inheritDoc}
     */
    public function buildPublishAcknowledgementMessage(int $messageId): string
    {
        return chr(0x40) . chr(0x02) . $this->encodeMessageId($messageId);
    }

    /**
     * {@inheritDoc}
     */
    public function buildPublishReceivedMessage(int $messageId): string
    {
        return chr(0x50) . chr(0x02) . $this->encodeMessageId($messageId);
    }

    /**
     * {@inheritDoc}
     */
    public function buildPublishCompleteMessage(int $messageId): string
    {
        return chr(0x70) . chr(0x02) . $this->encodeMessageId($messageId);
    }

    /**
     * Handles a received message. The buffer contains the whole message except
     * command and length. The message structure is:
     *
     *   [topic-length:topic:message]+
     *
     * @param string $buffer
     * @param int    $qualityOfServiceLevel
     * @return void
     * @throws DataTransferException
     */
    protected function handlePublishedMessage(string $buffer, int $qualityOfServiceLevel): void
    {
        $topicLength = (ord($buffer[0]) << 8) + ord($buffer[1]);
        $topic       = substr($buffer, 2, $topicLength);
        $message     = substr($buffer, ($topicLength + 2));

        if ($qualityOfServiceLevel > self::QOS_AT_MOST_ONCE) {
            if (strlen($message) < 2) {
                $this->logger->error('Received a message with QoS level [{qos}] without message identifier.', [
                    'qos' => $qualityOfServiceLevel,
                ]);

                // This message seems to be incomplete or damaged. We ignore it and wait for a retransmission,
                // which will occur at some point due to QoS level > 0.
                return;
            }

            $messageId = $this->decodeMessageId($this->pop($message, 2));

            if ($qualityOfServiceLevel === self::QOS_AT_LEAST_ONCE) {
                $this->sendPublishAcknowledgement($messageId);
            }

            if ($qualityOfServiceLevel === self::QOS_EXACTLY_ONCE) {
                try {
                    $this->sendPublishReceived($messageId);
                    $this->repository->addNewPendingPublishConfirmation($messageId, $topic, $message);
                } catch (PendingPublishConfirmationAlreadyExistsException $e) {
                    // We already received and processed this message, therefore we do not respond
                    // with a receipt a second time and wait for the release instead.
                }
                // We only deliver this published message as soon as we receive a publish complete.
                return;
            }
        }

        $this->deliverPublishedMessage($topic, $message, $qualityOfServiceLevel);
    }

    /**
     * Handles a received publish acknowledgement. The buffer contains the whole
     * message except command and length. The message structure is:
     *
     *   [message-identifier]
     *
     * @param string $buffer
     * @return void
     * @throws UnexpectedAcknowledgementException
     */
    protected function handlePublishAcknowledgement(string $buffer): void
    {
        $this->logger->debug('Handling publish acknowledgement received from the broker.');

        if (strlen($buffer) !== 2) {
            $this->logger->notice('Received invalid publish acknowledgement from the broker.');
            throw new UnexpectedAcknowledgementException(
                UnexpectedAcknowledgementException::EXCEPTION_ACK_PUBLISH,
                'The MQTT broker responded with an invalid publish acknowledgement.'
            );
        }

        $messageId = $this->decodeMessageId($this->pop($buffer, 2));

        $result = $this->repository->removePendingPublishedMessage($messageId);
        if ($result === false) {
            $this->logger->notice('Received publish acknowledgement from the broker for already acknowledged message.');
            throw new UnexpectedAcknowledgementException(
                UnexpectedAcknowledgementException::EXCEPTION_ACK_PUBLISH,
                'The MQTT broker acknowledged a publish that has not been pending anymore.'
            );
        }

        $this->repository->releaseMessageId($messageId);
    }

    /**
     * Handles a received publish receipt. The buffer contains the whole
     * message except command and length. The message structure is:
     *
     *   [message-identifier]
     *
     * @param string $buffer
     * @return void
     * @throws UnexpectedAcknowledgementException
     */
    protected function handlePublishReceipt(string $buffer): void
    {
        $this->logger->debug('Handling publish receipt from the broker.');

        if (strlen($buffer) !== 2) {
            $this->logger->notice('Received invalid publish receipt from the broker.');
            throw new UnexpectedAcknowledgementException(
                UnexpectedAcknowledgementException::EXCEPTION_ACK_RECEIVE,
                'The MQTT broker responded with an invalid publish receipt.'
            );
        }

        $messageId = $this->decodeMessageId($this->pop($buffer, 2));

        $result = $this->repository->markPendingPublishedMessageAsReceived($messageId);
        if ($result === false) {
            $this->logger->notice('Received publish receipt from the broker for already acknowledged message.');
            throw new UnexpectedAcknowledgementException(
                UnexpectedAcknowledgementException::EXCEPTION_ACK_RECEIVE,
                'The MQTT broker sent a receipt for a publish that has not been pending anymore.'
            );
        }
    }

    /**
     * Handles a received publish release message. The buffer contains the whole
     * message except command and length. The message structure is:
     *
     *   [message-identifier]
     *
     * @param string $buffer
     * @return void
     * @throws DataTransferException
     * @throws UnexpectedAcknowledgementException
     */
    protected function handlePublishRelease(string $buffer): void
    {
        $this->logger->debug('Handling publish release received from the broker.');

        if (strlen($buffer) !== 2) {
            $this->logger->notice('Received invalid publish release from the broker.');
            throw new UnexpectedAcknowledgementException(
                UnexpectedAcknowledgementException::EXCEPTION_ACK_RELEASE,
                'The MQTT broker responded with an invalid publish release message.'
            );
        }

        $messageId = $this->decodeMessageId($this->pop($buffer, 2));

        $message = $this->repository->getPendingPublishConfirmationWithMessageId($messageId);

        $result = $this->repository->removePendingPublishConfirmation($messageId);
        if ($message === null || $result === false) {
            $this->logger->notice('Received publish release from the broker for already released message.');
            throw new UnexpectedAcknowledgementException(
                UnexpectedAcknowledgementException::EXCEPTION_ACK_RELEASE,
                'The MQTT broker released a publish that has not been pending anymore.'
            );
        }

        $this->deliverPublishedMessage($message->getTopic(), $message->getMessage(), $message->getQualityOfServiceLevel());
        $this->sendPublishComplete($messageId);
    }

    /**
     * Handles a received publish confirmation message. The buffer contains the whole
     * message except command and length. The message structure is:
     *
     *   [message-identifier]
     *
     * @param string $buffer
     * @return void
     * @throws UnexpectedAcknowledgementException
     */
    protected function handlePublishCompletion(string $buffer): void
    {
        $this->logger->debug('Handling publish completion from the broker.');

        if (strlen($buffer) !== 2) {
            $this->logger->notice('Received invalid publish completion from the broker.');
            throw new UnexpectedAcknowledgementException(
                UnexpectedAcknowledgementException::EXCEPTION_ACK_COMPLETE,
                'The MQTT broker responded with an invalid publish completion.'
            );
        }

        $messageId = $this->decodeMessageId($this->pop($buffer, 2));

        $result = $this->repository->removePendingPublishedMessage($messageId);
        if ($result === false) {
            $this->logger->notice('Received publish completion from the broker for already acknowledged message.');
            throw new UnexpectedAcknowledgementException(
                UnexpectedAcknowledgementException::EXCEPTION_ACK_COMPLETE,
                'The MQTT broker sent a completion for a publish that has not been pending anymore.'
            );
        }

        $this->repository->releaseMessageId($messageId);
    }

    /**
     * Handles a received subscription acknowledgement. The buffer contains the whole
     * message except command and length. The message structure is:
     *
     *   [message-identifier:[qos-level]+]
     *
     * The order of the received QoS levels matches the order of the sent subscriptions.
     *
     * @param string $buffer
     * @return void
     * @throws UnexpectedAcknowledgementException
     */
    protected function handleSubscribeAcknowledgement(string $buffer): void
    {
        $this->logger->debug('Handling subscribe acknowledgement received from the broker.');

        if (strlen($buffer) < 3) {
            $this->logger->notice('Received invalid subscribe acknowledgement from the broker.');
            throw new UnexpectedAcknowledgementException(
                UnexpectedAcknowledgementException::EXCEPTION_ACK_SUBSCRIBE,
                'The MQTT broker responded with an invalid subscribe acknowledgement.'
            );
        }

        $messageId        = $this->decodeMessageId($this->pop($buffer, 2));
        $subscriptions    = $this->repository->getTopicSubscriptionsWithMessageId($messageId);
        $acknowledgements = str_split($buffer);

        if (count($acknowledgements) !== count($subscriptions)) {
            $this->logger->notice('Received subscribe acknowledgement from the broker with wrong number of QoS acknowledgements.', [
                'required' => count($subscriptions),
                'received' => count($acknowledgements),
            ]);
            throw new UnexpectedAcknowledgementException(
                UnexpectedAcknowledgementException::EXCEPTION_ACK_SUBSCRIBE,
                sprintf(
                    'The MQTT broker responded with a different amount of QoS acknowledgements as we have subscriptions.'
                    . ' Subscriptions: %s, QoS Acknowledgements: %s',
                    count($subscriptions),
                    count($acknowledgements)
                )
            );
        }

        foreach ($acknowledgements as $index => $qualityOfServiceLevel) {
            $subscriptions[$index]->setAcknowledgedQualityOfServiceLevel(intval($qualityOfServiceLevel));
        }

        $this->repository->releaseMessageId($messageId);
    }

    /**
     * Handles a received unsubscribe acknowledgement. The buffer contains the whole
     * message except command and length. The message structure is:
     *
     *   [message-identifier]
     *
     * @param string $buffer
     * @return void
     * @throws UnexpectedAcknowledgementException
     */
    protected function handleUnsubscribeAcknowledgement(string $buffer): void
    {
        $this->logger->debug('Handling unsubscribe acknowledgement received from the broker.');

        if (strlen($buffer) !== 2) {
            $this->logger->notice('Received invalid unsubscribe acknowledgement from the broker.');
            throw new UnexpectedAcknowledgementException(
                UnexpectedAcknowledgementException::EXCEPTION_ACK_PUBLISH,
                'The MQTT broker responded with an invalid unsubscribe acknowledgement.'
            );
        }

        $messageId = $this->decodeMessageId($this->pop($buffer, 2));

        $unsubscribeRequest = $this->repository->getPendingUnsubscribeRequestWithMessageId($messageId);
        $result             = $this->repository->removePendingUnsubscribeRequest($messageId);
        if ($result === false) {
            $this->logger->notice('Received unsubscribe acknowledgement from the broker for already acknowledged request.');
            throw new UnexpectedAcknowledgementException(
                UnexpectedAcknowledgementException::EXCEPTION_ACK_PUBLISH,
                'The MQTT broker acknowledged an unsubscribe request that has not been pending anymore.'
            );
        }

        if ($unsubscribeRequest !== null) {
            $this->repository->removeTopicSubscription($unsubscribeRequest->getTopic());
        }

        $this->repository->releaseMessageId($messageId);
    }

    /**
     * Handles a received ping request. Simply sends an acknowledgement.
     *
     * @return void
     * @throws DataTransferException
     */
    protected function handlePingRequest(): void
    {
        $this->logger->debug('Received ping request from the broker. Sending response.');

        $this->writeToSocket(chr(0xd0) . chr(0x00));
    }

    /**
     * Handles a received ping acknowledgement.
     *
     * @return void
     */
    protected function handlePingAcknowledgement(): void
    {
        $this->logger->debug('Received ping acknowledgement from the broker.');
    }
}
