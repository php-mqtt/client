<?php

declare(strict_types=1);

namespace Namoshek\MQTT;

/**
 * Exception to be thrown if an MQTT client error occurs.
 */
class MQTTClientException extends \Exception
{
    public function __construct(string $message, int $code = 0, \Throwable $parentException = null)
    {
        parent::__construct(
            sprintf('[%s] The MQTT client encountered an error.', $code),
            $code,
            $parentException
        );
    }
}
