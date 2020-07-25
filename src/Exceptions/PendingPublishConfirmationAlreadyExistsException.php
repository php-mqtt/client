<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if a publish message with QoS level 2 is received and a
 * publish receive message has already been sent, but a publish confirmation is pending.
 */
class PendingPublishConfirmationAlreadyExistsException extends MqttClientException
{
    public function __construct(int $messageId)
    {
        parent::__construct(sprintf('A pending publish confirmation with the message identifier [%s] exists already.', $messageId));
    }
}
