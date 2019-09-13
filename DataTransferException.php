<?php

declare(strict_types=1);

namespace Namoshek\MQTT;

/**
 * Exception to be thrown if an MQTT client encountered an error while transfering data.
 */
class DataTransferException extends MQTTClientException
{
    public function __construct(int $code, string $error)
    {
        parent::__construct(
            sprintf('[%s] Transfering data over socket failed: %s', $code, $error),
            $code
        );
    }
}
