<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Packets;

use PhpMqtt\Client\Protocol\Qos;
use PhpMqtt\Client\Protocol\Topic;

/**
 * One MQTT 5 topic-filter subscription request.
 *
 * @package PhpMqtt\Client\Protocol\Packets
 */
final class SubscriptionRequest
{
    public function __construct(
        private string $topicFilter,
        private int $qualityOfService = 0,
        private bool $noLocal = false,
        private bool $retainAsPublished = false,
        private int $retainHandling = 0
    )
    {
        Topic::assertValidFilter($topicFilter);
        Qos::assertValid($qualityOfService);

        if ($retainHandling < 0 || $retainHandling > 2) {
            throw new \InvalidArgumentException('Retain Handling must be between 0 and 2.');
        }
    }

    public function getTopicFilter(): string
    {
        return $this->topicFilter;
    }

    public function getQualityOfService(): int
    {
        return $this->qualityOfService;
    }

    public function usesNoLocal(): bool
    {
        return $this->noLocal;
    }

    public function usesRetainAsPublished(): bool
    {
        return $this->retainAsPublished;
    }

    public function getRetainHandling(): int
    {
        return $this->retainHandling;
    }
}
