<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Raised when a well-formed packet violates MQTT protocol rules.
 *
 * @package PhpMqtt\Client\Exceptions
 */
class ProtocolErrorException extends ProtocolViolationException
{
}
