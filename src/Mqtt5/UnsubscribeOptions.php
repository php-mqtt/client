<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\Topic;

/**
 * Immutable batched MQTT 5 UNSUBSCRIBE options.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class UnsubscribeOptions
{
    /** @var string[] */
    private array $topicFilters;
    private Properties $properties;

    /**
     * @param string[] $topicFilters
     */
    public function __construct(array $topicFilters, ?Properties $properties = null)
    {
        if (count($topicFilters) === 0) {
            throw new \InvalidArgumentException('At least one topic filter is required.');
        }

        foreach ($topicFilters as $topicFilter) {
            Topic::assertValidFilter($topicFilter);
        }

        $this->topicFilters = array_values($topicFilters);
        $this->properties   = $properties ?? Properties::empty();
    }

    /**
     * @return string[]
     */
    public function getTopicFilters(): array
    {
        return $this->topicFilters;
    }

    public function getProperties(): Properties
    {
        return $this->properties;
    }
}
