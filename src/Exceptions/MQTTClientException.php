<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if an MQTT client error occurs.
 *
 * @package PhpMqtt\Client\Exceptions
 */
class MQTTClientException extends \Exception
{
    public function __construct(string $message = '', int $code = 0, \Throwable $parentException = null)
    {
        if (empty($message)) {
            parent::__construct(
                sprintf('[%s] The MQTT client encountered an error.', $code),
                $code,
                $parentException
            );
        } else {
            parent::__construct($message, $code, $parentException);
        }
    }
}
