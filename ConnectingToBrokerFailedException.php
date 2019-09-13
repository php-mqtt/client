<?php

declare(strict_types=1);

namespace Namoshek\MQTT;

/**
 * Exception to be thrown if an MQTT client could not connect to the broker.
 */
class ConnectingToBrokerFailedException extends MQTTClientException
{
    public function __construct(int $code, string $error)
    {
        parent::__construct(
            sprintf('[%s] Establishing a connection to the MQTT broker failed: %s', $code, $error),
            $code
        );
    }
}
