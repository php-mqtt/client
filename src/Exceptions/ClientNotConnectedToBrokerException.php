<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if an MQTT client is not connected to a broker and tries
 * to perform an action which requires a connection (e.g. publish or subscribe).
 */
class ClientNotConnectedToBrokerException extends DataTransferException
{
    const EXCEPTION_CONNECTION_LOST = 0300;

    public function __construct(string $error)
    {
        parent::__construct(self::EXCEPTION_CONNECTION_LOST, $error);
    }
}
