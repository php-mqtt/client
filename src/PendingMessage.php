<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use DateTime;

/**
 * Represents a pending message.
 *
 * For messages with QoS 1 and 2 the client is responsible to resend the message if no
 * acknowledgement is received from the broker within a given time period.
 *
 * This class serves as common base for message objects which need to be resent if no
 * acknowledgement is received.
 *
 * @package PhpMqtt\Client
 */
abstract class PendingMessage
{
    private int $sendingAttempts = 1;
    private DateTime $lastSentAt;

    /**
     * Creates a new pending message object.
     */
    protected function __construct(private int $messageId, ?DateTime $sentAt = null)
    {
        $this->lastSentAt = $sentAt ?? new DateTime();
    }

    /**
     * Returns the message identifier.
     */
    public function getMessageId(): int
    {
        return $this->messageId;
    }

    /**
     * Returns the date time when the message was last sent.
     */
    public function getLastSentAt(): DateTime
    {
        return $this->lastSentAt;
    }

    /**
     * Returns the number of times the message has been sent.
     */
    public function getSendingAttempts(): int
    {
        return $this->sendingAttempts;
    }

    /**
     * Sets the date time when the message was last sent.
     */
    public function setLastSentAt(?DateTime $value = null): self
    {
        $this->lastSentAt = $value ?? new DateTime();

        return $this;
    }

    /**
     * Increments the sending attempts by one.
     */
    public function incrementSendingAttempts(): self
    {
        $this->sendingAttempts++;

        return $this;
    }
}
