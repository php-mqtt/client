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
    public const EXCEPTION_CONNECTION_FAILED              = 0001;
    public const EXCEPTION_CONNECTION_PROTOCOL_VERSION    = 0002;
    public const EXCEPTION_CONNECTION_IDENTIFIER_REJECTED = 0003;
    public const EXCEPTION_CONNECTION_BROKER_UNAVAILABLE  = 0004;
    public const EXCEPTION_CONNECTION_INVALID_CREDENTIALS = 0005;
    public const EXCEPTION_CONNECTION_UNAUTHORIZED        = 0006;
    public const EXCEPTION_CONNECTION_UNKNOWN_ERROR       = 0400;
    public const EXCEPTION_CONNECTION_SOCKET_ERROR        = 1000;
    public const EXCEPTION_CONNECTION_TLS_ERROR           = 2000;

    /** @var string|null */
    private ?string $connectionErrorCode;

    /** @var string|null */
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
