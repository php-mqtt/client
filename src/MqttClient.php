<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use PhpMqtt\Client\Concerns\GeneratesRandomClientIds;
use PhpMqtt\Client\Concerns\OffersHooks;
use PhpMqtt\Client\Concerns\ValidatesConfiguration;
use PhpMqtt\Client\Contracts\MessageProcessor;
use PhpMqtt\Client\Contracts\MqttClient as ClientContract;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\Exceptions\ClientNotConnectedToBrokerException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\Exceptions\InvalidMessageException;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\Exceptions\PendingMessageAlreadyExistsException;
use PhpMqtt\Client\Exceptions\PendingMessageNotFoundException;
use PhpMqtt\Client\Exceptions\ProtocolNotSupportedException;
use PhpMqtt\Client\Exceptions\ProtocolViolationException;
use PhpMqtt\Client\MessageProcessors\Mqtt31MessageProcessor;
use PhpMqtt\Client\Repositories\MemoryRepository;
use Psr\Log\LoggerInterface;

/**
 * An MQTT client implementing protocol version 3.1.
 *
 * @package PhpMqtt\Client
 */
class MqttClient implements ClientContract
{
    use GeneratesRandomClientIds,
        OffersHooks,
        ValidatesConfiguration;

    const MQTT_3_1 = '3.1';

    const QOS_AT_MOST_ONCE  = 0;
    const QOS_AT_LEAST_ONCE = 1;
    const QOS_EXACTLY_ONCE  = 2;

    private string $host;
    private int $port;
    private string $clientId;
    private ConnectionSettings $settings;
    private string $buffer     = '';
    private bool $connected    = false;
    private ?float $lastPingAt = null;
    private MessageProcessor $messageProcessor;
    private Repository $repository;
    private Logger $logger;
    private bool $interrupted  = false;
    private int $bytesReceived = 0;
    private int $bytesSent     = 0;

    /** @var resource|null */
    protected $socket;

    /**
     * Constructs a new MQTT client which subsequently supports publishing and subscribing
     *
     * Notes:
     *   - If no client id is given, a random one is generated, forcing a clean session implicitly.
     *   - If no protocol is given, MQTT v3 is used by default.
     *   - If no repository is given, an in-memory repository is created for you. Once you terminate
     *     your script, all stored data (like resend queues) is lost.
     *   - If no logger is given, log messages are dropped. Any PSR-3 logger will work.
     *
     * @param string               $host
     * @param int                  $port
     * @param string|null          $clientId
     * @param string               $protocol
     * @param Repository|null      $repository
     * @param LoggerInterface|null $logger
     * @throws ProtocolNotSupportedException
     */
    public function __construct(
        string $host,
        int $port = 1883,
        string $clientId = null,
        string $protocol = self::MQTT_3_1,
        Repository $repository = null,
        LoggerInterface $logger = null
    )
    {
        if (!in_array($protocol, [self::MQTT_3_1])) {
            throw new ProtocolNotSupportedException($protocol);
        }

        $this->host       = $host;
        $this->port       = $port;
        $this->clientId   = $clientId ?? $this->generateRandomClientId();
        $this->repository = $repository ?? new MemoryRepository();
        $this->logger     = new Logger($this->host, $this->port, $this->clientId, $logger);

        switch ($protocol) {
            case self::MQTT_3_1:
            default:
                $this->messageProcessor = new Mqtt31MessageProcessor($this->clientId, $this->logger);
        }

        $this->initializeEventHandlers();
    }

    /**
     * {@inheritDoc}
     */
    public function connect(
        ConnectionSettings $settings = null,
        bool $useCleanSession = false
    ): void
    {
        // Always abruptly close any previous connection if we are opening a new one.
        // The caller should make sure this does not happen.
        $this->closeSocket();

        $this->logger->debug('Connecting to broker.');

        $this->settings = $settings ?? new ConnectionSettings();

        $this->ensureConnectionSettingsAreValid($this->settings);

        // When a clean session is requested, we have to reset the repository to forget about persisted states.
        if ($useCleanSession) {
            $this->repository->reset();
        }

        try {
            $this->establishSocketConnection();
            $this->performConnectionHandshake($useCleanSession);
        } catch (ConnectingToBrokerFailedException $e) {
            $this->closeSocket();

            throw $e;
        }

        $this->connected = true;
    }

