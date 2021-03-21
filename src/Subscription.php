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
    private string $topicFilter;
    private string $regexifiedTopicFilter;
    private int $qualityOfService;
    private ?\Closure $callback;

    /**
     * Creates a new subscription object.
     *
     * @param string        $topicFilter
     * @param int           $qualityOfService
     * @param \Closure|null $callback
     */
    public function __construct(string $topicFilter, int $qualityOfService = 0, ?\Closure $callback = null)
    {
        $this->topicFilter      = $topicFilter;
        $this->qualityOfService = $qualityOfService;
        $this->callback         = $callback;

        $this->regexifyTopicFilter();
    }

    /**
     * Converts the topic filter into a regular expression.
     *
     * @return void
     */
    private function regexifyTopicFilter(): void
    {
        $topicFilter = $this->topicFilter;

        // If the topic filter is for a shared subscription, we remove the shared subscription prefix as well as the group name
        // from the topic filter. To do so, we look for the $share keyword and then try to find the second topic separator to
        // calculate the substring containing the actual topic filter.
        // Note: shared subscriptions always have the form: $share/<group>/<topic>
        if (strpos($topicFilter, '$share/') === 0 && ($separatorIndex = strpos($topicFilter, '/', 7)) !== false) {
            $topicFilter = substr($topicFilter, $separatorIndex + 1);
        }

        $this->regexifiedTopicFilter = '/^' . str_replace(['$', '/', '+', '#'], ['\$', '\/', '[^\/]*', '.*'], $topicFilter) . '$/';
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
    public function matchesTopic(string $topicName): bool
    {
        return (bool) preg_match($this->regexifiedTopicFilter, $topicName);
    }

    /**
     * Returns the callback for this subscription.
     *
     * @return \Closure|null
     */
    public function getCallback(): ?\Closure
    {
        return $this->callback;
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
