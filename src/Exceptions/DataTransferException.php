<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if an MQTT client encountered an error while transferring data.
 *
 * @package PhpMqtt\Client\Exceptions
 */
class DataTransferException extends MQTTClientException
{
    public function __construct(int $code, string $error)
    {
        parent::__construct(
            sprintf('[%s] Transferring data over socket failed: %s', $code, $error),
            $code
        );
    }
}
