<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

use PhpMqtt\Client\Protocol\Properties;

/**
 * Immutable batched MQTT 5 SUBSCRIBE options.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class SubscribeOptions
{
    /** @var SubscriptionOptions[] */
    private array $subscriptions;
    private Properties $properties;

    /**
     * @param SubscriptionOptions[] $subscriptions
     */
    public function __construct(array $subscriptions, ?Properties $properties = null)
    {
        if (count($subscriptions) === 0) {
            throw new \InvalidArgumentException('At least one subscription is required.');
        }

        foreach ($subscriptions as $subscription) {
            if (!$subscription instanceof SubscriptionOptions) {
                throw new \InvalidArgumentException('Invalid subscription option.');
            }
        }

        $this->subscriptions = array_values($subscriptions);
        $this->properties    = $properties ?? Properties::empty();
    }

    /**
     * @return SubscriptionOptions[]
     */
    public function getSubscriptions(): array
    {
        return $this->subscriptions;
    }

    public function getProperties(): Properties
    {
        return $this->properties;
    }
}
