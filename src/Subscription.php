<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use Opis\Closure\SerializableClosure;

/**
 * A simple DTO for subscriptions to a topic which need to be stored in a repository.
 *
 * @package PhpMqtt\Client
 */
class Subscription
{
    /** @var string */
    private $topicFilter;

    /** @var int|null */
    private $subscriptionId;

    /** @var int */
    private $qualityOfService;

    /** @var SerializableClosure|null */
    private $callback;

    /** @var string */
    private $regexifiedTopicFilter;

    /**
     * Creates a new subscription object.
     *
     * @param string        $topicFilter
     * @param \Closure|null $callback
     * @param int           $qualityOfService
     */
    public function __construct(string $topicFilter, ?int $subscriptionId, ?\Closure $callback, int $qualityOfService = 0)
    {
        $this->topicFilter      = $topicFilter;
        $this->subscriptionId   = $subscriptionId;
        $this->qualityOfService = $qualityOfService;

        if ($callback !== null) {
            $this->callback = SerializableClosure::from($callback);
        }

        $this->regexifyTopicFilter();
    }

    /**
     * Converts the topic filter into a regular expression.
     *
     * @return void
     */
    private function regexifyTopicFilter(): void
    {
        $this->regexifiedTopicFilter = '/^' . str_replace(['$', '/', '+', '#'], ['\$', '\/', '[^\/]*', '.*'], $this->topicFilter) . '$/';
    }

    /**
     * Returns the topic of the subscription.
     *
     * @return string
     */
    public function getTopicFilter(): string
    {
        return $this->topicFilter;
    }

    /**
     * Matches the given topic name matches to the subscription's topic filter.
     *
     * @param string $topicName
     * @return bool
     */
    public function matchTopicFilter(string $topicName): bool
    {
        return (bool) preg_match($this->regexifiedTopicFilter, $topicName);
    }

    /**
     * Returns the subscription identifier.
     *
     * @return int|null
     */
    public function getSubscriptionId(): ?int
    {
        return $this->subscriptionId;
    }

    /**
     * Returns the callback for this subscription.
     *
     * @return \Closure|null
     */
    public function getCallback(): ?\Closure
    {
        return ($this->callback ? $this->callback->getClosure() : null);
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
     * Sets the actual quality of service level.
     *
     * @param int $qualityOfService
     * @return void
     */
    public function setQualityOfServiceLevel(int $qualityOfService): void
    {
        $this->qualityOfService = $qualityOfService;
    }
}
