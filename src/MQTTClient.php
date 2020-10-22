<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use DateInterval;
use DateTime;
use PhpMqtt\Client\Concerns\OffersHooks;
use PhpMqtt\Client\Contracts\MQTTClient as ClientContract;
use PhpMqtt\Client\Contracts\Repository;
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
    use OffersHooks;

    const EXCEPTION_CONNECTION_SOCKET_ERROR        = 1000;
    const EXCEPTION_CONNECTION_TLS_ERROR           = 2000;
    const EXCEPTION_CONNECTION_FAILED              = 0001;
    const EXCEPTION_CONNECTION_PROTOCOL_VERSION    = 0002;
    const EXCEPTION_CONNECTION_IDENTIFIER_REJECTED = 0003;
    const EXCEPTION_CONNECTION_BROKER_UNAVAILABLE  = 0004;
    const EXCEPTION_CONNECTION_BAD_LOGIN           = 0005;
    const EXCEPTION_CONNECTION_NOT_AUTHORIZED      = 0006;
    const EXCEPTION_CONNECTION_UNKNOWN_ERROR       = 0400;
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

    /** @var resource|null */
    protected $socket;

    /**
     * Constructs a new MQTT client which subsequently supports publishing and subscribing.
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
     * In case there is an existing connection, it will be abruptly closed and
     * replaced with the new one, as `disconnect()` should be called first.
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
        // Always abruptly close any previous connection if we are opening a new one.
        // The caller should make sure this does not happen.
        $this->closeSocket();

        $this->logger->info(sprintf('Connecting to MQTT broker [%s:%s].', $this->host, $this->port));

        $this->settings = $settings ?? new ConnectionSettings();

        $this->establishSocketConnection();
        $this->performConnectionHandshake($username, $password, $sendCleanSessionFlag);
    }

    /**
     * Opens a socket that connects to the host and port set on the object.
     *
     * @return void
     * @throws ConnectingToBrokerFailedException
     */
    protected function establishSocketConnection(): void
    {
        $useTls = ($this->settings->shouldUseTls() || $this->hasCertificateAuthorityFile());

        $contextOptions = [];

        if ($useTls) {
            $contextOptions['ssl'] = [];

            if ($this->settings->shouldTlsVerifyPeer() || $this->hasCertificateAuthorityFile()) {
                // As it does not make any sense to specify a certificate file without
                // enabling peer verification, we automatically enable it in that case.
                $contextOptions['ssl']['verify_peer'] = true;
            } else {
                // You get a warning output for free, on every connection.
                $this->logger->warning('TLS encryption without peer verification enabled - POTENTIAL SECURITY ISSUE.');
                $contextOptions['ssl']['verify_peer'] = false;
            }

            $contextOptions['ssl']['verify_peer_name'] = $this->settings->shouldTlsVerifyPeerName();

            if ($this->hasCertificateAuthorityFile()) {
                $this->logger->info(sprintf('Using certificate authority file [%s] to verify peer name.', $this->getCertificateAuthorityFile()));
                $contextOptions['ssl']['cafile'] = $this->getCertificateAuthorityFile();
            }
            
            if ($this->isTlsClientCertificateConfigurationValid($this->settings)) {
                if ($this->settings->getTlsClientCertificateFile() !== null) {
                    $contextOptions['ssl']['local_cert'] = $this->settings->getTlsClientCertificateFile();
                }

                if ($this->settings->getTlsClientCertificateKeyFile() !== null) {
                    $contextOptions['ssl']['local_pk'] = $this->settings->getTlsClientCertificateKeyFile();
                }

                if ($this->settings->getTlsClientCertificatePassphrase() !== null) {
                    $contextOptions['ssl']['passphrase'] = $this->settings->getTlsClientCertificatePassphrase();
                }
            }
        }

        $connectionString = 'tcp://' . $this->getHost() . ':' . $this->getPort();
        $socketContext    = stream_context_create($contextOptions);
        $socket           = @stream_socket_client(
            $connectionString,
            $errorCode,
            $errorMessage,
            $this->settings->getSocketTimeout(),
            STREAM_CLIENT_CONNECT,
            $socketContext
        );

        if ($socket === false) {
            $this->logger->error(sprintf(
                'Establishing a connection with the MQTT broker using connection string [%s] failed: %s (code %d).',
                $connectionString,
                $errorMessage,
                $errorCode
            ));
            // errorCode is a POSIX socket error (0-255)
            throw new ConnectingToBrokerFailedException(
                self::EXCEPTION_CONNECTION_SOCKET_ERROR,
                sprintf('Socket error [%d]: %s', $errorCode, $errorMessage),
                $errorMessage,
                (string) $errorCode
            );
        }

        if ($useTls) {
            // Since stream_socket_enable_crypto() communicates errors using error_get_last(),
            // we need to clear a potentially set error at this point to be sure the error we
            // retrieve in the error handling part is actually of this function call and not
            // from some unrelated code of the users application.
            error_clear_last();

            $retval = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_ANY_CLIENT);
            if ($retval === false) {
                // At this point, PHP should have given us something like this:
                //     SSL operation failed with code 1. OpenSSL Error messages:
                //     error:1416F086:SSL routines:tls_process_server_certificate:certificate verify failed
                // We need to get our hands dirty and extract the OpenSSL error
                // from the PHP message, which luckily gives us a handy newline.
                $this->parseTlsErrorMessage(error_get_last(), $tlsErrorCode, $tlsErrorMessage);

                // Before returning an exception, we need to close the already opened socket.
                fclose($socket);

                $this->logger->error(sprintf(
                    'Enabling TLS on the connection with the MQTT broker [%s] failed: %s (code %s).',
                    $connectionString,
                    $tlsErrorMessage,
                    $tlsErrorCode
                ));
                throw new ConnectingToBrokerFailedException(
                    self::EXCEPTION_CONNECTION_TLS_ERROR,
                    sprintf('TLS error [%s]: %s', $tlsErrorCode, $tlsErrorMessage),
                    $tlsErrorMessage,
                    $tlsErrorCode
                );
            }
        }

        stream_set_timeout($socket, $this->settings->getSocketTimeout());
        stream_set_blocking($socket, $this->settings->wantsToBlockSocket());

        $this->socket = $socket;
    }

    /**
     * Validates the TLS configuration of the client certificate. The configuration is considered valid
     * if the path to a valid certificate file, a valid key file and, if required, a passphrase is given.
     * A combined file with the client certificate and its key is not supported.
     *
     * If no configuration for a client certificate is given at all, the configuration is also valid.
     *
     * Warnings will be written to the log in case of an invalid configuration.
     *
     * @param ConnectionSettings $settings
     * @return bool
     */
    private function isTlsClientCertificateConfigurationValid(ConnectionSettings $settings): bool
    {
        $certificateFile = $settings->getTlsClientCertificateFile();
        $keyFile         = $settings->getTlsClientCertificateKeyFile();

        // No certificate and key files given, the configuration is valid.
        if ($certificateFile === null && $keyFile === null) {
            return true;
        }

        // The given client certificate file path is invalid.
        if ($certificateFile === null || !is_file($certificateFile)) {
            $this->logger->warning('The client certificate file setting must contain the path to a regular file.');
            return false;
        }

        // The given client certificate key file path is invalid.
        if ($keyFile === null || !is_file($keyFile)) {
            $this->logger->warning('The client certificate key file setting must contain the path to a regular file.');
            return false;
        }

        // If the openssl extension is available, we can actually verify if the key matches the certificate.
        if (function_exists('openssl_x509_check_private_key')) {
            $certificate = file_get_contents($certificateFile);
            $key         = file_get_contents($keyFile);
            $passphrase  = $settings->getTlsClientCertificatePassphrase();

            if (!openssl_x509_check_private_key($certificate, ($passphrase !== null) ? [$key, $passphrase] : $key)) {
                return false;
            }
        }

        return true;
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

        if (!preg_match('/:\n(?:error:([0-9A-Z]+):)?(.+)$/', $phpError['message'], $regp)) {
            $tlsErrorCode    = "UNKNOWN:2";
            $tlsErrorMessage = $phpError['message'];
            return;
        }

        if ($regp[1] == "") {
            $tlsErrorCode    = "UNKNOWN:3";
            $tlsErrorMessage = $regp[2];
            return;
        }

        $tlsErrorCode    = $regp[1];
        $tlsErrorMessage = $regp[2];
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
    protected function performConnectionHandshake(
        string $username = null,
        string $password = null,
        bool $sendCleanSessionFlag = false
    ): void
    {
        try {
            $buffer = '';

            // protocol header
            $buffer .= chr(0x00); // length of protocol name 1
            $buffer .= chr(0x06); // length of protocol name 2
            $buffer .= chr(0x4d); // protocol name: M
            $buffer .= chr(0x51); // protocol name: Q
            $buffer .= chr(0x49); // protocol name: I
            $buffer .= chr(0x73); // protocol name: s
            $buffer .= chr(0x64); // protocol name: d
            $buffer .= chr(0x70); // protocol name: p
            $buffer .= chr(0x03); // protocol version (3.1)

            // connection flags
            $buffer .= chr($this->buildConnectionFlags($username, $password, $sendCleanSessionFlag));

            // keep alive settings
            $buffer .= chr($this->settings->getKeepAlive() >> 8);
            $buffer .= chr($this->settings->getKeepAlive() & 0xff);

            // client id (connection identifier)
            $buffer .= $this->buildLengthPrefixedString($this->clientId);

            // last will topic and message
            if ($this->settings->hasLastWill()) {
                $buffer .= $this->buildLengthPrefixedString($this->settings->getLastWillTopic());
                $buffer .= $this->buildLengthPrefixedString($this->settings->getLastWillMessage());
            }

            // credentials
            if ($username !== null) {
                $buffer .= $this->buildLengthPrefixedString($username);
            }
            if ($password !== null) {
                $buffer .= $this->buildLengthPrefixedString($password);
            }

            // message type and message length
            $header = chr(0x10) . $this->encodeMessageLength(strlen($buffer));

            // send the connection message
            $this->logger->info('Sending connection handshake to MQTT broker.');
            $this->writeToSocket($header . $buffer);

            // read and process the acknowledgement
            $acknowledgement = $this->readFromSocket(4);
            if ($acknowledgement && (ord($acknowledgement[0]) >> 4 === 2)) {
                switch (ord($acknowledgement[3])) {
                    case 0:
                        $this->logger->info(sprintf('Connection with MQTT broker at [%s:%s] established successfully.', $this->host, $this->port));
                        break;
                    case 1:
                        $this->logger->error(sprintf('The MQTT broker at [%s:%s] does not support MQTT v3.', $this->host, $this->port));
                        throw new ConnectingToBrokerFailedException(
                            self::EXCEPTION_CONNECTION_PROTOCOL_VERSION,
                            'Connection refused: Protocol version 3.1 is not supported.'
                        );
                    case 2:
                        $this->logger->error(sprintf('The MQTT broker at [%s:%s] rejected the sent client identifier.', $this->host, $this->port));
                        throw new ConnectingToBrokerFailedException(
                            self::EXCEPTION_CONNECTION_IDENTIFIER_REJECTED,
                            'Connection refused: Client identifier rejected.'
                        );
                    case 3:
                        $this->logger->error(sprintf('The MQTT broker at [%s:%s] is currently unavailable.', $this->host, $this->port));
                        throw new ConnectingToBrokerFailedException(
                            self::EXCEPTION_CONNECTION_BROKER_UNAVAILABLE,
                            'Connection refused: Service currently not available.'
                        );
                    case 4:
                        $this->logger->error(sprintf('The MQTT broker at [%s:%s] reported bad username or password.', $this->host, $this->port));
                        throw new ConnectingToBrokerFailedException(
                            self::EXCEPTION_CONNECTION_BAD_LOGIN,
                            'Connection refused: Bad username or password.'
                        );
                    case 5:
                        $this->logger->error(sprintf('The MQTT broker at [%s:%s] denied our connection.', $this->host, $this->port));
                        throw new ConnectingToBrokerFailedException(
                            self::EXCEPTION_CONNECTION_NOT_AUTHORIZED,
                            'Connection refused: Not authorized.'
                        );
                    default:
                        $this->logger->error(sprintf(
                            'The MQTT broker at [%s:%s] responded with an invalid error code [0x%02x].',
                            $this->host,
                            $this->port,
                            ord($acknowledgement[3])
                        ));
                        throw new ConnectingToBrokerFailedException(
                            self::EXCEPTION_CONNECTION_UNKNOWN_ERROR,
                            sprintf('Connection Refused: Unknown reason 0x%02x', ord($acknowledgement[3]))
                        );
                }
            } else {
                // The server might have closed the connection without reason, or it might have sent something other
                // than CONNACK, which would be a protocol violation. Thus, we don't need to distinguish.
                $this->logger->error(sprintf('The MQTT broker at [%s:%s] closed the connection without reason.', $this->host, $this->port));
                throw new ConnectingToBrokerFailedException(
                    self::EXCEPTION_CONNECTION_FAILED,
                    'Connection closed without reason.'
                );
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
        $this->logger->info(sprintf('Closing the connection to the MQTT broker at [%s:%s].', $this->host, $this->port));

        $this->disconnect();

        if ($this->socket !== null && is_resource($this->socket)) {
            stream_socket_shutdown($this->socket, STREAM_SHUT_WR);
        }
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
        $messageId = null;

        if ($qualityOfService > 0) {
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

        $buffer = '';

        $buffer .= $this->buildLengthPrefixedString($topic);

        if ($messageId !== null) {
            $buffer .= $this->encodeMessageId($messageId);
        }

        $buffer .= $message;

        $command = 0x30;
        if ($retain) {
            $command |= 1 << 0;
        }
        if ($qualityOfService > 0) {
            $command |= $qualityOfService << 1;
        }
        if ($isDuplicate) {
            $command |= 1 << 3;
        }

        $header = chr($command) . $this->encodeMessageLength(strlen($buffer));

        $this->writeToSocket($header . $buffer);
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
    public function subscribe(string $topic, callable $callback, int $qualityOfService = 0): void
    {
        $this->logger->debug('Subscribing to an MQTT topic.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
            'topic' => $topic,
            'qos' => $qualityOfService,
        ]);

        $messageId = $this->nextMessageId();

        $buffer = '';

        $buffer .= $this->encodeMessageId($messageId);

        $buffer .= $this->buildLengthPrefixedString($topic);

        $buffer .= chr($qualityOfService);

        $this->repository->addNewTopicSubscription($topic, $callback, $messageId, $qualityOfService);

        $header = chr(0x82) . $this->encodeMessageLength(strlen($buffer));

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

        $buffer = '';

        $buffer .= $this->encodeMessageId($messageId);

        $buffer .= $this->buildLengthPrefixedString($topic);

        $command = 0xa2 | ($isDuplicate ? 1 << 3 : 0);

        $header = chr($command) . $this->encodeMessageLength(strlen($buffer));

        $this->writeToSocket($header . $buffer);
    }

    /**
     * Returns the next time the broker expects to be pinged.
     *
     * @return float
     */
    protected function nextPingAt(): float
    {
        return ($this->lastPingAt + $this->settings->getKeepAlive());
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
                if ($allowSleep) {
                    usleep(100000); // 100ms
                }
            } else {
                // Read the first byte of a message (command and flags).
                $command          = (int) (ord($byte) / 16);
                $qualityOfService = (ord($byte) & 0x06) >> 1;
                $retained         = (bool) (ord($byte) & 0x01);

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
                    switch ($command) {
                        case 2:
                            throw new UnexpectedAcknowledgementException(
                                self::EXCEPTION_ACK_CONNECT,
                                'We unexpectedly received a connection acknowledgement.'
                            );
                        case 3:
                            $this->handlePublishedMessage($buffer, $qualityOfService, $retained);
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
                        case 13:
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
     * @param bool   $retained
     * @return void
     * @throws DataTransferException
     */
    protected function handlePublishedMessage(string $buffer, int $qualityOfServiceLevel, bool $retained = false): void
    {
        $topicLength = (ord($buffer[0]) << 8) + ord($buffer[1]);
        $topic       = substr($buffer, 2, $topicLength);
        $message     = substr($buffer, ($topicLength + 2));

        if ($qualityOfServiceLevel > 0) {
            if (strlen($message) < 2) {
                $this->logger->error(sprintf(
                    'Received a published message with QoS level [%s] from an MQTT broker, but without a message identifier.',
                    $qualityOfServiceLevel
                ));

                // This message seems to be incomplete or damaged. We ignore it and wait for a retransmission,
                // which will occur at some point due to QoS level > 0.
                return;
            }

            $messageId = $this->stringToNumber($this->pop($message, 2));

            if ($qualityOfServiceLevel === 1) {
                $this->sendPublishAcknowledgement($messageId);
            }

            if ($qualityOfServiceLevel === 2) {
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

        $this->deliverPublishedMessage($topic, $message, $qualityOfServiceLevel, $retained);
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

        $messageId = $this->stringToNumber($this->pop($buffer, 2));

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

        $messageId = $this->stringToNumber($this->pop($buffer, 2));

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

        $this->sendPublishRelease($messageId);
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

        $messageId = $this->stringToNumber($this->pop($buffer, 2));

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

        $messageId = $this->stringToNumber($this->pop($buffer, 2));

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

        $messageId        = $this->stringToNumber($this->pop($buffer, 2));
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

        $messageId = $this->stringToNumber($this->pop($buffer, 2));

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
     * @param bool   $retained
     * @return void
     */
    protected function deliverPublishedMessage(string $topic, string $message, int $qualityOfServiceLevel, bool $retained = false): void
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
                call_user_func($subscriber->getCallback(), $topic, $message, $retained);
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
     * Sends a publish release message for the given message identifier.
     *
     * @param int $messageId
     * @return void
     * @throws DataTransferException
     */
    protected function sendPublishRelease(int $messageId): void
    {
        $this->logger->debug('Sending publish release message to an MQTT broker.', [
            'broker' => sprintf('%s:%s', $this->host, $this->port),
            'message_id' => $messageId,
        ]);

        $this->writeToSocket(chr(0x62) . chr(0x02) . $this->encodeMessageId($messageId));
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
     * Converts the given string to a number, assuming it is an MSB encoded
     * number. This means preceding characters have higher value.
     *
     * @param string $buffer
     * @return int
     */
    protected function stringToNumber(string $buffer): int
    {
        $length = strlen($buffer);
        $result = 0;

        foreach (str_split($buffer) as $index => $char) {
            $result += ord($char) << (($length - 1) * 8 - ($index * 8));
        }

        return $result;
    }

    /**
     * Encodes the given message identifier as string.
     *
     * @param int $messageId
     * @return string
     */
    protected function encodeMessageId(int $messageId): string
    {
        return chr($messageId >> 8) . chr($messageId % 256);
    }

    /**
     * Encodes the length of a message as string, so it can be transmitted
     * over the wire.
     *
     * @param int $length
     * @return string
     */
    protected function encodeMessageLength(int $length): string
    {
        $result = '';

        do {
            $digit  = $length % 128;
            $length = $length >> 7;

            // if there are more digits to encode, set the top bit of this digit
            if ($length > 0) {
                $digit = ($digit | 0x80);
            }

            $result .= chr($digit);
        } while ($length > 0);

        return $result;
    }

    /**
     * Gets the next message id to be used.
     *
     * @return int
     */
    protected function nextMessageId(): int
    {
        if ($this->messageId === 65535) {
            $this->messageId = 0;
        }

        return $this->messageId++;
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
        $result    = '';
        $remaining = $limit;

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

        // Before entering busy waiting for data in blocking mode, define when to timeout.
        $timeout = null;
        if ($this->settings->getSocketTimeout() > 0) {
            $timeout = microtime(true) + $this->settings->getSocketTimeout();
        }

        while (feof($this->socket) === false && $remaining > 0) {
            $receivedData = fread($this->socket, $remaining);
            if ($receivedData === false) {
                $this->logger->error('Reading data from the socket from an MQTT broker failed.', [
                    'broker' => sprintf('%s:%s', $this->host, $this->port),
                ]);
                throw new DataTransferException(self::EXCEPTION_RX_DATA, 'Reading data from the socket failed. Has it been closed?');
            }
            $result   .= $receivedData;
            $remaining = $limit - strlen($result);

            // If the server is delaying the expected data, we do not want to enter busy waiting.
            if ($remaining > 0) {
                usleep(10000); // 10ms
                if ($timeout !== null && microtime(true) >= $timeout) {
                    throw new DataTransferException(self::EXCEPTION_RX_DATA, 'Timed out while reading data from the socket.');
                }
            }
        }

        return $result;
    }

    /**
     * Closes the socket connection immediately without flushing data queues.
     *
     * @return void
     */
    protected function closeSocket(): void
    {
        if ($this->socket) {
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
     * Creates a string which is prefixed with its own length as bytes.
     * This means a string like 'hello world' will become
     *
     *   \x00\x0bhello world
     *
     * where \x00\0x0b is the hex representation of 00000000 00001011 = 11
     *
     * @param string $data
     * @return string
     */
    protected function buildLengthPrefixedString(string $data): string
    {
        $length = strlen($data);
        $msb    = $length >> 8;
        $lsb    = $length % 256;

        return chr($msb) . chr($lsb) . $data;
    }

    /**
     * Pops the first $limit bytes from the given buffer and returns them.
     *
     * @param string $buffer
     * @param int    $limit
     * @return string
     */
    protected function pop(string &$buffer, int $limit): string
    {
        $limit = min(strlen($buffer), $limit);

        $result = substr($buffer, 0, $limit);
        $buffer = substr($buffer, $limit);

        return $result;
    }

    /**
     * Sets the interrupted signal.
     *
     * @return void
     */
    public function interrupt(): void
    {
        $this->interrupted = true;
    }

    /**
     * Shifts the last $limit bytes from the given buffer and returns them.
     *
     * @param string $buffer
     * @param int    $limit
     * @return string
     */
    protected function shift(string &$buffer, int $limit): string
    {
        $limit = min(strlen($buffer), $limit);

        $result = substr($buffer, $limit * (-1));
        $buffer = substr($buffer, 0, $limit * (-1));

        return $result;
    }

    /**
     * Generates a random client id in the form of an md5 hash.
     *
     * @return string
     */
    protected function generateRandomClientId(): string
    {
        return substr(md5(uniqid((string) mt_rand(), true)), 0, 20);
    }
}
