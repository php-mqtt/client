<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use DateInterval;
use DateTime;
use PhpMqtt\Client\Concerns\GeneratesRandomClientIds;
use PhpMqtt\Client\Concerns\OffersHooks;
use PhpMqtt\Client\Concerns\ValidatesConfiguration;
use PhpMqtt\Client\Contracts\MessageProcessor;
use PhpMqtt\Client\Contracts\MqttClient as ClientContract;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\Exceptions\ClientNotConnectedToBrokerException;
use PhpMqtt\Client\Exceptions\ConfigurationInvalidException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\Exceptions\MqttClientException;
use PhpMqtt\Client\Exceptions\PendingPublishConfirmationAlreadyExistsException;
use PhpMqtt\Client\Exceptions\ProtocolNotSupportedException;
use PhpMqtt\Client\Exceptions\TopicNotSubscribedException;
use PhpMqtt\Client\Exceptions\UnexpectedAcknowledgementException;
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

    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var string */
    private $clientId;

    /** @var ConnectionSettings|null */
    private $settings;

    /** @var string */
    private $buffer = '';

    /** @var bool */
    private $connected = false;

    /** @var float */
    private $lastPingAt;

    /** @var MessageProcessor */
    private $messageProcessor;

    /** @var Repository */
    private $repository;

    /** @var LoggerInterface */
    private $logger;

    /** @var bool */
    private $interrupted = false;

    /** @var int */
    private $bytesReceived = 0;

    /** @var int */
    private $bytesSent = 0;

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

        if ($repository === null) {
            $repository = new MemoryRepository();
        }

        $this->host       = $host;
        $this->port       = $port;
        $this->clientId   = $clientId ?? $this->generateRandomClientId();
        $this->repository = $repository;
        $this->logger     = new Logger($this->host, $this->port, $this->clientId, $logger);

        switch ($protocol) {
            case self::MQTT_3_1:
            default:
                $this->messageProcessor = new Mqtt31MessageProcessor($this->clientId, $this->logger);
        }

        $this->initializeEventHandlers();
    }

    /**
     * Connect to the MQTT broker using the given credentials and settings.
     * If no custom settings are passed, the client will use the default settings.
     * See {@see ConnectionSettings} for more details about the defaults.
     *
     * In case there is an existing connection, it will be abruptly closed and
     * replaced with the new one, as `disconnect()` should be called first.
     *
     * @param ConnectionSettings|null $settings
     * @param bool                    $sendCleanSessionFlag
     * @return void
     * @throws ConfigurationInvalidException
     * @throws ConnectingToBrokerFailedException
     */
    public function connect(
        ConnectionSettings $settings = null,
        bool $sendCleanSessionFlag = false
    ): void
    {
        // Always abruptly close any previous connection if we are opening a new one.
        // The caller should make sure this does not happen.
        $this->closeSocket();

        $this->logger->debug('Connecting to broker.');

        $this->settings = $settings ?? new ConnectionSettings();

        $this->ensureConnectionSettingsAreValid($this->settings);

        try {
            $this->establishSocketConnection();
            $this->performConnectionHandshake($sendCleanSessionFlag);
        } catch (ConnectingToBrokerFailedException $e) {
            if ($this->socket !== null) {
                $this->closeSocket();
            }

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

            if ($this->settings->getTlsClientCertificatePassphrase() !== null) {
                $tlsOptions['passphrase'] = $this->settings->getTlsClientCertificatePassphrase();
            }

            $contextOptions['ssl'] = $tlsOptions;
        }

        $connectionString = 'tcp://' . $this->getHost() . ':' . $this->getPort();
        $socketContext    = stream_context_create($contextOptions);

        $socket = stream_socket_client(
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

                $this->logger->error(sprintf(
                    'Enabling TLS on the connection with the MQTT broker [%s] failed: %s (code %s).',
                    $connectionString,
                    $tlsErrorMessage,
                    $tlsErrorCode
                ));

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
     * @param array       $phpError
     * @param string|null $tlsErrorCode
     * @param string|null $tlsErrorMessage
     * @return void
     */
    private function parseTlsErrorMessage($phpError, ?string &$tlsErrorCode = null, ?string &$tlsErrorMessage = null): void
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
     * @throws ConnectingToBrokerFailedException
     */
    protected function performConnectionHandshake(bool $useCleanSession = false): void
    {
        try {
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
     * Sets the interrupted signal. Doing so instructs the client to exit the loop, if it is
     * actually looping.
     *
     * Sending multiple interrupt signals has no effect, unless the client exits the loop,
     * which resets the signal for another loop.
     *
     * @return void
     */
    public function interrupt(): void
    {
        $this->interrupted = true;
    }

    /**
     * Returns the host used by the client to connect to.
     *
     * @return string
     */
    public function getHost(): string
    {
        return $this->host;
    }

    /**
     * Returns the port used by the client to connect to.
     *
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * Returns the identifier used by the client.
     *
     * @return string
     */
    public function getClientId(): string
    {
        return $this->clientId;
    }

    /**
     * Returns the total number of received bytes, across reconnects.
     *
     * @return int
     */
    public function getReceivedBytes(): int
    {
        return $this->bytesReceived;
    }

    /**
     * Returns the total number of sent bytes, across reconnects.
     *
     * @return int
     */
    public function getSentBytes(): int
    {
        return $this->bytesSent;
    }

    /**
     * Returns an indication, whether the client is supposed to be connected already or not.
     *
     * Note: the result of this method should be used carefully, since we can only detect a
     * closed socket once we try to send or receive data. Therefore, this method only gives
     * an indication whether the client is in a connected state or not.
     * This information may be useful in applications where multiple parts use the client.
     *
     * @return bool
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
     * Sends a disconnect and closes the socket.
     *
     * @return void
     * @throws DataTransferException
     */
    public function close(): void
    {
        $this->ensureConnected();

        $this->logger->debug('Closing the connection to the broker.');

        $this->disconnect();

        if ($this->socket !== null && is_resource($this->socket)) {
            stream_socket_shutdown($this->socket, STREAM_SHUT_WR);
        }

        $this->connected = false;
    }

    /**
     * Publishes the given message on the given topic. If the additional quality of service
     * and retention flags are set, the message will be published using these settings.
     *
     * @param string $topic
     * @param string $message
     * @param int    $qualityOfService
     * @param bool   $retain
     * @return void
     * @throws DataTransferException
     */
    public function publish(string $topic, string $message, int $qualityOfService = 0, bool $retain = false): void
    {
        $this->ensureConnected();

        $messageId = null;

        if ($qualityOfService > self::QOS_AT_MOST_ONCE) {
            $messageId = $this->repository->newMessageId();

            $pendingMessage = new PublishedMessage($messageId, $topic, $message, $qualityOfService, $retain);
            $this->repository->addPendingPublishedMessage($pendingMessage);
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
            'message_id' => $messageId,
            'is_duplicate' => $isDuplicate,
        ]);

        foreach ($this->publishEventHandlers as $handler) {
            try {
                call_user_func($handler, $this, $topic, $message, $messageId, $qualityOfService, $retain);
            } catch (\Throwable $e) {
                $this->logger->error('Publish hook callback threw exception for published message on topic [{topic}].', [
                    'topic' => $topic,
                    'exception' => $e,
                ]);
            }
        }

        $data = $this->messageProcessor->buildPublishMessage($topic, $message, $qualityOfService, $retain, $messageId, $isDuplicate);

        $this->writeToSocket($data);
    }

    /**
     * Subscribe to the given topic with the given quality of service.
     *
     * The subscription callback is passed the topic as first and the message as second
     * parameter. A third parameter indicates whether the received message has been sent
     * because it was retained by the broker.
     *
     * Example:
     * ```php
     * $mqtt->subscribe(
     *     '/foo/bar/+',
     *     function (string $topic, string $message, bool $retained) use ($logger) {
     *         $logger->info("Received {retained} message on topic [{topic}]: {message}", [
     *             'topic' => $topic,
     *             'message' => $message,
     *             'retained' => $retained ? 'retained' : 'live'
     *         ]);
     *     }
     * );
     * ```
     *
     * @param string   $topic
     * @param callable $callback
     * @param int      $qualityOfService
     * @return void
     * @throws DataTransferException
     */
    public function subscribe(string $topic, callable $callback, int $qualityOfService = self::QOS_AT_MOST_ONCE): void
    {
        $this->ensureConnected();

        $messageId = $this->repository->newMessageId();
        $data      = $this->messageProcessor->buildSubscribeMessage($messageId, $topic, $qualityOfService);

        $this->logger->debug('Subscribing to topic [{topic}] with QoS [{qos}].', [
            'topic' => $topic,
            'qos' => $qualityOfService,
        ]);

        $pendingMessage = new TopicSubscription($topic, $callback, $messageId, $qualityOfService);
        $this->repository->addTopicSubscription($pendingMessage);

        $this->writeToSocket($data);
    }

    /**
     * Unsubscribe from the given topic.
     *
     * @param string $topic
     * @return void
     * @throws DataTransferException
     * @throws TopicNotSubscribedException
     */
    public function unsubscribe(string $topic): void
    {
        $this->ensureConnected();

        $subscription = $this->repository->getTopicSubscriptionByTopic($topic);
        if ($subscription === null) {
            throw new TopicNotSubscribedException(sprintf('No subscription found for topic [%s].', $topic));
        }

        $messageId = $this->repository->newMessageId();
        $data      = $this->messageProcessor->buildUnsubscribeMessage($messageId, $topic);

        $this->logger->debug('Unsubscribing from topic [{topic}].', [
            'messageId' => $messageId,
            'topic' => $topic,
        ]);

        $pendingMessage = new UnsubscribeRequest($messageId, $topic);
        $this->repository->addPendingUnsubscribeRequest($pendingMessage);

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
     * Runs an event loop that handles messages from the server and calls the registered
     * callbacks for published messages.
     *
     * If the second parameter is provided, the loop will exit as soon as all
     * queues are empty. This means there may be no open subscriptions,
     * no pending messages as well as acknowledgments and no pending unsubscribe requests.
     *
     * The third parameter will, if set, lead to a forceful exit after the specified
     * amount of seconds, but only if the second parameter is set to true. This basically
     * means that if we wait for all pending messages to be acknowledged, we only wait
     * a maximum of $queueWaitLimit seconds until we give up. We do not exit after the
     * given amount of time if there are open topic subscriptions though.
     *
     * @param bool     $allowSleep
     * @param bool     $exitWhenQueuesEmpty
     * @param int|null $queueWaitLimit
     * @return void
     * @throws DataTransferException
     * @throws MqttClientException
     */
    public function loop(bool $allowSleep = true, bool $exitWhenQueuesEmpty = false, int $queueWaitLimit = null): void
    {
        $this->logger->debug('Starting client loop to process incoming messages and the resend queue.');

        $loopStartedAt            = microtime(true);
        $lastRepublishedAt        = microtime(true);
        $lastResendUnsubscribedAt = microtime(true);

        while (true) {
            if ($this->interrupted) {
                $this->interrupted = false;
                break;
            }

            $elapsedTime = microtime(true) - $loopStartedAt;

            foreach ($this->loopEventHandlers as $handler) {
                try {
                    call_user_func($handler, $this, $elapsedTime);
                } catch (\Throwable $e) {
                    $this->logger->error('Loop hook callback threw exception.', ['exception' => $e]);
                }
            }

            // Read data from the socket - as much as available.
            $this->buffer .= $this->readAllAvailableDataFromSocket();

            // Try to parse a message from the buffer and handle it, as long as messages can be parsed.
            if (strlen($this->buffer) > 0) {
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
                        try {
                            $this->handleMessage($message);
                        } catch (UnexpectedAcknowledgementException $e) {
                            $this->logger->warning($e);
                        }
                    }
                }
            } else {
                if ($allowSleep) {
                    usleep(100000); // 100ms
                }
            }

            // Once a second we try to republish messages without confirmation.
            // This will only trigger the republishing though. If a message really
            // gets republished depends on the resend timeout and the last time
            // we sent the message.
            if (1 < (microtime(true) - $lastRepublishedAt)) {
                $this->republishPendingMessages();
                $lastRepublishedAt = microtime(true);
            }

            // Once a second we try to resend unconfirmed unsubscribe requests.
            // This will also only trigger the resending process. If an unsubscribe
            // request really gets resend depends on the resend timeout and the last
            // time we sent the unsubscribe request.
            if (1 < (microtime(true) - $lastResendUnsubscribedAt)) {
                $this->republishPendingUnsubscribeRequests();
                $lastResendUnsubscribedAt = microtime(true);
            }

            // If the last message of the broker has been received more seconds ago
            // than specified by the keep alive time, we will send a ping to ensure
            // the connection is kept alive.
            if ($this->nextPingAt() <= microtime(true)) {
                $this->ping();
            }

            // This check will ensure, that, if we want to exit as soon as all queues
            // are empty and they really are empty, we quit.
            if ($exitWhenQueuesEmpty) {
                if ($this->allQueuesAreEmpty() && $this->repository->countTopicSubscriptions() === 0) {
                    break;
                }

                // We also exit the loop if there are no open topic subscriptions
                // and we reached the time limit.
                if ($queueWaitLimit !== null &&
                    (microtime(true) - $loopStartedAt) > $queueWaitLimit &&
                    $this->repository->countTopicSubscriptions() === 0) {
                    break;
                }
            }
        }
    }

    /**
     * Handles the given message according to its contents.
     *
     * @param Message $message
     * @throws DataTransferException
     * @throws UnexpectedAcknowledgementException
     */
    protected function handleMessage(Message $message): void
    {
        // PUBLISH
        if ($message->getType()->equals(MessageType::PUBLISH())) {
            if ($message->getQualityOfService() === self::QOS_AT_LEAST_ONCE) {
                $this->sendPublishAcknowledgement($message->getMessageId());
            }

            if ($message->getQualityOfService() === self::QOS_EXACTLY_ONCE) {
                try {
                    $this->sendPublishReceived($message->getMessageId());
                    $pendingMessage = new PublishedMessage(
                        $message->getMessageId(),
                        $message->getTopic(),
                        $message->getContent(),
                        2,
                        false
                    );
                    $this->repository->addPendingPublishConfirmation($pendingMessage);
                } catch (PendingPublishConfirmationAlreadyExistsException $e) {
                    // We already received and processed this message, therefore we do not respond
                    // with a receipt a second time and wait for the release instead.
                }
                // We only deliver this published message as soon as we receive a publish complete.
                return;
            }

            $this->deliverPublishedMessage($message->getTopic(), $message->getContent(), $message->getQualityOfService());
        }

        // PUBACK
        if ($message->getType()->equals(MessageType::PUBLISH_ACKNOWLEDGEMENT())) {
            $result = $this->repository->removePendingPublishedMessage($message->getMessageId());
            if ($result === false) {
                $this->logger->notice('Received publish acknowledgement from the broker for already acknowledged message.');
                throw new UnexpectedAcknowledgementException(
                    UnexpectedAcknowledgementException::EXCEPTION_ACK_PUBLISH,
                    'The MQTT broker acknowledged a publish that has not been pending anymore.'
                );
            }

            $this->repository->releaseMessageId($message->getMessageId());
        }

        // PUBREC
        if ($message->getType()->equals(MessageType::PUBLISH_RECEIPT())) {
            $result = $this->repository->markPendingPublishedMessageAsReceived($message->getMessageId());
            if ($result === false) {
                $this->logger->notice('Received publish receipt from the broker for already acknowledged message.');
                throw new UnexpectedAcknowledgementException(
                    UnexpectedAcknowledgementException::EXCEPTION_ACK_RECEIVE,
                    'The MQTT broker sent a receipt for a publish that has not been pending anymore.'
                );
            }
        }

        // PUBREL
        if ($message->getType()->equals(MessageType::PUBLISH_RELEASE())) {
            $publishedMessage = $this->repository->getPendingPublishConfirmationWithMessageId($message->getMessageId());
            $result           = $this->repository->removePendingPublishConfirmation($message->getMessageId());
            if ($publishedMessage === null || $result === false) {
                $this->logger->notice('Received publish release from the broker for already released message.');
                throw new UnexpectedAcknowledgementException(
                    UnexpectedAcknowledgementException::EXCEPTION_ACK_RELEASE,
                    'The MQTT broker released a publish that has not been pending anymore.'
                );
            }

            $this->deliverPublishedMessage(
                $publishedMessage->getTopic(),
                $publishedMessage->getMessage(),
                $publishedMessage->getQualityOfServiceLevel()
            );

            $this->sendPublishComplete($message->getMessageId());
        }

        // PUBCOMP
        if ($message->getType()->equals(MessageType::PUBLISH_COMPLETE())) {
            $result = $this->repository->removePendingPublishedMessage($message->getMessageId());
            if ($result === false) {
                $this->logger->notice('Received publish completion from the broker for already acknowledged message.');
                throw new UnexpectedAcknowledgementException(
                    UnexpectedAcknowledgementException::EXCEPTION_ACK_COMPLETE,
                    'The MQTT broker sent a completion for a publish that has not been pending anymore.'
                );
            }

            $this->repository->releaseMessageId($message->getMessageId());
        }

        // SUBACK
        if ($message->getType()->equals(MessageType::SUBSCRIBE_ACKNOWLEDGEMENT())) {
            $subscriptions = $this->repository->getTopicSubscriptionsWithMessageId($message->getMessageId());

            if (count($message->getAcknowledgedQualityOfServices()) !== count($subscriptions)) {
                $this->logger->notice('Received subscribe acknowledgement from the broker with wrong number of QoS acknowledgements.', [
                    'required' => count($subscriptions),
                    'received' => count($message->getAcknowledgedQualityOfServices()),
                ]);
                throw new UnexpectedAcknowledgementException(
                    UnexpectedAcknowledgementException::EXCEPTION_ACK_SUBSCRIBE,
                    sprintf(
                        'The MQTT broker responded with a different amount of QoS acknowledgements as we have subscriptions.'
                        . ' Subscriptions: %s, QoS Acknowledgements: %s',
                        count($subscriptions),
                        count($message->getAcknowledgedQualityOfServices())
                    )
                );
            }

            foreach ($message->getAcknowledgedQualityOfServices() as $index => $qualityOfServiceLevel) {
                $subscriptions[$index]->setAcknowledgedQualityOfServiceLevel(intval($qualityOfServiceLevel));
            }

            $this->repository->releaseMessageId($message->getMessageId());
        }

        // UNSUBACK
        if ($message->getType()->equals(MessageType::UNSUBSCRIBE_ACKNOWLEDGEMENT())) {
            $unsubscribeRequest = $this->repository->getPendingUnsubscribeRequestWithMessageId($message->getMessageId());
            $result             = $this->repository->removePendingUnsubscribeRequest($message->getMessageId());
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

            $this->repository->releaseMessageId($message->getMessageId());
        }

        // PINGREQ
        if ($message->getType()->equals(MessageType::PING_REQUEST())) {
            // Respond with PINGRESP.
            $this->writeToSocket(chr(0xd0) . chr(0x00));
        }
    }

    /**
     * Determines if all queues are empty.
     *
     * @return bool
     */
    protected function allQueuesAreEmpty(): bool
    {
        return $this->repository->countPendingPublishMessages() === 0 &&
               $this->repository->countPendingUnsubscribeRequests() === 0 &&
               $this->repository->countPendingPublishConfirmations() === 0;
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
        $subscribers = $this->repository->getTopicSubscriptionsMatchingTopic($topic);

        $this->logger->debug('Delivering message received on topic [{topic}] from the broker to [{subscribers}] subscribers.', [
            'topic' => $topic,
            'message' => $message,
            'subscribers' => count($subscribers),
        ]);

        foreach ($subscribers as $subscriber) {
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
    }

    /**
     * Republishes pending messages.
     *
     * @return void
     * @throws DataTransferException
     */
    protected function republishPendingMessages(): void
    {
        $this->logger->debug('Re-publishing pending messages to the broker.');

        /** @noinspection PhpUnhandledExceptionInspection */
        $dateTime = (new DateTime())->sub(new DateInterval('PT' . $this->settings->getResendTimeout() . 'S'));
        $messages = $this->repository->getPendingPublishedMessagesLastSentBefore($dateTime);

        foreach ($messages as $message) {
            $this->logger->debug('Re-publishing pending message to the broker.', ['messageId' => $message->getMessageId()]);

            $this->publishMessage(
                $message->getTopic(),
                $message->getMessage(),
                $message->getQualityOfServiceLevel(),
                $message->wantsToBeRetained(),
                $message->getMessageId(),
                true
            );

            $message->setLastSentAt(new DateTime());
            $message->incrementSendingAttempts();
        }
    }

    /**
     * Re-sends pending unsubscribe requests.
     *
     * @return void
     * @throws DataTransferException
     */
    protected function republishPendingUnsubscribeRequests(): void
    {
        $this->logger->debug('Re-sending pending unsubscribe requests to the broker.');

        /** @noinspection PhpUnhandledExceptionInspection */
        $dateTime = (new DateTime())->sub(new DateInterval('PT' . $this->settings->getResendTimeout() . 'S'));
        $requests = $this->repository->getPendingUnsubscribeRequestsLastSentBefore($dateTime);

        foreach ($requests as $request) {
            $data = $this->messageProcessor->buildUnsubscribeMessage($request->getMessageId(), $request->getTopic(), true);

            $this->logger->debug('Re-sending pending unsubscribe request to the broker.', [
                'messageId' => $request->getMessageId(),
                'topic' => $request->getTopic(),
            ]);

            $this->writeToSocket($data);

            $request->setLastSentAt(new DateTime());
            $request->incrementSendingAttempts();
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
        $this->logger->debug('Sending publish acknowledgement to the broker.', ['message_id' => $messageId]);

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
        $this->logger->debug('Sending publish received message to the broker.', ['message_id' => $messageId]);

        $this->writeToSocket($this->messageProcessor->buildPublishReceivedMessage($messageId));
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
        $this->logger->debug('Sending publish complete message to the broker.', ['message_id' => $messageId]);

        $this->writeToSocket($this->messageProcessor->buildPublishCompleteMessage($messageId));
    }

    /**
     * Sends a ping message to the broker to keep the connection alive.
     *
     * @throws DataTransferException
     */
    protected function ping(): void
    {
        $this->logger->debug('Sending ping to the broker to keep the connection alive.');

        $this->writeToSocket($this->messageProcessor->buildPingMessage());
    }

    /**
     * Sends a disconnect message to the broker. Does not close the socket.
     *
     * @throws DataTransferException
     */
    protected function disconnect(): void
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
            $this->logger->debug('Closing socket connection failed: {error}', [
                'error' => $phpError ? $phpError['message'] : 'undefined',
            ]);
        }

        $this->socket = null;
    }
}
