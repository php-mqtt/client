<?php

declare(strict_types=1);

namespace Namoshek\MQTT;

// TODO: add logging using a PSR logging interface

class MQTTClient
{
    const EXCEPTION_CONNECTION_FAILED = 0001;
    const EXCEPTION_TX_DATA           = 0101;
    const EXCEPTION_RX_DATA           = 0102;
    const EXCEPTION_ACK_SUBSCRIBE     = 0201;

    /** @var string */
    private $host;

    /** @var int */
    private $port;

    /** @var string */
    private $clientId;

    /** @var MQTTConnectionSettings|null */
    private $settings;

    /** @var string|null */
    private $caFile;

    /** @var resource|null */
    private $socket;

    /** @var DateTime|null */
    private $lastPingAt;

    /** @var int */
    private $messageId = 1;

    /** @var MQTTTopicSubscription[] */
    private $subscribedTopics = [];

    /**
     * Constructs a new MQTT client which subsequently supports publishing and subscribing.
     * 
     * @param string      $host
     * @param int         $port
     * @param string|null $clientId
     * @param string|null $caFile
     */
    public function __construct(string $host, int $port = 1883, string $clientId = null, string $caFile = null)
    {
        $this->host     = $host;
        $this->port     = $port;
        $this->clientId = $clientId ?? $this->generateRandomClientId();
        $this->caFile   = $caFile;
    }

    /**
     * Connect to the MQTT broker using the given credentials and settings.
     * 
     * @param string|null            $username
     * @param string|null            $password
     * @param MQTTConnectionSettings $settings
     * @param bool                   $sendCleanSessionFlag
     * @return void
     * @throws ConnectingToBrokerFailedException
     */
    public function connect(string $username = null, string $password = null, MQTTConnectionSettings $settings = null, bool $sendCleanSessionFlag = false): void
    {
        $this->settings = $settings ?? new MQTTConnectionSettings();

        $this->openSocket();
        $this->sendConnectionMessage($username, $password, $sendCleanSessionFlag);
    }

    /**
     * Opens a socket that connects to the host and port set on the object.
     * 
     * @return void
     * @throws ConnectingToBrokerFailedException
     */
    protected function openSocket(): void
    {
        // TODO: add logging
        // TODO: build Uri object from PSR

        if ($this->hasCertificateAuthorityFile()) {
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
            throw new ConnectingToBrokerFailedException($errorCode, $errorMessage);
        }

        stream_set_timeout($this->socket, $this->settings->getSocketTimeout());
        stream_set_blocking($this->socket, $this->settings->wantsToBlockSocket());
    }

