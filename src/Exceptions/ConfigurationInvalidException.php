<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if an MQTT client has been misconfigured or wrong connection
 * settings are being used.
 *
 * @package PhpMqtt\Client\Exceptions
 */
class ConfigurationInvalidException extends MqttClientException
{
}
