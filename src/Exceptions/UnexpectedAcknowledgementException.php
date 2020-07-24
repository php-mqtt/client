<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if an MQTT client encountered an unexpected acknowledgement.
 */
class UnexpectedAcknowledgementException extends MqttClientException
{
    const EXCEPTION_ACK_CONNECT   = 0201;
    const EXCEPTION_ACK_PUBLISH   = 0202;
    const EXCEPTION_ACK_SUBSCRIBE = 0203;
    const EXCEPTION_ACK_RELEASE   = 0204;
    const EXCEPTION_ACK_RECEIVE   = 0205;
    const EXCEPTION_ACK_COMPLETE  = 0206;

    public function __construct(int $code, string $error)
    {
        parent::__construct(
            sprintf('[%s] Received unexpected acknowledgement: %s', $code, $error),
            $code
        );
    }
}
