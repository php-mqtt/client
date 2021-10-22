<?php

declare(strict_types=1);

namespace PhpMqtt\Client\MessageProcessors;

use PhpMqtt\Client\ConnectionSettings;
use Psr\Log\LoggerInterface;

/**
 * This message processor implements the MQTT protocol version 3.1.1.
 *
 * @package PhpMqtt\Client\MessageProcessors
 */
class Mqtt311MessageProcessor extends Mqtt31MessageProcessor
{
    private string $clientId;

    /**
     * Creates a new message processor instance which supports version 3.1.1 of the MQTT protocol.
     *
     * @param string          $clientId
     * @param LoggerInterface $logger
     */
    public function __construct(string $clientId, LoggerInterface $logger)
    {
        parent::__construct($clientId, $logger);

        $this->clientId = $clientId;
    }

    /**
     * {@inheritDoc}
     */
    public function buildConnectMessage(ConnectionSettings $connectionSettings, bool $useCleanSession = false): string
    {
        // The protocol name and version.
        $buffer  = $this->buildLengthPrefixedString('MQTT');
        $buffer .= chr(0x04); // protocol version (4)

        // Build connection flags based on the connection settings.
        $buffer .= chr($this->buildConnectionFlags($connectionSettings, $useCleanSession));

        // Encode and add the keep alive interval.
        $buffer .= chr($connectionSettings->getKeepAliveInterval() >> 8);
        $buffer .= chr($connectionSettings->getKeepAliveInterval() & 0xff);

        // Encode and add the client identifier.
        $buffer .= $this->buildLengthPrefixedString($this->clientId);

        // Encode and add the last will topic and message, if configured.
        if ($connectionSettings->hasLastWill()) {
            $buffer .= $this->buildLengthPrefixedString($connectionSettings->getLastWillTopic());
            $buffer .= $this->buildLengthPrefixedString($connectionSettings->getLastWillMessage());
        }

        // Encode and add the credentials, if configured.
        if ($connectionSettings->getUsername() !== null) {
            $buffer .= $this->buildLengthPrefixedString($connectionSettings->getUsername());
        }
        if ($connectionSettings->getPassword() !== null) {
            $buffer .= $this->buildLengthPrefixedString($connectionSettings->getPassword());
        }

        // The header consists of the message type 0x10 and the length.
        $header = chr(0x10) . $this->encodeMessageLength(strlen($buffer));

        return $header . $buffer;
    }
}
