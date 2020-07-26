<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

/**
 * A simple DTO for subscriptions to a topic which need to be stored in a repository.
 *
 * @package PhpMqtt\Client
 */
class TopicSubscription
{
    /** @var string */
    private $topic;

    /** @var string */
    private $regexifiedTopic;

    /** @var callable */
    private $callback;

    /** @var int */
    private $messageId;

    /** @var int */
    private $qualityOfService;

    /** @var int|null */
    private $acknowledgedQualityOfService;

    /**
     * Creates a new topic subscription object.
     *
     * @param string   $topic
     * @param callable $callback
     * @param int      $messageId
     * @param int      $qualityOfService
     */
    public function __construct(string $topic, callable $callback, int $messageId, int $qualityOfService = 0)
    {
        $this->topic            = $topic;
        $this->regexifiedTopic  = '/^' . str_replace(['$', '/', '+', '#'], ['\$', '\/', '[^\/]*', '.*'], $topic) . '$/';
        $this->callback         = $callback;
        $this->messageId        = $messageId;
        $this->qualityOfService = $qualityOfService;
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
     * Returns the regexified topic. This regex can be used to match
     * incoming messages to subscriptions.
     *
     * @return string
     */
    public function getRegexifiedTopic(): string
    {
        return $this->regexifiedTopic;
    }

    /**
     * Returns the callback for this subscription.
     *
     * @return callable
     */
    public function getCallback(): callable
    {
        return $this->callback;
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
     * Returns the requested quality of service level.
     *
     * @return int
     */
    public function getQualityOfServiceLevel(): int
    {
        return $this->qualityOfService;
    }

    /**
     * Returns the acknowledged quality of service level.
     *
     * @return int|null
     */
    public function getAcknowledgedQualityOfServiceLevel(): ?int
    {
        return $this->acknowledgedQualityOfService;
    }

    /**
     * Sets the acknowledged quality of service level.
     *
     * @param int $value
     * @return static
     */
    public function setAcknowledgedQualityOfServiceLevel(int $value): self
    {
        $this->acknowledgedQualityOfService = $value;

        return $this;
    }
}
