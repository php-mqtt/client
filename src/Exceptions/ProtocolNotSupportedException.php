<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if an invalid MQTT version is given.
 */
class ProtocolNotSupportedException extends MqttClientException
{
    public function __construct(string $protocol)
    {
        parent::__construct(sprintf('The given protocol version [%s] is not supported.', $protocol));
    }
}