    /**
     * Opens a socket that connects to the host and port set on the object.
     *
     * When this method is called, all connection settings have been validated.
     *
     * @return void
     * @throws ConnectingToBrokerFailedException
     */
    protected function establishSocketConnection(): void
    {
        $contextOptions = [];

        // Only if TLS is enabled, we add all TLS options to the context options.
        if ($this->settings->shouldUseTls()) {
            $this->logger->debug('Using TLS for the connection to the broker.');

            $shouldVerifyPeer = $this->settings->shouldTlsVerifyPeer()
                                || $this->settings->getTlsCertificateAuthorityFile() !== null
                                || $this->settings->getTlsCertificateAuthorityPath() !== null;

            if (!$shouldVerifyPeer) {
                $this->logger->warning('Using TLS without peer verification is discouraged. Are you aware of the security risk?');
            }

            if ($this->settings->isTlsSelfSignedAllowed()) {
                $this->logger->warning('Using TLS with self-signed certificates is discouraged. Please use a CA file to verify it.');
            }

            $tlsOptions = [
                'verify_peer' => $shouldVerifyPeer,
                'verify_peer_name' => $this->settings->shouldTlsVerifyPeerName(),
                'allow_self_signed' => $this->settings->isTlsSelfSignedAllowed(),
            ];

            if ($this->settings->getTlsCertificateAuthorityFile() !== null) {
                $tlsOptions['cafile'] = $this->settings->getTlsCertificateAuthorityFile();
            }

            if ($this->settings->getTlsCertificateAuthorityPath() !== null) {
                $tlsOptions['capath'] = $this->settings->getTlsCertificateAuthorityPath();
            }

            if ($this->settings->getTlsClientCertificateFile() !== null) {
                $tlsOptions['local_cert'] = $this->settings->getTlsClientCertificateFile();
            }

            if ($this->settings->getTlsClientCertificateKeyFile() !== null) {
                $tlsOptions['local_pk'] = $this->settings->getTlsClientCertificateKeyFile();
            }

            if ($this->settings->getTlsClientCertificateKeyPassphrase() !== null) {
                $tlsOptions['passphrase'] = $this->settings->getTlsClientCertificateKeyPassphrase();
            }

            $contextOptions['ssl'] = $tlsOptions;
        }

        $connectionString = 'tcp://' . $this->getHost() . ':' . $this->getPort();
        $socketContext    = stream_context_create($contextOptions);

        $socket = @stream_socket_client(
            $connectionString,
            $errorCode,
            $errorMessage,
            $this->settings->getConnectTimeout(),
            STREAM_CLIENT_CONNECT,
            $socketContext
        );

        // The socket will be set to false if stream_socket_client() returned an error.
        if ($socket === false) {
            $this->logger->error('Establishing a connection with the broker using the connection string [{connectionString}] failed: {error}', [
                'connectionString' => $connectionString,
                'error' => $errorMessage,
                'code' => $errorCode,
            ]);
            throw new ConnectingToBrokerFailedException(
                ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_SOCKET_ERROR,
                sprintf('Socket error [%d]: %s', $errorCode, $errorMessage),
                (string) $errorCode,
                $errorMessage
            );
        }

        // If TLS is enabled, we need to enable it on the already created stream.
        // Until now, we only created a normal TCP stream.
        if ($this->settings->shouldUseTls()) {
            // Since stream_socket_enable_crypto() communicates errors using error_get_last(),
            // we need to clear a potentially set error at this point to be sure the error we
            // retrieve in the error handling part is actually of this function call and not
            // from some unrelated code of the users application.
            error_clear_last();

            $this->logger->debug('Enabling TLS on the existing socket connection.');

            $enableEncryptionResult = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_ANY_CLIENT);

            if ($enableEncryptionResult === false) {
                // At this point, PHP should have given us something like this:
                //    SSL operation failed with code 1. OpenSSL Error messages:
                //    error:1416F086:SSL routines:tls_process_server_certificate:certificate verify failed
                // We need to get our hands dirty and extract the OpenSSL error
                // from the PHP message, which luckily gives us a handy newline.
                $this->parseTlsErrorMessage(error_get_last(), $tlsErrorCode, $tlsErrorMessage);

                // Before returning an exception, we need to close the already opened socket.
                @fclose($socket);

                $this->logger->error('Enabling TLS on the connection with the MQTT broker failed (code {errorCode}): {errorMessage}', [
                    'errorMessage' => $tlsErrorMessage,
                    'errorCode' => $tlsErrorCode,
                ]);

                throw new ConnectingToBrokerFailedException(
                    ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_TLS_ERROR,
                    sprintf('TLS error [%s]: %s', $tlsErrorCode, $tlsErrorMessage),
                    $tlsErrorCode,
                    $tlsErrorMessage
                );
            }

            $this->logger->debug('TLS enabled successfully.');
        }

        stream_set_timeout($socket, $this->settings->getSocketTimeout());
        stream_set_blocking($socket, false);

