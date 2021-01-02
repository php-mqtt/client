<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if an MQTT client could not connect to the broker.
 *
 * @package PhpMqtt\Client\Exceptions
 */
class ConnectingToBrokerFailedException extends MqttClientException
{
    const EXCEPTION_CONNECTION_FAILED              = 0001;
    const EXCEPTION_CONNECTION_PROTOCOL_VERSION    = 0002;
    const EXCEPTION_CONNECTION_IDENTIFIER_REJECTED = 0003;
    const EXCEPTION_CONNECTION_BROKER_UNAVAILABLE  = 0004;
    const EXCEPTION_CONNECTION_INVALID_CREDENTIALS = 0005;
    const EXCEPTION_CONNECTION_UNAUTHORIZED        = 0006;
    const EXCEPTION_CONNECTION_SOCKET_ERROR        = 1000;
    const EXCEPTION_CONNECTION_TLS_ERROR           = 2000;

    private ?string $connectionErrorCode;
    private ?string $connectionErrorMessage;

    /**
     * ConnectingToBrokerFailedException constructor.
     *
     * @param int         $code
     * @param string      $error
     * @param string|null $innerCode
     * @param string|null $innerMessage
     */
    public function __construct(int $code, string $error, string $innerCode = null, string $innerMessage = null)
    {
        parent::__construct(
            sprintf('[%s] Establishing a connection to the MQTT broker failed: %s', $code, $error),
            $code
        );

        $this->connectionErrorCode    = $innerCode;
        $this->connectionErrorMessage = $innerMessage;
    }

    /**
     * Retrieves the connection error code.
     *
     * @return string|null
     */
    public function getConnectionErrorCode(): ?string
    {
        return $this->connectionErrorCode;
    }

    /**
     * Retrieves the connection error message.
     *
     * @return string|null
     */
    public function getConnectionErrorMessage(): ?string
    {
        return $this->connectionErrorMessage;
    }
}
