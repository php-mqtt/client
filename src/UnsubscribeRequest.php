<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use DateTime;

/**
 * Represents an unsubscribe request. Is used to store pending unsubscribe requests.
 * If an unsubscribe request is not acknowledged by the broker, having one of these
 * objects allows the client to resend the request.
 *
 * @package PhpMqtt\Client
 */
class UnsubscribeRequest
{
    /** @var int */
    private $messageId;

    /** @var string */
    private $topic;

    /** @var DateTime */
    private $lastSentAt;

    /** @var int */
    private $sendingAttempts = 1;

    /**
     * Creates a new unsubscribe request object.
     *
     * @param int           $messageId
     * @param string        $topic
     * @param DateTime|null $sentAt
     */
    public function __construct(int $messageId, string $topic, DateTime $sentAt = null)
    {
        $this->messageId  = $messageId;
        $this->topic      = $topic;
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
     * Returns the topic of the subscription.
     *
     * @return string
     */
    public function getTopic(): string
    {
        return $this->topic;
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
     * @param DateTime $value
     * @return static
     */
    public function setLastSentAt(DateTime $value): self
    {
        $this->lastSentAt = $value;

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