    /**
     * Sends a connection message over the socket.
     * If the socket connection is not established, an exception is thrown.
     * 
     * @param string|null $username
     * @param string|null $password
     * @param bool        $sendCleanSessionFlag
     * @return void
     * @throws DataTransferException
     */
    protected function sendConnectionMessage(string $username = null, string $password = null, bool $sendCleanSessionFlag): void
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
                $i           .= strlen($usernamePart);
            }
            if ($password !== null) {
                $passwordPart = $this->buildLengthPrefixedString($password);
                $buffer      .= $passwordPart;
                $i           .= strlen($passwordPart);
            }

            // message type and message length
            $header = chr(0x10) . chr($i);

            // send the connection message
            $this->writeToSocket($header . $buffer);

            // read and process the acknowledgement
            $acknowledgement = $this->readFromSocket(4);
            // TODO: improve response handling for better error handling (connection refused, etc.)
            if (ord($acknowledgement[0]) >> 4 === 2 && $acknowledgement[3] === chr(0)) {
                // TODO: add logging - successfully connected to broker
                $this->lastPingAt = microtime(true);
            } else {
                // TODO: add logging - connection failed
                throw new ConnectingToBrokerFailedException(self::EXCEPTION_CONNECTION_FAILED, 'A connection could not be established.');
            }
        } catch (DataTransferException $e) {
            throw new ConnectingToBrokerFailedException(self::EXCEPTION_CONNECTION_FAILED, 'A connection could not be established due to data transfer issues.');
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
    protected function buildConnectionFlags(string $username = null, string $password = null, bool $sendCleanSessionFlag): int
    {
        $flags = 0;

        if ($sendCleanSessionFlag) {
            $flags += 1 << 1; // set the `clean session` flag
        }

        if ($this->settings->hasLastWill()) {
            $flags += 1 << 2; // set the `will` flag
        
            if ($this->settings->requiresQualityOfService()) {
                $flags += $this->settings->getQualityOfServiceLevel() << 3; // set the `qos` bits
            }
                
            if ($this->settings->requiresMessageRetention()) {
                $flags += 1 << 5; // set the `retain` flag
            }
        }

        if ($password !== null) {
            $flags += 1 << 6; // set the `has password` flag
        }

        if ($username !== null) {
            $flags += 1 << 7; // set the `has username` flag
        }

        return $flags;
    }

    /**
     * Sends a ping to the MQTT broker.
     * 
     * @return void
     */
    public function ping(): void
    {
        $this->writeToSocket(chr(0xc0) . chr(0x00));
    }

    /**
     * Sends a disconnect and closes the socket.
     * 
     * @return void
     */
    public function close(): void
    {
        $this->disconnect();
        stream_socket_shutdown($this->socket, STREAM_SHUT_WR);
    }

    /**
     * Sends a disconnect message to the MQTT broker.
     * 
     * @return void
     */
    protected function disconnect(): void
    {
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
        $i      = 0;
        $buffer = '';

        $topicPart = $this->buildLengthPrefixedString($topic);
        $buffer   .= $topicPart;
        $i        += strlen($topicPart);

        if ($qualityOfService > 0)
        {
            $messageId = $this->nextMessageId();
            $buffer   .= chr($messageId >> 8); $i++;
            $buffer   .= chr($messageId % 256); $i++;
        }

        $buffer .= $message;
        $i      += strlen($message);

        $cmd = 0x30;
        if ($retain) {
            $cmd += 1 << 0;
        }
        if ($qualityOfService > 0) {
            $cmd += $qualityOfService << 1;
        }

        $header = chr($cmd) . $this->encodeMessageLength($i);

        $this->writeToSocket($header . $buffer);
    }

    /**
     * Subscribe to the given topics with the given quality of service.
     * 
     * @param string   $topic
     * @param callable $callback
     * @param int      $qualityOfService
     * @return void
     * @throws DataTransferException
     */
    public function subscribe(string $topic, callable $callback, int $qualityOfService = 0): void
    {
        $i         = 0;
        $buffer    = '';
        $messageId = $this->nextMessageId();
        $buffer   .= chr($messageId >> 8); $i++;
        $buffer   .= chr($messageId % 256); $i++;

        $topicPart = $this->buildLengthPrefixedString($topic);
        $buffer   .= $topicPart;
        $i        += strlen($topicPart);
        $buffer   .= chr($qualityOfService); $i++;

        $this->addTopicSubscription($topic, $callback, $messageId, $qualityOfService);

        $cmd    = 0x80 | ($qualityOfService << 1);
        $header = chr($cmd) . chr($i);

        $this->writeToSocket($header . $buffer);
    }

    /**
     * Adds a topic subscription.
     * 
     * @param string   $topic
     * @param callable $callback
     * @param int      $messageId
     * @param int      $qualityOfService
     * @return void
     */
    protected function addTopicSubscription(string $topic, callable $callback, int $messageId, int $qualityOfService): void
    {
        $this->subscribedTopics[] = new MQTTTopicSubscription($topic, $callback, $messageId, $qualityOfService);
    }

    /**
     * Runs an event loop that handles messages from the server and calls the registered
     * callbacks for published messages.
     * 
     * @param bool $allowSleep
     * @return void
     */
    public function loop(bool $allowSleep = true): void
    {
        while (true) {
            $cmd = 0;
            
            // when we receive an eof, we reconnect to get a clean connection
            // if (feof($this->socket)) {
            //     fclose($this->socket);
            //     $this->connect_auto(false);
            //     foreach ($this->subscribedTopics as $topic => $settings) {
            //         $this->subscribe($topic, $settings['callback'], $settings['qos']);
            //     }
            // }
            
            $byte = $this->readFromSocket(1, true);
            
            if (strlen($byte) === 0) {
                if($allowSleep){
                    usleep(100000); // 100ms
                }
            } else {
                $cmd        = (int)(ord($byte) / 16);
                $multiplier = 1; 
                $value      = 0;

                do {
                    $digit       = ord($this->readFromSocket(1));
                    $value      += ($digit & 127) * $multiplier; 
                    $multiplier *= 128;
                } while (($digit & 128) !== 0);

                if ($value) {
                    $buffer = $this->readFromSocket($value);
                }
                
                if ($cmd) {
                    switch($cmd){
                        // TODO: implement remaining commands
                        case 3:
                            $this->handlePublishedMessage($buffer);
                            break;
                        case 9:
                            $this->handleSubscriptionAcknowledgement($buffer);
                            break;
                    }

                    $this->lastPingAt = microtime(true);
                }
            }

            if ($this->lastPingAt < (microtime(true) - $this->settings->getKeepAlive())) {
                $this->ping();    
            }

            // when we do not receive a package in a long time, we reconnect to avoid issues with the connection
            // if ($this->lastPingAt < (microtime(true) - ($this->settings->getKeepAlive() * 2))) {
            //     fclose($this->socket);
            //     $this->connect_auto(false);
            //     if(count($this->topics))
            //         $this->subscribe($this->topics);
            // }
        }
    }

    /**
     * Handles a received message. The buffer contains the whole message except
     * command and length. The message structure is:
     * 
     *   [topic-length:topic:message]+
     * 
     * @param string $buffer
     * @return void
     */
    protected function handlePublishedMessage(string $buffer): void
    {
        $topicLength = (ord($buffer[0]) << 8) + ord($buffer[1]);
        $topic       = substr($buffer, 2, $topicLength);
        $message     = substr($buffer, ($topicLength + 2));

        foreach ($this->subscribedTopics as $subscribedTopic) {
            if (preg_match($subscribedTopic->getRegexifiedTopic(), $topic)) {
                $callback = $subscribedTopic->getCallback();
                if (is_callable($callback)) {
                    call_user_func($callback, $topic, $message);
                }
            }
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
    protected function handleSubscriptionAcknowledgement(string $buffer): void
    {
        if (strlen($buffer) < 3) {
            throw new UnexpectedAcknowledgementException(self::EXCEPTION_ACK_SUBSCRIBE, 'The MQTT broker responded with an invalid acknowledgement.');
        }

        $messageId        = $this->stringToNumber($this->pop($buffer, 2));
        $subscriptions    = $this->getTopicSubscriptionsWithMessageId($messageId);
        $acknowledgements = str_split($buffer);

        if (count($acknowledgements) !== count($subscriptions)) {
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
     * Returns all topic subscriptions with the given message identifier.
     * 
     * @param int $messageId
     * @return MQTTTopicSubscription[]
     */
    protected function getTopicSubscriptionsWithMessageId(int $messageId): array
    {
        return array_values(array_filter($this->subscribedTopics, function (MQTTTopicSubscription $subscription) use ($messageId) {
            return $subscription->getMessageId() === $messageId;
        }));
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
     * Gets the current message id.
     * 
     * @return int
     */
    protected function currentMessageId(): int
    {
        return $this->messageId;
    }

    /**
     * Gets the next message id to be used.
     * 
     * @return int
     */
    protected function nextMessageId(): int
    {
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

        $result = fwrite($this->socket, $data, $length);

        if ($result === false || $result !== $length) {
            throw new DataTransferException(self::EXCEPTION_TX_DATA, 'Sending data over the socket failed. Has it been closed?');
        }
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
                throw new DataTransferException(self::EXCEPTION_RX_DATA, 'Reading data from the socket failed. Has it been closed?');
            }
            return $receivedData;
        }

        while (feof($this->socket) === false && $remaining > 0) {
            $receivedData = fread($this->socket, $remaining);
            if ($receivedData === false) {
                throw new DataTransferException(self::EXCEPTION_RX_DATA, 'Reading data from the socket failed. Has it been closed?');
            }
            $result .= $receivedData;
            $remaining = $limit - strlen($result);
        }

        return $result;
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
     *   \x00\x0bhello world
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
     * Generates a random client id in the form of an md5 hash.
     * 
     * @return string
     */
    protected function generateRandomClientId(): string
    {
        return md5(uniqid(mt_rand(), true));
    }
}
