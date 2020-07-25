<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if an MQTT client encountered an error while transfering data.
 */
class DataTransferException extends MqttClientException
{
    public function __construct(int $code, string $error)
    {
        parent::__construct(
            sprintf('[%s] Transfering data over socket failed: %s', $code, $error),
            $code
        );
    }
}
