<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if an MQTT client could not connect to the broker.
 *
 * @package PhpMqtt\Client\Exceptions
 */
class ConnectingToBrokerFailedException extends MQTTClientException
{
    /** @var string|null */
    private $connectionErrorMessage;

    /** @var string|null */
    private $connectionErrorCode;

    /**
     * ConnectingToBrokerFailedException constructor.
     *
     * @param int         $code
     * @param string      $error
     * @param string|null $innerMessage
     * @param string|null $innerCode
     */
    public function __construct(int $code, string $error, string $innerMessage = null, string $innerCode = null)
    {
        parent::__construct(
            sprintf('[%s] Establishing a connection to the MQTT broker failed: %s', $code, $error),
            $code
        );
        $this->connectionErrorMessage = ($innerMessage ?? $error);
        $this->connectionErrorCode    = $innerCode;
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

    /**
     * Retrieves the connection error code.
     *
     * @return string|null
     */
    public function getConnectionErrorCode(): ?string
    {
        return $this->connectionErrorCode;
    }
}
