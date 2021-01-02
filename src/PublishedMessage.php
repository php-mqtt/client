<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

/**
 * A simple DTO for published messages which need to be stored in a repository
 * while waiting for the confirmation to be deliverable.
 *
 * @package PhpMqtt\Client
 */
class PublishedMessage extends PendingMessage
{
    private string $topicName;
    private string $message;
    private int $qualityOfService;
    private bool $retain;
    private bool $received = false;

    /**
     * Creates a new published message object.
     *
     * @param int    $messageId
     * @param string $topicName
     * @param string $message
     * @param int    $qualityOfService
     * @param bool   $retain
     */
    public function __construct(
        int $messageId,
        string $topicName,
        string $message,
        int $qualityOfService,
        bool $retain
    )
    {
        parent::__construct($messageId);
        $this->topicName        = $topicName;
        $this->message          = $message;
        $this->qualityOfService = $qualityOfService;
        $this->retain           = $retain;
    }

    /**
     * Returns the topic name of the published message.
     *
     * @return string
     */
    public function getTopicName(): string
    {
        return $this->topicName;
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
     * Determines whether the message has been confirmed as received.
     *
     * @return bool
     */
    public function hasBeenReceived(): bool
    {
        return $this->received;
    }

    /**
     * Marks the published message as received (QoS level 2).
     *
     * Returns `true` if the message was not previously received. Otherwise
     * `false` will be returned.
     *
     * @return bool
     */
    public function markAsReceived(): bool
    {
        $result = !$this->received;

        $this->received = true;

        return $result;
    }
}
