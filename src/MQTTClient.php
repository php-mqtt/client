<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use DateInterval;
use DateTime;
use PhpMqtt\Client\Concerns\GeneratesRandomClientIds;
use PhpMqtt\Client\Concerns\OffersHooks;
use PhpMqtt\Client\Concerns\TranscodesData;
use PhpMqtt\Client\Concerns\WorksWithBuffers;
use PhpMqtt\Client\Contracts\MQTTClient as ClientContract;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\Exceptions\ClientNotConnectedToBrokerException;
use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\Exceptions\DataTransferException;
use PhpMqtt\Client\Exceptions\PendingPublishConfirmationAlreadyExistsException;
use PhpMqtt\Client\Exceptions\UnexpectedAcknowledgementException;
use PhpMqtt\Client\Repositories\MemoryRepository;
use Psr\Log\LoggerInterface;

/**
 * An MQTT client implementing protocol version 3.1.
 *
 * @package PhpMqtt\Client
 */
class MQTTClient implements ClientContract
{
    use GeneratesRandomClientIds,
        OffersHooks,
        TranscodesData,
        WorksWithBuffers;

    const EXCEPTION_CONNECTION_FAILED              = 0001;
    const EXCEPTION_CONNECTION_PROTOCOL_VERSION    = 0002;
    const EXCEPTION_CONNECTION_IDENTIFIER_REJECTED = 0003;
    const EXCEPTION_CONNECTION_BROKER_UNAVAILABLE  = 0004;
    const EXCEPTION_CONNECTION_NOT_ESTABLISHED     = 0005;
    const EXCEPTION_TX_DATA                        = 0101;
    const EXCEPTION_RX_DATA                        = 0102;
    const EXCEPTION_ACK_CONNECT                    = 0201;
    const EXCEPTION_ACK_PUBLISH                    = 0202;
    const EXCEPTION_ACK_SUBSCRIBE                  = 0203;
    const EXCEPTION_ACK_RELEASE                    = 0204;
    const EXCEPTION_ACK_RECEIVE                    = 0205;
    const EXCEPTION_ACK_COMPLETE                   = 0206;

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

    /** @var string|null */
    private $caFile;

    /** @var resource|null */
    private $socket;

    /** @var bool */
    private $connected = false;

    /** @var float */
    private $lastPingAt;

    /** @var int */
    private $messageId = 1;

    /** @var Repository */
    private $repository;

    /** @var LoggerInterface */
    private $logger;

    /** @var bool */
    private $interrupted = false;

    /**
     * Constructs a new MQTT client which subsequently supports publishing and subscribing
     *
     * Notes:
     *   - If no client id is given, a random one is generated, forcing a clean session implicitly.
     *   - If a CA file is given, it is used to verify the peer server certificate.
     *   - If no repository is given, an in-memory repository is created for you. Once you terminate
     *     your script, all stored data (like resend queues) is lost.
     *   - If no logger is given, log messages are dropped. Any PSR-3 logger will work.
     *
     * @param string               $host
     * @param int                  $port
     * @param string|null          $clientId
     * @param string|null          $caFile
     * @param Repository|null      $repository
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        string $host,
        int $port = 1883,
        string $clientId = null,
        string $caFile = null,
        Repository $repository = null,
        LoggerInterface $logger = null
    )
    {
        if ($repository === null) {
            $repository = new MemoryRepository();
        }

        $this->host       = $host;
        $this->port       = $port;
        $this->clientId   = $clientId ?? $this->generateRandomClientId();
        $this->caFile     = $caFile;
        $this->repository = $repository;
        $this->logger     = new Logger($logger);

        $this->initializeEventHandlers();
    }

    /**
     * Connect to the MQTT broker using the given credentials and settings.
     * If no custom settings are passed, the client will use the default settings.
     * See {@see ConnectionSettings} for more details about the defaults.
     *
     * @param string|null             $username
     * @param string|null             $password
     * @param ConnectionSettings|null $settings
     * @param bool                    $sendCleanSessionFlag
     * @return void
     * @throws ConnectingToBrokerFailedException
     */
    public function connect(
        string $username = null,
        string $password = null,
        ConnectionSettings $settings = null,
        bool $sendCleanSessionFlag = false
    ): void
    {
        $this->logger->info(sprintf('Connecting to MQTT broker [%s:%s].', $this->host, $this->port));

        $this->settings = $settings ?? new ConnectionSettings();

        $this->establishSocketConnection();
        $this->performConnectionHandshake($username, $password, $sendCleanSessionFlag);

        $this->connected = true;
    }

