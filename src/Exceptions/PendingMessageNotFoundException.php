<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if a pending message with the same packet identifier is not found.
 *
 * @package PhpMqtt\Client\Exceptions
 */
class PendingMessageNotFoundException extends RepositoryException
{
    /**
     * PendingMessageNotFoundException constructor.
     *
     * @param int $messageId
     */
    public function __construct(int $messageId)
    {
        parent::__construct(sprintf('No pending message with the message identifier [%s].', $messageId));
    }
}
