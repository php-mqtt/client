<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

/**
 * Represents a pending subscribe request.
 *
 * @package PhpMqtt\Client
 */
class SubscribeRequest extends PendingMessage
{
    /** @var Subscription[] */
    private array $subscriptions;

    /**
     * Creates a new subscribe request message.
     *
     * @param int            $messageId
     * @param Subscription[] $subscriptions
     */
    public function __construct(int $messageId, array $subscriptions)
    {
        parent::__construct($messageId);

        $this->subscriptions = array_values($subscriptions);
    }

    /**
     * Returns the subscriptions in this request.
     *
     * @return Subscription[]
     */
    public function getSubscriptions(): array
    {
        return $this->subscriptions;
    }
}
