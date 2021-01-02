<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Exceptions;

/**
 * Exception to be thrown if a pending message with the same packet identifier is still pending.
 *
 * @package PhpMqtt\Client\Exceptions
 */
class PendingMessageAlreadyExistsException extends RepositoryException
{
    /**
     * PendingMessageAlreadyExistsException constructor.
     *
     * @param int $messageId
     */
    public function __construct(int $messageId)
    {
        parent::__construct(sprintf('A pending message with the message identifier [%s] exists already.', $messageId));
    }
}
