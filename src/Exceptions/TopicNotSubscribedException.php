<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if unsubscribe is called for a topic which has
 * not been subscribed yet.
 *
 * @package PhpMqtt\Client\Exceptions
 */
class TopicNotSubscribedException extends MqttClientException
{
}
