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

    public function __construct(int $code, string $error)
    {
        parent::__construct(
            sprintf('[%s] Establishing a connection to the MQTT broker failed: %s', $code, $error),
            $code
        );
    }
}
