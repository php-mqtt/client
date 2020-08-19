<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if an MQTT client encountered a protocol violation.
 *
 * @package PhpMqtt\Client\Exceptions
 */
class ProtocolViolationException extends MqttClientException
{
    /**
     * ProtocolViolationException constructor.
     *
     * @param string $error
     */
    public function __construct(string $error)
    {
        parent::__construct(sprintf('Protocol violation: %s.', $error));
    }
}
