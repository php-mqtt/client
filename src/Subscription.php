<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

/**
 * A simple DTO for subscriptions to a topic which need to be stored in a repository.
 *
 * @package PhpMqtt\Client
 */
class Subscription
{
    private string $regexifiedTopicFilter;

    /**
     * Creates a new subscription object.
     */
    public function __construct(
        private string $topicFilter,
        private int $qualityOfService = 0,
        private ?\Closure $callback = null,
    )
    {
        $this->regexifyTopicFilter();
    }

    /**
     * Converts the topic filter into a regular expression.
     */
    private function regexifyTopicFilter(): void
    {
        $topicFilter = $this->topicFilter;

        // If the topic filter is for a shared subscription, we remove the shared subscription prefix as well as the group name
        // from the topic filter. To do so, we look for the $share keyword and then try to find the second topic separator to
        // calculate the substring containing the actual topic filter.
        // Note: shared subscriptions always have the form: $share/<group>/<topic>
        if (str_starts_with($topicFilter, '$share/') && ($separatorIndex = strpos($topicFilter, '/', 7)) !== false) {
            $topicFilter = substr($topicFilter, $separatorIndex + 1);
        }

        $this->regexifiedTopicFilter = '/^' . str_replace(['$', '/', '+', '#'], ['\$', '\/', '([^\/]*)', '(.*)'], $topicFilter) . '$/';
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
        return (bool) preg_match($this->regexifiedTopicFilter, $topicName);
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
        if (!preg_match($this->regexifiedTopicFilter, $topicName, $matches)) {
            return [];
        }

        return array_slice($matches, 1);
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
