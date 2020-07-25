<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if an MQTT client is not connected to a broker and tries
 * to perform an action which requires a connection (e.g. publish or subscribe).
 */
class ConnectionRefusedException
        extends DataTransferException
{
}