        $this->logger->debug('Socket opened and ready to use.');

        $this->socket = $socket;
    }

    /**
     * Internal parser for SSL-related PHP error messages.
     *
     * @param array|null  $phpError
     * @param string|null $tlsErrorCode
     * @param string|null $tlsErrorMessage
     * @return void
     */
    private function parseTlsErrorMessage(?array $phpError, ?string &$tlsErrorCode = null, ?string &$tlsErrorMessage = null): void
    {
        if (!$phpError || !isset($phpError['message'])) {
            $tlsErrorCode    = "UNKNOWN:1";
            $tlsErrorMessage = "Unknown error";
            return;
        }

        if (!preg_match('/:\n(?:error:([0-9A-Z]+):)?(.+)$/', $phpError['message'], $matches)) {
            $tlsErrorCode    = "UNKNOWN:2";
            $tlsErrorMessage = $phpError['message'];
            return;
        }

        if ($matches[1] == "") {
            $tlsErrorCode    = "UNKNOWN:3";
            $tlsErrorMessage = $matches[2];
            return;
        }

        $tlsErrorCode    = $matches[1];
        $tlsErrorMessage = $matches[2];
    }

    /**
     * Performs the connection handshake with the help of the configured message processor.
     * The connection handshake is expected to have the same flow all the time:
     *   - Connect request with variable length
     *   - Connect acknowledgement with variable length
     *
     * @param bool $useCleanSession
     * @return void
     * @throws ConnectingToBrokerFailedException
     */
    protected function performConnectionHandshake(bool $useCleanSession = false): void
    {
        try {
            $connectionHandshakeStartedAt = microtime(true);

            $data = $this->messageProcessor->buildConnectMessage($this->settings, $useCleanSession);

            $this->logger->debug('Sending connection handshake to broker.');

            $this->writeToSocket($data);

            // Start by waiting for the first byte, then using polling logic to fetch all remaining
            // data from the socket.
            $buffer        = $this->readFromSocket(1);
            $requiredBytes = -1;
            while (true) {
                if ($requiredBytes > 0) {
                    $buffer .= $this->readFromSocket($requiredBytes);
                } else {
                    $buffer .= $this->readAllAvailableDataFromSocket();
                }

                $message = null;
                $result  = $this->messageProcessor->tryFindMessageInBuffer($buffer, strlen($buffer), $message, $requiredBytes);

                // We only need to wait for the bytes we don't have in the buffer yet.
                $requiredBytes = $requiredBytes - strlen($buffer);

                if ($result === true) {
                    /** @var string $message */

                    // Remove the parsed data from the buffer.
                    $buffer = substr($buffer, strlen($message));

                    // Process the acknowledgement message.
                    $this->messageProcessor->handleConnectAcknowledgement($message);

                    break;
                }

                // If no acknowledgement has been received from the broker within the configured connection timeout period,
                // we abort the connection attempt and assume broker unavailability.
                if (microtime(true) - $this->settings->getConnectTimeout() > $connectionHandshakeStartedAt) {
                    throw new ConnectingToBrokerFailedException(
                        ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_BROKER_UNAVAILABLE,
                        'The broker did not acknowledge the connection attempt within the configured connection timeout period.'
                    );
                }
            }

            // We need to set the global buffer to the remaining data we might already have read.
            $this->buffer = $buffer;
        } catch (DataTransferException $e) {
            $this->logger->error('While connecting to the broker, a transfer error occurred.');
            throw new ConnectingToBrokerFailedException(
                ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_FAILED,
                'A connection could not be established due to data transfer issues.'
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function interrupt(): void
    {
        $this->interrupted = true;
    }

    /**
     * {@inheritDoc}
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * {@inheritDoc}
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * {@inheritDoc}
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * {@inheritDoc}
     */
    public function getReceivedBytes(): int
    {
        return $this->bytesReceived;
    }

    /**
     * {@inheritDoc}
     */
    public function getSentBytes(): int
    {
        return $this->bytesSent;
    }

    /**
     * {@inheritDoc}
     */
    public function isConnected(): bool
    {
        return $this->connected;
    }

    /**
     * Ensures the client is connected to a broker (or at least thinks it is).
     * This method does not account for closed sockets.
     *
     * @return void
     * @throws ClientNotConnectedToBrokerException
     */
    protected function ensureConnected(): void
    {
        if (!$this->isConnected()) {
            throw new ClientNotConnectedToBrokerException(
                'The client is not connected to a broker. The requested operation is impossible at this point.'
            );
        }
    }

    /**
     * {@inheritDoc}
     */
    public function disconnect(): void
    {
        $this->ensureConnected();

        $this->sendDisconnect();

        if ($this->socket !== null && is_resource($this->socket)) {
            $this->logger->debug('Closing the socket to the broker.');

            stream_socket_shutdown($this->socket, STREAM_SHUT_WR);
        }

        $this->connected = false;
    }

    /**
     * {@inheritDoc}
     */
    public function publish(string $topic, string $message, int $qualityOfService = 0, bool $retain = false): void
    {
        $this->ensureConnected();

        $messageId = null;

        if ($qualityOfService > self::QOS_AT_MOST_ONCE) {
            $messageId = $this->repository->newMessageId();

            $pendingMessage = new PublishedMessage($messageId, $topic, $message, $qualityOfService, $retain);
            $this->repository->addPendingOutgoingMessage($pendingMessage);
        }

        $this->publishMessage($topic, $message, $qualityOfService, $retain, $messageId);
    }

    /**
     * Actually publishes a message after using the configured message processor to build it.
     * This is an internal method used for both, initial publishing of messages as well as
     * re-publishing in case of timeouts.
     *
     * @param string   $topic
     * @param string   $message
     * @param int      $qualityOfService
     * @param bool     $retain
     * @param int|null $messageId
     * @param bool     $isDuplicate
     * @return void
     * @throws DataTransferException
     */
    protected function publishMessage(
        string $topic,
        string $message,
        int $qualityOfService,
        bool $retain,
        int $messageId = null,
        bool $isDuplicate = false
    ): void
    {
        $this->logger->debug('Publishing a message on topic [{topic}]: {message}', [
            'topic' => $topic,
            'message' => $message,
            'qos' => $qualityOfService,
            'retain' => $retain,
            'messageId' => $messageId,
            'isDuplicate' => $isDuplicate,
        ]);

        $this->runPublishEventHandlers($topic, $message, $messageId, $qualityOfService, $retain);

        $data = $this->messageProcessor->buildPublishMessage($topic, $message, $qualityOfService, $retain, $messageId, $isDuplicate);

        $this->writeToSocket($data);
    }

    /**
     * {@inheritDoc}
     */
    public function subscribe(string $topicFilter, callable $callback = null, int $qualityOfService = self::QOS_AT_MOST_ONCE): void
    {
        $this->ensureConnected();

        $this->logger->debug('Subscribing to topic [{topicFilter}] with maximum QoS [{qos}].', [
            'topicFilter' => $topicFilter,
            'qos' => $qualityOfService,
        ]);

        $messageId = $this->repository->newMessageId();

        // Create the subscription representation now, but it will become an
        // actual subscription only upon acknowledgement from the broker.
        $subscriptions = [new Subscription($topicFilter, $qualityOfService, $callback)];

        $pendingMessage = new SubscribeRequest($messageId, $subscriptions);
        $this->repository->addPendingOutgoingMessage($pendingMessage);

        $data = $this->messageProcessor->buildSubscribeMessage($messageId, $subscriptions);
        $this->writeToSocket($data);
    }

    /**
     * {@inheritDoc}
     */
    public function unsubscribe(string $topicFilter): void
    {
        $this->ensureConnected();

        $this->logger->debug('Unsubscribing from topic [{topicFilter}].', ['topicFilter' => $topicFilter]);

        $messageId    = $this->repository->newMessageId();
        $topicFilters = [$topicFilter];

        $pendingMessage = new UnsubscribeRequest($messageId, $topicFilters);
        $this->repository->addPendingOutgoingMessage($pendingMessage);

        $data = $this->messageProcessor->buildUnsubscribeMessage($messageId, $topicFilters);
        $this->writeToSocket($data);
    }

    /**
     * Returns the next time the broker expects to be pinged.
     *
     * @return float
     */
    protected function nextPingAt(): float
    {
        return ($this->lastPingAt + $this->settings->getKeepAliveInterval());
    }

    /**
     * {@inheritDoc}
     */
    public function loop(bool $allowSleep = true, bool $exitWhenQueuesEmpty = false, int $queueWaitLimit = null): void
    {
        $this->logger->debug('Starting client loop to process incoming messages and the resend queue.');

        $loopStartedAt = microtime(true);

        while (true) {
            if ($this->interrupted) {
                $this->interrupted = false;
                break;
            }

            $elapsedTime = microtime(true) - $loopStartedAt;
            $this->runLoopEventHandlers($elapsedTime);

            // Read data from the socket - as much as available.
            $this->buffer .= $this->readAllAvailableDataFromSocket();

            // Try to parse a message from the buffer and handle it, as long as messages can be parsed.
            if (strlen($this->buffer) > 0) {
                $this->processMessageBuffer();
            } elseif ($allowSleep) {
                usleep(100000); // 100ms
            }

            // Republish messages expired without confirmation.
            // This includes published messages, subscribe and unsubscribe requests.
            $this->resendPendingMessages();

            // If the last message of the broker has been received more seconds ago
            // than specified by the keep alive time, we will send a ping to ensure
            // the connection is kept alive.
            if ($this->nextPingAt() <= microtime(true)) {
                $this->ping();
            }

            // If configured, the loop is exited if all queues are empty or a certain time limit is reached (i.e. retry is aborted).
            // In any case, there may not be any active subscriptions though.
            if ($exitWhenQueuesEmpty && $this->repository->countSubscriptions() === 0) {
                if ($this->allQueuesAreEmpty()) {
                    break;
                }

                // The time limit is reached. This most likely means the outgoing queues could not be emptied in time.
                // Probably the server did not respond with an acknowledgement.
                if ($queueWaitLimit !== null && (microtime(true) - $loopStartedAt) > $queueWaitLimit) {
                    break;
                }
            }
        }
    }

    /**
     * Processes the incoming message buffer by parsing and handling the messages, until the buffer is empty.
     *
     * @return void
     * @throws DataTransferException
     * @throws InvalidMessageException
     * @throws MqttClientException
     * @throws ProtocolViolationException
     */
    private function processMessageBuffer(): void
    {
        while (true) {
            $data          = '';
            $requiredBytes = -1;
            $hasMessage    = $this->messageProcessor->tryFindMessageInBuffer($this->buffer, strlen($this->buffer), $data, $requiredBytes);

            // When there is no full message in the buffer, we stop processing for now and go on
            // with the next iteration.
            if ($hasMessage === false) {
                break;
            }

            // If we found a message, the buffer needs to be reduced by the message length.
            $this->buffer = substr($this->buffer, strlen($data));

            // We then pass the message over to the message processor to parse and validate it.
            $message = $this->messageProcessor->parseAndValidateMessage($data);

            // The result is used by us to perform required actions according to the protocol.
            if ($message !== null) {
                $this->handleMessage($message);
            }
        }
    }

    /**
     * Handles the given message according to its contents.
     *
     * @param Message $message
     * @return void
     * @throws DataTransferException
     * @throws ProtocolViolationException
     */
    protected function handleMessage(Message $message): void
    {
        // PUBLISH (incoming)
        if ($message->getType()->equals(MessageType::PUBLISH())) {
            if ($message->getQualityOfService() === self::QOS_AT_LEAST_ONCE) {
                // QoS 1.
                $this->sendPublishAcknowledgement($message->getMessageId());
            }

            if ($message->getQualityOfService() === self::QOS_EXACTLY_ONCE) {
                // QoS 2, part 1.
                try {
                    $pendingMessage = new PublishedMessage(
                        $message->getMessageId(),
                        $message->getTopic(),
                        $message->getContent(),
                        2,
                        false
                    );
                    $this->repository->addPendingIncomingMessage($pendingMessage);
                } catch (PendingMessageAlreadyExistsException $e) {
                    // We already received and processed this message.
                }

                // Always acknowledge, even if we received it multiple times.
                $this->sendPublishReceived($message->getMessageId());

                // We only deliver this published message as soon as we receive a publish complete.
                return;
            }

            // For QoS 0 and QoS 1 we can deliver right away.
            $this->deliverPublishedMessage($message->getTopic(), $message->getContent(), $message->getQualityOfService());
            return;
        }

        // PUBACK (outgoing, QoS 1)
        // Receiving an acknowledgement allows us to remove the published message from the retry queue.
        if ($message->getType()->equals(MessageType::PUBLISH_ACKNOWLEDGEMENT())) {
            $result = $this->repository->removePendingOutgoingMessage($message->getMessageId());
            if ($result === false) {
                $this->logger->notice('Received publish acknowledgement from the broker for already acknowledged message.', [
                    'messageId' => $message->getMessageId()
                ]);
            }
            return;
        }

        // PUBREC (outgoing, QoS 2, part 1)
        // Receiving a receipt allows us to mark the published message as received.
        if ($message->getType()->equals(MessageType::PUBLISH_RECEIPT())) {
            try {
                $result = $this->repository->markPendingOutgoingPublishedMessageAsReceived($message->getMessageId());
            } catch (PendingMessageNotFoundException $e) {
                // This should never happen as we should have received all PUBREC messages before we see the first
                // PUBCOMP which actually remove the message. So we do this for safety only.
                $result = false;
            }
            if ($result === false) {
                $this->logger->notice('Received publish receipt from the broker for already acknowledged message.', [
                    'messageId' => $message->getMessageId()
                ]);
            }

            // We always reply blindly to keep the flow moving.
            $this->sendPublishRelease($message->getMessageId());
            return;
        }

        // PUBREL (incoming, QoS 2, part 2)
        // When the broker tells us we can release the received published message, we deliver it to subscribed callbacks.
        if ($message->getType()->equals(MessageType::PUBLISH_RELEASE())) {
            $pendingMessage = $this->repository->getPendingIncomingMessage($message->getMessageId());
            if (!$pendingMessage || !$pendingMessage instanceof PublishedMessage) {
                $this->logger->notice('Received publish release from the broker for already released message.', [
                    'messageId' => $message->getMessageId(),
                ]);
            } else {
                $this->deliverPublishedMessage(
                    $pendingMessage->getTopicName(),
                    $pendingMessage->getMessage(),
                    $pendingMessage->getQualityOfServiceLevel()
                );

                $this->repository->removePendingIncomingMessage($message->getMessageId());
            }

            // Always reply with the PUBCOMP packet so it stops resending it.
            $this->sendPublishComplete($message->getMessageId());
            return;
        }

        // PUBCOMP (outgoing, QoS 2 part 3)
        // Receiving a completion allows us to remove a published message from the retry queue.
        // At this point, the publish process is complete.
        if ($message->getType()->equals(MessageType::PUBLISH_COMPLETE())) {
            $result = $this->repository->removePendingOutgoingMessage($message->getMessageId());
            if ($result === false) {
                $this->logger->notice('Received publish completion from the broker for already acknowledged message.', [
                    'messageId' => $message->getMessageId(),
                ]);
            }
            return;
        }

        // SUBACK
        if ($message->getType()->equals(MessageType::SUBSCRIBE_ACKNOWLEDGEMENT())) {
            $pendingMessage = $this->repository->getPendingOutgoingMessage($message->getMessageId());
            if (!$pendingMessage || !$pendingMessage instanceof SubscribeRequest) {
                $this->logger->notice('Received subscribe acknowledgement from the broker for already acknowledged request.', [
                    'messageId' => $message->getMessageId(),
                ]);
                return;
            }

            $acknowledgedSubscriptions = $pendingMessage->getSubscriptions();
            if (count($acknowledgedSubscriptions) != count($message->getAcknowledgedQualityOfServices())) {
                throw new ProtocolViolationException(sprintf(
                    'The MQTT broker responded with a different amount of QoS acknowledgements (%d) than we expected (%d).',
                    count($message->getAcknowledgedQualityOfServices()),
                    count($acknowledgedSubscriptions)
                ));
            }

            foreach ($message->getAcknowledgedQualityOfServices() as $index => $qualityOfService) {
                // It may happen that the server registers our subscription
                // with a lower quality of service than requested, in this
                // case this is the one that we will record.
                $acknowledgedSubscriptions[$index]->setQualityOfServiceLevel($qualityOfService);

                $this->repository->addSubscription($acknowledgedSubscriptions[$index]);
            }

            $this->repository->removePendingOutgoingMessage($message->getMessageId());
            return;
        }

        // UNSUBACK
        if ($message->getType()->equals(MessageType::UNSUBSCRIBE_ACKNOWLEDGEMENT())) {
            $pendingMessage = $this->repository->getPendingOutgoingMessage($message->getMessageId());
            if (!$pendingMessage || !$pendingMessage instanceof UnsubscribeRequest) {
                $this->logger->notice('Received unsubscribe acknowledgement from the broker for already acknowledged request.', [
                    'messageId' => $message->getMessageId(),
                ]);
                return;
            }

            foreach ($pendingMessage->getTopicFilters() as $topicFilter) {
                $this->repository->removeSubscription($topicFilter);
            }

            $this->repository->removePendingOutgoingMessage($message->getMessageId());
            return;
        }

        // PINGREQ
        if ($message->getType()->equals(MessageType::PING_REQUEST())) {
            // Respond with PINGRESP.
            $this->writeToSocket($this->messageProcessor->buildPingResponseMessage());
            return;
        }
    }

    /**
     * Determines if all queues are empty.
     *
     * @return bool
     */
    protected function allQueuesAreEmpty(): bool
    {
        return $this->repository->countPendingOutgoingMessages() === 0 &&
               $this->repository->countPendingIncomingMessages() === 0;
    }

    /**
     * Delivers a published message to subscribed callbacks.
     *
     * @param string $topic
     * @param string $message
     * @param int    $qualityOfServiceLevel
     * @param bool   $retained
     * @return void
     */
    protected function deliverPublishedMessage(string $topic, string $message, int $qualityOfServiceLevel, bool $retained = false): void
    {
        $subscribers = $this->repository->getSubscriptionsMatchingTopic($topic);

        $this->logger->debug('Delivering message received on topic [{topic}] with QoS [{qos}] from the broker to [{subscribers}] subscribers.', [
            'topic' => $topic,
            'message' => $message,
            'qos' => $qualityOfServiceLevel,
            'subscribers' => count($subscribers),
        ]);

        foreach ($subscribers as $subscriber) {
            if ($subscriber->getCallback() === null) {
                continue;
            }

            try {
                call_user_func($subscriber->getCallback(), $topic, $message, $retained);
            } catch (\Throwable $e) {
                $this->logger->error('Subscriber callback threw exception for published message on topic [{topic}].', [
                    'topic' => $topic,
                    'message' => $message,
                    'exception' => $e,
                ]);
            }
        }

        $this->runMessageReceivedEventHandlers($topic, $message, $qualityOfServiceLevel, $retained);
    }

    /**
     * Republishes pending messages.
     *
     * @return void
     * @throws DataTransferException
     * @throws InvalidMessageException
     */
    protected function resendPendingMessages(): void
    {
        /** @noinspection PhpUnhandledExceptionInspection */
        $dateTime = (new \DateTime())->sub(new \DateInterval('PT' . $this->settings->getResendTimeout() . 'S'));
        $messages = $this->repository->getPendingOutgoingMessagesLastSentBefore($dateTime);

        foreach ($messages as $pendingMessage) {
            if ($pendingMessage instanceof PublishedMessage) {
                $this->logger->debug('Re-publishing pending message to the broker.', [
                    'messageId' => $pendingMessage->getMessageId(),
                ]);

                $this->publishMessage(
                    $pendingMessage->getTopicName(),
                    $pendingMessage->getMessage(),
                    $pendingMessage->getQualityOfServiceLevel(),
                    $pendingMessage->wantsToBeRetained(),
                    $pendingMessage->getMessageId(),
                    true
                );
            } elseif ($pendingMessage instanceof SubscribeRequest) {
                $this->logger->debug('Re-sending pending subscribe request to the broker.', [
                    'messageId' => $pendingMessage->getMessageId(),
                ]);

                $data = $this->messageProcessor->buildSubscribeMessage($pendingMessage->getMessageId(), $pendingMessage->getSubscriptions(), true);
                $this->writeToSocket($data);
            } elseif ($pendingMessage instanceof UnsubscribeRequest) {
                $this->logger->debug('Re-sending pending unsubscribe request to the broker.', [
                    'messageId' => $pendingMessage->getMessageId(),
                ]);

                $data = $this->messageProcessor->buildUnsubscribeMessage($pendingMessage->getMessageId(), $pendingMessage->getTopicFilters(), true);
                $this->writeToSocket($data);
            } else {
                throw new InvalidMessageException('Unexpected message type encountered while resending pending messages.');
            }

            $pendingMessage->setLastSentAt(new \DateTime());
            $pendingMessage->incrementSendingAttempts();
        }
    }

    /**
     * Sends a publish acknowledgement for the given message identifier.
     *
     * @param int $messageId
     * @return void
     * @throws DataTransferException
     */
    protected function sendPublishAcknowledgement(int $messageId): void
    {
        $this->logger->debug('Sending publish acknowledgement to the broker (message id: {messageId}).', ['messageId' => $messageId]);

        $this->writeToSocket($this->messageProcessor->buildPublishAcknowledgementMessage($messageId));
    }

    /**
     * Sends a publish received message for the given message identifier.
     *
     * @param int $messageId
     * @return void
     * @throws DataTransferException
     */
    protected function sendPublishReceived(int $messageId): void
    {
        $this->logger->debug('Sending publish received message to the broker (message id: {messageId}).', ['messageId' => $messageId]);

        $this->writeToSocket($this->messageProcessor->buildPublishReceivedMessage($messageId));
    }

    /**
     * Sends a publish release message for the given message identifier.
     *
     * @param int $messageId
     * @return void
     * @throws DataTransferException
     */
    protected function sendPublishRelease(int $messageId): void
    {
        $this->logger->debug('Sending publish release message to the broker (message id: {messageId}).', ['messageId' => $messageId]);

        $this->writeToSocket($this->messageProcessor->buildPublishReleaseMessage($messageId));
    }

    /**
     * Sends a publish complete message for the given message identifier.
     *
     * @param int $messageId
     * @return void
     * @throws DataTransferException
     */
    protected function sendPublishComplete(int $messageId): void
    {
        $this->logger->debug('Sending publish complete message to the broker (message id: {messageId}).', ['messageId' => $messageId]);

        $this->writeToSocket($this->messageProcessor->buildPublishCompleteMessage($messageId));
    }

    /**
     * Sends a ping message to the broker to keep the connection alive.
     *
     * @return void
     * @throws DataTransferException
     */
    protected function ping(): void
    {
        $this->logger->debug('Sending ping to the broker to keep the connection alive.');

        $this->writeToSocket($this->messageProcessor->buildPingRequestMessage());
    }

    /**
     * Sends a disconnect message to the broker. Does not close the socket.
     *
     * @return void
     * @throws DataTransferException
     */
    protected function sendDisconnect(): void
    {
        $data = $this->messageProcessor->buildDisconnectMessage();

        $this->logger->debug('Sending disconnect package to the broker.');

        $this->writeToSocket($data);
    }

    /**
     * Writes some data to the socket. If a $length is given and it is shorter
     * than the data, only $length amount of bytes will be sent.
     *
     * @param string   $data
     * @param int|null $length
     * @return void
     * @throws DataTransferException
     */
    protected function writeToSocket(string $data, int $length = null): void
    {
        $calculatedLength = strlen($data);
        $length           = min($length ?? $calculatedLength, $calculatedLength);

        $result = @fwrite($this->socket, $data, $length);

        if ($result === false || $result !== $length) {
            $this->logger->error('Sending data over the socket to the broker failed.');
            throw new DataTransferException(
                DataTransferException::EXCEPTION_TX_DATA,
                'Sending data over the socket failed. Has it been closed?'
            );
        }

        $this->bytesSent += $length;

        $this->logger->debug('Sent data over the socket: {data}', ['data' => $data]);

        // After writing successfully to the socket, the broker should have received a new message from us.
        // Because we only need to send a ping if no other messages are delivered, we can safely reset the ping timer.
        $this->lastPingAt = microtime(true);
    }

    /**
     * Reads data from the socket. If the second parameter $withoutBlocking is set to true,
     * a maximum of $limit bytes will be read and returned. If $withoutBlocking is set to false,
     * the method will wait until $limit bytes have been received.
     *
     * @param int  $limit
     * @param bool $withoutBlocking
     * @return string
     * @throws DataTransferException
     */
    protected function readFromSocket(int $limit = 8192, bool $withoutBlocking = false): string
    {
        if ($withoutBlocking) {
            $result = fread($this->socket, $limit);

            if ($result === false) {
                $this->logger->error('Reading data from the socket of the broker failed.');
                throw new DataTransferException(
                    DataTransferException::EXCEPTION_RX_DATA,
                    'Reading data from the socket failed. Has it been closed?'
                );
            }

            $this->bytesReceived += strlen($result);

            $this->logger->debug('Read data from the socket (without blocking): {data}', ['data' => $result]);

            return $result;
        }

        $result    = '';
        $remaining = $limit;

        $this->logger->debug('Waiting for {bytes} bytes of data.', ['bytes' => $remaining]);

        while (feof($this->socket) === false && $remaining > 0) {
            $receivedData = fread($this->socket, $remaining);
            if ($receivedData === false) {
                $this->logger->error('Reading data from the socket of the broker failed.');
                throw new DataTransferException(
                    DataTransferException::EXCEPTION_RX_DATA,
                    'Reading data from the socket failed. Has it been closed?'
                );
            }
            $result   .= $receivedData;
            $remaining = $limit - strlen($result);
        }

        $this->bytesReceived += strlen($result);

        $this->logger->debug('Read data from the socket: {data}', ['data' => $result]);

        return $result;
    }

    /**
     * Reads all the available data from the socket using non-blocking mode. Essentially this means
     * that {@see MqttClient::readFromSocket()} is called over and over again, as long as data is
     * returned.
     *
     * @return string
     * @throws DataTransferException
     */
    protected function readAllAvailableDataFromSocket(): string
    {
        $result = '';

        while (true) {
            $buffer = $this->readFromSocket(8192, true);

            $result .= $buffer;

            if (strlen($buffer) < 8192) {
                break;
            }
        }

        return $result;
    }

    /**
     * Closes the socket connection immediately, without flushing queued data.
     *
     * @return void
     */
    protected function closeSocket(): void
    {
        if ($this->socket === null || !is_resource($this->socket)) {
            return;
        }

        if (@fclose($this->socket)) {
            $this->logger->debug('Successfully closed socket connection to the broker.');
        } else {
            $phpError = error_get_last();
            $this->logger->notice('Closing socket connection failed: {error}', [
                'error' => $phpError ? $phpError['message'] : 'undefined',
            ]);
        }

        $this->socket = null;
    }
}
