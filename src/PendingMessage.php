<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use DateTime;

/**
 * Represents a pending message.
 *
 * If the message is not acknowledged by the broker, having one of these
 * objects allows the client to resend the request.
 *
 * @package PhpMqtt\Client
 */
abstract class PendingMessage
{
    /** @var int */
    protected $messageId;

    /** @var int */
    protected $sendingAttempts = 1;

    /** @var DateTime */
    protected $lastSentAt;

    /**
     * Creates a new pending message object.
     *
     * @param int           $messageId
     * @param DateTime|null $sentAt
     */
    protected function __construct(int $messageId, DateTime $sentAt = null)
    {
        $this->messageId  = $messageId;
        $this->lastSentAt = $sentAt ?? new DateTime();
    }

    /**
     * Returns the message identifier.
     *
     * @return int
     */
    public function getMessageId(): int
    {
        return $this->messageId;
    }

    /**
     * Returns the date time when the message was last attempted to be sent.
     *
     * @return DateTime
     */
    public function getLastSentAt(): DateTime
    {
        return $this->lastSentAt;
    }

    /**
     * Returns the number of times the message has been attempted to be sent.
     *
     * @return int
     */
    public function getSendingAttempts(): int
    {
        return $this->sendingAttempts;
    }

    /**
     * Sets the date time when the message was last attempted to be sent.
     *
     * @param DateTime|null $value
     * @return static
     */
    public function setLastSentAt(DateTime $value = null): self
    {
        $this->lastSentAt = $value ?? new DateTime();

        return $this;
    }

    /**
     * Increments the sending attempts by one.
     *
     * @return static
     */
    public function incrementSendingAttempts(): self
    {
        $this->sendingAttempts++;

        return $this;
    }
}
