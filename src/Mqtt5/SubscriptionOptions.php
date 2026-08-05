<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

use PhpMqtt\Client\Protocol\Packets\SubscriptionRequest;

/**
 * Immutable options for one MQTT 5 subscription filter.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class SubscriptionOptions
{
    private ?\Closure $callback;

    public function __construct(
        private string $topicFilter,
        private int $qualityOfService = 0,
        ?callable $callback = null,
        private bool $noLocal = false,
        private bool $retainAsPublished = false,
        private int $retainHandling = 0
    )
    {
        new SubscriptionRequest($topicFilter, $qualityOfService, $noLocal, $retainAsPublished, $retainHandling);
        $this->callback = $callback === null ? null : \Closure::fromCallable($callback);
    }

    public function getTopicFilter(): string
    {
        return $this->topicFilter;
    }

    public function getQualityOfService(): int
    {
        return $this->qualityOfService;
    }

    public function getCallback(): ?\Closure
    {
        return $this->callback;
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

    public function toPacketSubscription(): SubscriptionRequest
    {
        return new SubscriptionRequest(
            $this->topicFilter,
            $this->qualityOfService,
            $this->noLocal,
            $this->retainAsPublished,
            $this->retainHandling
        );
    }
}