    /**
     * Opens a socket that connects to the host and port set on the object.
     *
     * @return void
     * @throws ConnectingToBrokerFailedException
     */
    protected function establishSocketConnection(): void
    {
        if ($this->hasCertificateAuthorityFile()) {
            $this->logger->info(sprintf('Using certificate authority file [%s] to verify peer name.', $this->caFile));

            $socketContext = stream_context_create([
                'ssl' => [
                'verify_peer_name' => true,
                    'cafile' => $this->getCertificateAuthorityFile(),
                ],
            ]);
            $connectionString = 'tls://' . $this->getHost() . ':' . $this->getPort();
            $this->socket     = stream_socket_client($connectionString, $errorCode, $errorMessage, 60, STREAM_CLIENT_CONNECT, $socketContext);
        } else {
            $connectionString = 'tcp://' . $this->getHost() . ':' . $this->getPort();
            $this->socket     = stream_socket_client($connectionString, $errorCode, $errorMessage, 60, STREAM_CLIENT_CONNECT);
        }

        if ($this->socket === false) {
            $this->logger->error(sprintf('Establishing a connection with the MQTT broker using connection string [%s] failed.', $connectionString));
            throw new ConnectingToBrokerFailedException($errorCode, $errorMessage);
        }

        stream_set_timeout($this->socket, $this->settings->getSocketTimeout());
        stream_set_blocking($this->socket, $this->settings->wantsToBlockSocket());
    }

