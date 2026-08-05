<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Raised when bytes received from a peer are not a well-formed MQTT packet.
 *
 * @package PhpMqtt\Client\Exceptions
 */
class MalformedPacketException extends InvalidMessageException
{
}
