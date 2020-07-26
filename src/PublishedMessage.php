<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use DateTime;

/**
 * A simple DTO for published messages which need to be stored in a repository
 * while waiting for the confirmation to be deliverable.
 *
 * @package PhpMqtt\Client
 */
class PublishedMessage
{
    /** @var int */
    private $messageId;

    /** @var string */
    private $topic;

    /** @var string */
    private $message;

    /** @var int */
    private $qualityOfService;

    /** @var bool */
    private $retain;

    /** @var DateTime */
    private $lastSentAt;

    /** @var int */
    private $sendingAttempts = 1;

    /** @var bool */
    private $received = false;

    /**
     * Creates a new published message object.
     *
     * @param int           $messageId
     * @param string        $topic
     * @param string        $message
     * @param int           $qualityOfService
     * @param bool          $retain
     * @param DateTime|null $sentAt
     */
    public function __construct(
        int $messageId,
        string $topic,
        string $message,
        int $qualityOfService,
        bool $retain,
        DateTime $sentAt = null
    )
    {
        if ($sentAt === null) {
            $sentAt = new DateTime();
        }

        $this->messageId        = $messageId;
        $this->topic            = $topic;
        $this->message          = $message;
        $this->qualityOfService = $qualityOfService;
        $this->retain           = $retain;
        $this->lastSentAt       = $sentAt;
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
     * Returns the topic of the published message.
     *
     * @return string
     */
    public function getTopic(): string
    {
        return $this->topic;
    }

    /**
     * Returns the content of the published message.
     *
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns the requested quality of service level.
     *
     * @return int
     */
    public function getQualityOfServiceLevel(): int
    {
        return $this->qualityOfService;
    }

    /**
     * Determines whether this message wants to be retained.
     *
     * @return bool
     */
    public function wantsToBeRetained(): bool
    {
        return $this->retain;
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
     * Determines whether the message has been confirmed as received.
     *
     * @return bool
     */
    public function hasBeenReceived(): bool
    {
        return $this->received;
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

    /**
     * Sets the received state.
     *
     * @param bool $value
     * @return static
     */
    public function setReceived(bool $value): self
    {
        $this->received = $value;

        return $this;
    }
}