    /**
     * Sends a connection message over the socket and processes the response.
     * If the socket connection is not established, an exception is thrown.
     *
     * @param string|null $username
     * @param string|null $password
     * @param bool        $sendCleanSessionFlag
     * @return void
     * @throws ConnectingToBrokerFailedException
     */
    protected function performConnectionHandshake(string $username = null, string $password = null, bool $sendCleanSessionFlag = false): void
    {
        try {
            $i = 0;
            $buffer = '';

            // protocol header
            $buffer .= chr(0x00); $i++; // length of protocol name 1
            $buffer .= chr(0x06); $i++; // length of protocol name 2
            $buffer .= chr(0x4d); $i++; // protocol name: M
            $buffer .= chr(0x51); $i++; // protocol name: Q
            $buffer .= chr(0x49); $i++; // protocol name: I
            $buffer .= chr(0x73); $i++; // protocol name: s
            $buffer .= chr(0x64); $i++; // protocol name: d
            $buffer .= chr(0x70); $i++; // protocol name: p
            $buffer .= chr(0x03); $i++; // protocol version (3.1.1)

            // connection flags
            $flags   = $this->buildConnectionFlags($username, $password, $sendCleanSessionFlag);
            $buffer .= chr($flags); $i++;

            // keep alive settings
            $buffer .= chr($this->settings->getKeepAlive() >> 8); $i++;
            $buffer .= chr($this->settings->getKeepAlive() & 0xff); $i++;

            // client id (connection identifier)
            $clientIdPart = $this->buildLengthPrefixedString($this->clientId);
            $buffer      .= $clientIdPart;
            $i           += strlen($clientIdPart);

            // last will topic and message
            if ($this->settings->hasLastWill()) {
                $topicPart = $this->buildLengthPrefixedString($this->settings->getLastWillTopic());
                $buffer   .= $topicPart;
                $i        += strlen($topicPart);

                $messagePart = $this->buildLengthPrefixedString($this->settings->getLastWillMessage());
                $buffer     .= $messagePart;
                $i          += strlen($messagePart);
            }

            // credentials
            if ($username !== null) {
                $usernamePart = $this->buildLengthPrefixedString($username);
                $buffer      .= $usernamePart;
                $i           += strlen($usernamePart);
            }
            if ($password !== null) {
                $passwordPart = $this->buildLengthPrefixedString($password);
                $buffer      .= $passwordPart;
                $i           += strlen($passwordPart);
            }

            // message type and message length
            $header = chr(0x10) . chr($i);

            // send the connection message
            $this->logger->info('Sending connection handshake to MQTT broker.');
            $this->writeToSocket($header . $buffer);

            // read and process the acknowledgement
            $acknowledgement = $this->readFromSocket(4);
            if (ord($acknowledgement[0]) >> 4 === 2) {
                switch ($acknowledgement[3]) {
                    case chr(0):
                        $this->logger->info(sprintf('Connection with MQTT broker at [%s:%s] established successfully.', $this->host, $this->port));
                        break;
                    case chr(1):
                        $this->logger->error(sprintf('The MQTT broker at [%s:%s] does not support MQTT v3.', $this->host, $this->port));
                        throw new ConnectingToBrokerFailedException(
                            self::EXCEPTION_CONNECTION_PROTOCOL_VERSION,
                            'The selected MQTT broker does not support MQTT v3.'
                        );
                    case chr(2):
                        $this->logger->error(sprintf('The MQTT broker at [%s:%s] rejected the sent identifier.', $this->host, $this->port));
                        throw new ConnectingToBrokerFailedException(
                            self::EXCEPTION_CONNECTION_IDENTIFIER_REJECTED,
                            'The selected MQTT broker rejected the sent identifier.'
                        );
                    case chr(3):
                        $this->logger->error(sprintf('The MQTT broker at [%s:%s] is currently unavailable.', $this->host, $this->port));
                        throw new ConnectingToBrokerFailedException(
                            self::EXCEPTION_CONNECTION_BROKER_UNAVAILABLE,
                            'The selected MQTT broker is currently unavailable.'
                        );
                    default:
                        $this->logger->error(sprintf(
                            'The MQTT broker at [%s:%s] responded with an invalid error code [%s].',
                            $this->host,
                            $this->port,
                            $acknowledgement[3]
                        ));
                        throw new ConnectingToBrokerFailedException(
                            self::EXCEPTION_CONNECTION_FAILED,
                            'The selected MQTT broker responded with an invalid error code. A connection could not be established.'
                        );
                }
            } else {
                $this->logger->error(sprintf('The MQTT broker at [%s:%s] refused the connection.', $this->host, $this->port));
                throw new ConnectingToBrokerFailedException(self::EXCEPTION_CONNECTION_FAILED, 'A connection could not be established.');
            }
        } catch (DataTransferException $e) {
            $this->logger->error(sprintf('While connecting to the MQTT broker at [%s:%s], a transfer error occurred.', $this->host, $this->port));
            throw new ConnectingToBrokerFailedException(
                self::EXCEPTION_CONNECTION_FAILED,
                'A connection could not be established due to data transfer issues.'
            );
        }
    }

