<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use PhpMqtt\Client\Protocol\Topic;

/**
 * A simple DTO for subscriptions to a topic which need to be stored in a repository.
 *
 * @package PhpMqtt\Client
 */
class Subscription
{
    /**
     * Creates a new subscription object.
     */
    public function __construct(
        private string $topicFilter,
        private int $qualityOfService = 0,
        private ?\Closure $callback = null,
    )
    {
    }

    /**
     * Returns the topic of the subscription.
     */
    public function getTopicFilter(): string
    {
        return $this->topicFilter;
    }

    /**
     * Matches the given topic name matches to the subscription's topic filter.
     */
    public function matchesTopic(string $topicName): bool
    {
        return Topic::matches($this->topicFilter, $topicName);
    }

    /**
     * Returns an array which contains all matched wildcards of this subscription, taken from the given topic name.
     *
     * Example:
     *   Subscription topic filter: foo/+/bar/+/baz/#
     *   Result for 'foo/1/bar/2/baz': ['1', '2']
     *   Result for 'foo/my/bar/subscription/baz/42': ['my', 'subscription', '42']
     *   Result for 'foo/my/bar/subscription/baz/hello/world/123': ['my', 'subscription', 'hello', 'world', '123']
     *   Result for invalid topic 'some/topic': []
     *
     * Note: This method should only be called if {@see matchesTopic} returned true. An empty array will be returned otherwise.
     */
    public function getMatchedWildcards(string $topicName): array
    {
        return Topic::matchedWildcards($this->topicFilter, $topicName);
    }

    /**
     * Returns the callback for this subscription.
     */
    public function getCallback(): ?\Closure
    {
        return $this->callback;
    }

    /**
     * Returns the requested quality of service level.
     */
    public function getQualityOfServiceLevel(): int
    {
        return $this->qualityOfService;
    }

    /**
     * Sets the actual quality of service level.
     */
    public function setQualityOfServiceLevel(int $qualityOfService): void
    {
        $this->qualityOfService = $qualityOfService;
    }
}