    /**
     * Builds the connection flags from the inputs and settings.
     *
     * @param string|null $username
     * @param string|null $password
     * @param bool        $sendCleanSessionFlag
     * @return int
     */
    protected function buildConnectionFlags(string $username = null, string $password = null, bool $sendCleanSessionFlag = false): int
    {
        $flags = 0;

        if ($sendCleanSessionFlag) {
            $this->logger->debug('Using the [clean session] flag for the MQTT connection.');
            $flags += 1 << 1; // set the `clean session` flag
        }

        if ($this->settings->hasLastWill()) {
            $this->logger->debug('Using the [will] flag for the MQTT connection.');
            $flags += 1 << 2; // set the `will` flag

            if ($this->settings->requiresQualityOfService()) {
                $this->logger->debug(sprintf('Using QoS level [%s] for the MQTT connection.', $this->settings->getQualityOfServiceLevel()));
                $flags += $this->settings->getQualityOfServiceLevel() << 3; // set the `qos` bits
            }

            if ($this->settings->requiresMessageRetention()) {
                $this->logger->debug('Using the [retain] flag for the MQTT connection.');
                $flags += 1 << 5; // set the `retain` flag
            }
        }

        if ($password !== null) {
            $this->logger->debug('Using the [password] flag for the MQTT connection.');
            $flags += 1 << 6; // set the `has password` flag
        }

        if ($username !== null) {
            $this->logger->debug('Using the [username] flag for the MQTT connection.');
            $flags += 1 << 7; // set the `has username` flag
        }

        return $flags;
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
                static::EXCEPTION_CONNECTION_NOT_ESTABLISHED,
                'The client is not connected to a broker. The requested operation is impossible at this point.'
            );
        }
    }

    /**
     * Sends a ping to the MQTT broker.
     *
     * @return void
     * @throws DataTransferException
     */
    protected function ping(): void
    {
        $this->logger->debug('Sending ping to the MQTT broker to keep the connection alive.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
        ]);

        $this->writeToSocket(chr(0xc0) . chr(0x00));
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

        $this->logger->info(sprintf('Closing the connection to the MQTT broker at [%s:%s].', $this->host, $this->port));

        $this->disconnect();

        if ($this->socket !== null && is_resource($this->socket)) {
            stream_socket_shutdown($this->socket, STREAM_SHUT_WR);
        }

        $this->connected = false;
    }

    /**
     * Sends a disconnect message to the MQTT broker.
     *
     * @return void
     * @throws DataTransferException
     */
    protected function disconnect(): void
    {
        $this->logger->debug('Sending disconnect package to the MQTT broker.', ['broker' => sprintf('%s:%s', $this->host, $this->port)]);

        $this->writeToSocket(chr(0xe0) . chr(0x00));
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
            $messageId = $this->nextMessageId();
            $this->repository->addNewPendingPublishedMessage($messageId, $topic, $message, $qualityOfService, $retain);
        }

        $this->publishMessage($topic, $message, $qualityOfService, $retain, $messageId);
    }

    /**
     * Builds and publishes a message.
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
        $this->logger->debug('Publishing an MQTT message.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
            'topic' => $topic,
            'message' => $message,
            'qos' => $qualityOfService,
            'retain' => $retain,
            'message_id' => $messageId,
            'is_duplicate' => $isDuplicate,
        ]);

        foreach ($this->publishEventHandlers as $handler) {
            call_user_func($handler, $this, $topic, $message, $messageId, $qualityOfService, $retain);
        }

        $i      = 0;
        $buffer = '';

        $topicPart = $this->buildLengthPrefixedString($topic);
        $buffer   .= $topicPart;
        $i        += strlen($topicPart);

        if ($messageId !== null)
        {
            $buffer .= $this->encodeMessageId($messageId); $i += 2;
        }

        $buffer .= $message;
        $i      += strlen($message);

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

        $header = chr($command) . $this->encodeMessageLength($i);

        $this->writeToSocket($header . $buffer);
    }

    /**
     * Subscribe to the given topic with the given quality of service.
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

        $this->logger->debug('Subscribing to an MQTT topic.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
            'topic' => $topic,
            'qos' => $qualityOfService,
        ]);

        $i         = 0;
        $buffer    = '';
        $messageId = $this->nextMessageId();
        $buffer   .= $this->encodeMessageId($messageId); $i += 2;

        $topicPart = $this->buildLengthPrefixedString($topic);
        $buffer   .= $topicPart;
        $i        += strlen($topicPart);
        $buffer   .= chr($qualityOfService); $i++;

        $this->repository->addNewTopicSubscription($topic, $callback, $messageId, $qualityOfService);

        $header  = chr(0x82) . chr($i);

        $this->writeToSocket($header . $buffer);
    }

    /**
     * Unsubscribe from the given topic.
     *
     * @param string $topic
     * @return void
     * @throws DataTransferException
     */
    public function unsubscribe(string $topic): void
    {
        $this->ensureConnected();

        $messageId = $this->nextMessageId();

        $this->repository->addNewPendingUnsubscribeRequest($messageId, $topic);

        $this->sendUnsubscribeRequest($messageId, $topic);
    }

    /**
     * Sends an unsubscribe request to the broker.
     *
     * @param int    $messageId
     * @param string $topic
     * @param bool   $isDuplicate
     * @throws DataTransferException
     */
    protected function sendUnsubscribeRequest(int $messageId, string $topic, bool $isDuplicate = false): void
    {
        $this->logger->debug('Unsubscribing from an MQTT topic.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
            'message_id' => $messageId,
            'topic' => $topic,
            'is_duplicate' => $isDuplicate,
        ]);

        $i      = 0;
        $buffer = $this->encodeMessageId($messageId); $i += 2;

        $topicPart = $this->buildLengthPrefixedString($topic);
        $buffer   .= $topicPart;
        $i        += strlen($topicPart);

        $command = 0xa2 | ($isDuplicate ? 1 << 3 : 0);
        $header  = chr($command) . chr($i);

        $this->writeToSocket($header . $buffer);
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
     * @throws UnexpectedAcknowledgementException
     */
    public function loop(bool $allowSleep = true, bool $exitWhenQueuesEmpty = false, int $queueWaitLimit = null): void
    {
        $this->logger->debug('Starting MQTT client loop.');

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
                call_user_func($handler, $this, $elapsedTime);
            }

            $buffer = null;
            $byte   = $this->readFromSocket(1, true);

            if (strlen($byte) === 0) {
                if($allowSleep){
                    usleep(100000); // 100ms
                }
            } else {
                // Read the first byte of a message (command and flags).
                $command          = (int)(ord($byte) / 16);
                $qualityOfService = (ord($byte) & 0x06) >> 1;

                // Read the second byte of a message (remaining length)
                // If the continuation bit (8) is set on the length byte,
                // another byte will be read as length.
                $length     = 0;
                $multiplier = 1;
                do {
                    $digit       = ord($this->readFromSocket(1));
                    $length     += ($digit & 127) * $multiplier;
                    $multiplier *= 128;
                } while (($digit & 128) !== 0);

                // Read the remaining message according to the length information.
                if ($length) {
                    $buffer = $this->readFromSocket($length);
                }

                // Handle the received command according to the $command identifier.
                if ($command > 0 && $command < 15) {
                    switch($command){
                        case 2:
                            throw new UnexpectedAcknowledgementException(self::EXCEPTION_ACK_CONNECT, 'We unexpectedly received a connection acknowledgement.');
                        case 3:
                            $this->handlePublishedMessage($buffer, $qualityOfService);
                            break;
                        case 4:
                            $this->handlePublishAcknowledgement($buffer);
                            break;
                        case 5:
                            $this->handlePublishReceipt($buffer);
                            break;
                        case 6:
                            $this->handlePublishRelease($buffer);
                            break;
                        case 7:
                            $this->handlePublishCompletion($buffer);
                            break;
                        case 9:
                            $this->handleSubscribeAcknowledgement($buffer);
                            break;
                        case 11:
                            $this->handleUnsubscribeAcknowledgement($buffer);
                            break;
                        case 12:
                            $this->handlePingRequest();
                            break;
                        case 13;
                            $this->handlePingAcknowledgement();
                            break;
                        default:
                            $this->logger->debug(sprintf('Received message with unsupported command [%s]. Skipping.', $command));
                            break;
                    }
                } else {
                    $this->logger->error('A reserved command has been received from an MQTT broker. Supported are commands (including) 1-14.', [
                        'broker' => sprintf('%s:%s', $this->host, $this->port),
                        'command' => $command,
                    ]);
                }
            }

            // If the last message of the broker has been received more seconds ago
            // than specified by the keep alive time, we will send a ping to ensure
            // the connection is kept alive.
            if ($this->lastPingAt < (microtime(true) - $this->settings->getKeepAlive())) {
                $this->ping();
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
                $this->logger->error(sprintf(
                    'Received a published message with QoS level [%s] from an MQTT broker, but without a message identifier.',
                    $qualityOfServiceLevel
                ));

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
        $this->logger->debug('Handling publish acknowledgement received from an MQTT broker.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
        ]);

        if (strlen($buffer) !== 2) {
            $this->logger->notice('Received invalid publish acknowledgement from an MQTT broker.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
            ]);
            throw new UnexpectedAcknowledgementException(
                self::EXCEPTION_ACK_PUBLISH,
                'The MQTT broker responded with an invalid publish acknowledgement.'
            );
        }

        $messageId = $this->decodeMessageId($this->pop($buffer, 2));

        $result = $this->repository->removePendingPublishedMessage($messageId);
        if ($result === false) {
            $this->logger->notice('Received publish acknowledgement from an MQTT broker for already acknowledged message.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
            ]);
            throw new UnexpectedAcknowledgementException(
                self::EXCEPTION_ACK_PUBLISH,
                'The MQTT broker acknowledged a publish that has not been pending anymore.'
            );
        }
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
        $this->logger->debug('Handling publish receipt from an MQTT broker.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
        ]);

        if (strlen($buffer) !== 2) {
            $this->logger->notice('Received invalid publish receipt from an MQTT broker.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
            ]);
            throw new UnexpectedAcknowledgementException(
                self::EXCEPTION_ACK_RECEIVE,
                'The MQTT broker responded with an invalid publish receipt.'
            );
        }

        $messageId = $this->decodeMessageId($this->pop($buffer, 2));

        $result = $this->repository->markPendingPublishedMessageAsReceived($messageId);
        if ($result === false) {
            $this->logger->notice('Received publish receipt from an MQTT broker for already acknowledged message.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
            ]);
            throw new UnexpectedAcknowledgementException(
                self::EXCEPTION_ACK_RECEIVE,
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
        $this->logger->debug('Handling publish release received from an MQTT broker.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
        ]);

        if (strlen($buffer) !== 2) {
            $this->logger->notice('Received invalid publish release from an MQTT broker.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
            ]);
            throw new UnexpectedAcknowledgementException(
                self::EXCEPTION_ACK_RELEASE,
                'The MQTT broker responded with an invalid publish release message.'
            );
        }

        $messageId = $this->decodeMessageId($this->pop($buffer, 2));

        $message = $this->repository->getPendingPublishConfirmationWithMessageId($messageId);

        $result = $this->repository->removePendingPublishConfirmation($messageId);
        if ($message === null || $result === false) {
            $this->logger->notice('Received publish release from an MQTT broker for already released message.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
            ]);
            throw new UnexpectedAcknowledgementException(
                self::EXCEPTION_ACK_RELEASE,
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
        $this->logger->debug('Handling publish completion from an MQTT broker.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
        ]);

        if (strlen($buffer) !== 2) {
            $this->logger->notice('Received invalid publish completion from an MQTT broker.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
            ]);
            throw new UnexpectedAcknowledgementException(
                self::EXCEPTION_ACK_COMPLETE,
                'The MQTT broker responded with an invalid publish completion.'
            );
        }

        $messageId = $this->decodeMessageId($this->pop($buffer, 2));

        $result = $this->repository->removePendingPublishedMessage($messageId);
        if ($result === false) {
            $this->logger->notice('Received publish completion from an MQTT broker for already acknowledged message.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
            ]);
            throw new UnexpectedAcknowledgementException(
                self::EXCEPTION_ACK_COMPLETE,
                'The MQTT broker sent a completion for a publish that has not been pending anymore.'
            );
        }
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
        $this->logger->debug('Handling subscribe acknowledgement received from an MQTT broker.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
        ]);

        if (strlen($buffer) < 3) {
            $this->logger->notice('Received invalid subscribe acknowledgement from an MQTT broker.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
            ]);
            throw new UnexpectedAcknowledgementException(
                self::EXCEPTION_ACK_SUBSCRIBE,
                'The MQTT broker responded with an invalid subscribe acknowledgement.'
            );
        }

        $messageId        = $this->decodeMessageId($this->pop($buffer, 2));
        $subscriptions    = $this->repository->getTopicSubscriptionsWithMessageId($messageId);
        $acknowledgements = str_split($buffer);

        if (count($acknowledgements) !== count($subscriptions)) {
            $this->logger->notice('Received subscribe acknowledgement from an MQTT broker with wrong number of QoS acknowledgements.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
                'required' => count($subscriptions),
                'received' => count($acknowledgements),
            ]);
            throw new UnexpectedAcknowledgementException(
                self::EXCEPTION_ACK_SUBSCRIBE,
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
        $this->logger->debug('Handling unsubscribe acknowledgement received from an MQTT broker.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
        ]);

        if (strlen($buffer) !== 2) {
            $this->logger->notice('Received invalid unsubscribe acknowledgement from an MQTT broker.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
            ]);
            throw new UnexpectedAcknowledgementException(
                self::EXCEPTION_ACK_PUBLISH,
                'The MQTT broker responded with an invalid unsubscribe acknowledgement.'
            );
        }

        $messageId = $this->decodeMessageId($this->pop($buffer, 2));

        $unsubscribeRequest = $this->repository->getPendingUnsubscribeRequestWithMessageId($messageId);
        $result             = $this->repository->removePendingUnsubscribeRequest($messageId);
        if ($result === false) {
            $this->logger->notice('Received unsubscribe acknowledgement from an MQTT broker for already acknowledged unsubscribe request.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
            ]);
            throw new UnexpectedAcknowledgementException(
                self::EXCEPTION_ACK_PUBLISH,
                'The MQTT broker acknowledged an unsubscribe request that has not been pending anymore.'
            );
        }

        if ($unsubscribeRequest !== null) {
            $this->repository->removeTopicSubscription($unsubscribeRequest->getTopic());
        }
    }

    /**
     * Handles a received ping request. Simply sends an acknowledgement.
     *
     * @return void
     * @throws DataTransferException
     */
    protected function handlePingRequest(): void
    {
        $this->logger->debug('Received ping request from an MQTT broker. Sending response.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
        ]);

        $this->writeToSocket(chr(0xd0) . chr(0x00));
    }

    /**
     * Handles a received ping acknowledgement.
     *
     * @return void
     */
    protected function handlePingAcknowledgement(): void
    {
        $this->logger->debug('Received ping acknowledgement from an MQTT broker.', ['broker' => sprintf('%s:%s', $this->host, $this->port)]);
    }

    /**
     * Delivers a published message to subscribed callbacks.
     *
     * @param string $topic
     * @param string $message
     * @param int    $qualityOfServiceLevel
     * @return void
     */
    protected function deliverPublishedMessage(string $topic, string $message, int $qualityOfServiceLevel): void
    {
        $subscribers = $this->repository->getTopicSubscriptionsMatchingTopic($topic);

        $this->logger->debug('Delivering published message received from an MQTT broker.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
            'topic' => $topic,
            'message' => $message,
            'subscribers' => count($subscribers),
        ]);

        foreach ($subscribers as $subscriber) {
            if ($subscriber->getQualityOfServiceLevel() > $qualityOfServiceLevel) {
                // At this point we need to assume that this subscriber does not want to receive
                // the message, but maybe there are other subscribers waiting for the message.
                continue;
            }

            try {
                call_user_func($subscriber->getCallback(), $topic, $message);
            } catch (\Throwable $e) {
                // We ignore errors produced by custom callbacks.
            }
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
        $this->logger->debug('Sending publish acknowledgement to an MQTT broker.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
            'message_id' => $messageId,
        ]);

        $this->writeToSocket(chr(0x40) . chr(0x02) . $this->encodeMessageId($messageId));
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
        $this->logger->debug('Sending publish received message to an MQTT broker.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
            'message_id' => $messageId,
        ]);

        $this->writeToSocket(chr(0x50) . chr(0x02) . $this->encodeMessageId($messageId));
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
        $this->logger->debug('Sending publish received message to an MQTT broker.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
            'message_id' => $messageId,
        ]);

        $this->writeToSocket(chr(0x70) . chr(0x02) . $this->encodeMessageId($messageId));
    }

    /**
     * Republishes pending messages.
     *
     * @return void
     * @throws DataTransferException
     */
    protected function republishPendingMessages(): void
    {
        $this->logger->debug('Re-publishing pending messages to MQTT broker.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
        ]);

        /** @noinspection PhpUnhandledExceptionInspection */
        $dateTime = (new DateTime())->sub(new DateInterval('PT' . $this->settings->getResendTimeout() . 'S'));
        $messages = $this->repository->getPendingPublishedMessagesLastSentBefore($dateTime);

        foreach ($messages as $message) {
            $this->logger->debug('Re-publishing pending message to MQTT broker.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
                'message_id' => $message->getMessageId(),
            ]);

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
        $this->logger->debug('Re-sending pending unsubscribe requests to MQTT broker.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
        ]);

        /** @noinspection PhpUnhandledExceptionInspection */
        $dateTime = (new DateTime())->sub(new DateInterval('PT' . $this->settings->getResendTimeout() . 'S'));
        $requests = $this->repository->getPendingUnsubscribeRequestsLastSentBefore($dateTime);

        foreach ($requests as $request) {
            $this->logger->debug('Re-sending pending unsubscribe request to MQTT broker.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
                'message_id' => $request->getMessageId(),
            ]);

            $this->sendUnsubscribeRequest($request->getMessageId(), $request->getTopic(), true);

            $request->setLastSentAt(new DateTime());
            $request->incrementSendingAttempts();
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
     * Returns the certificate authority file, if available.
     *
     * @return string|null
     */
    public function getCertificateAuthorityFile(): ?string
    {
        return $this->caFile;
    }

    /**
     * Determines whether a certificate authority file is available.
     *
     * @return bool
     */
    public function hasCertificateAuthorityFile(): bool
    {
        return $this->getCertificateAuthorityFile() !== null;
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
        if ($length === null) {
            $length = strlen($data);
        }

        $length = min($length, strlen($data));

        $result = @fwrite($this->socket, $data, $length);

        if ($result === false || $result !== $length) {
            $this->logger->error('Sending data over the socket to an MQTT broker failed.', [
                'broker' => sprintf('%s:%s', $this->host, $this->port),
            ]);
            throw new DataTransferException(self::EXCEPTION_TX_DATA, 'Sending data over the socket failed. Has it been closed?');
        }

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
        $result      = '';
        $remaining   = $limit;

        if ($withoutBlocking) {
            $receivedData = fread($this->socket, $remaining);
            if ($receivedData === false) {
                $this->logger->error('Reading data from the socket from an MQTT broker failed.', [
                    'broker' => sprintf('%s:%s', $this->host, $this->port),
                ]);
                throw new DataTransferException(self::EXCEPTION_RX_DATA, 'Reading data from the socket failed. Has it been closed?');
            }
            return $receivedData;
        }

        while (feof($this->socket) === false && $remaining > 0) {
            $receivedData = fread($this->socket, $remaining);
            if ($receivedData === false) {
                $this->logger->error('Reading data from the socket from an MQTT broker failed.', [
                    'broker' => sprintf('%s:%s', $this->host, $this->port),
                ]);
                throw new DataTransferException(self::EXCEPTION_RX_DATA, 'Reading data from the socket failed. Has it been closed?');
            }
            $result .= $receivedData;
            $remaining = $limit - strlen($result);
        }

        return $result;
    }
}
