<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Repositories;

use PhpMqtt\Client\Contracts\Mqtt5Repository;
use PhpMqtt\Client\Exceptions\PendingMessageAlreadyExistsException;
use PhpMqtt\Client\Exceptions\PendingMessageNotFoundException;
use PhpMqtt\Client\Exceptions\RepositoryException;
use PhpMqtt\Client\PendingMessage;
use PhpMqtt\Client\PublishedMessage;
use PhpMqtt\Client\Subscription;

/**
 * Provides an in-memory implementation which manages message ids, subscriptions and pending messages.
 * Instances of this type do not persist any data and are only meant for simple uses cases.
 *
 * @package PhpMqtt\Client\Repositories
 */
class MemoryRepository implements Mqtt5Repository
{
    private int $nextMessageId = 1;

    /** @var array<int, PendingMessage> */
    private array $pendingOutgoingMessages = [];

    /** @var array<int, PendingMessage> */
    private array $pendingIncomingMessages = [];

    /** @var array<int, Subscription> */
    private array $subscriptions = [];

    /** @var array<int, PublishedMessage> */
    private array $queuedPublications = [];

    /** @var array<string, mixed> */
    private array $sessionMetadata = [];

    /**
     * {@inheritDoc}
     */
    public function reset(): void
    {
        $this->nextMessageId           = 1;
        $this->pendingOutgoingMessages = [];
        $this->pendingIncomingMessages = [];
        $this->subscriptions           = [];
        $this->queuedPublications      = [];
        $this->sessionMetadata         = [];
    }

    /**
     * {@inheritDoc}
     */
    public function newMessageId(): int
    {
        if (count($this->pendingOutgoingMessages) >= 65535) {
            // This should never happen, as the server receive queue is
            // normally smaller than the actual total number of message ids.
            // Also, when using MQTT 5.0 the server can specify a smaller
            // receive queue size (mosquitto for example has 20 by default),
            // so the client has to implement the logic to honor this
            // restriction and fallback to the protocol limit.
            throw new RepositoryException('No more message identifiers available. The queue is full.');
        }

        while (isset($this->pendingOutgoingMessages[$this->nextMessageId])) {
            $this->nextMessageId++;
            if ($this->nextMessageId > 65535) {
                $this->nextMessageId = 1;
            }
        }

        return $this->nextMessageId;
    }

    /**
     * {@inheritDoc}
     */
    public function countPendingOutgoingMessages(): int
    {
        return count($this->pendingOutgoingMessages);
    }

    /**
     * {@inheritDoc}
     */
    public function getPendingOutgoingMessage(int $messageId): ?PendingMessage
    {
        return $this->pendingOutgoingMessages[$messageId] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function getPendingOutgoingMessagesLastSentBefore(?\DateTime $dateTime = null): array
    {
        $result = [];

        foreach ($this->pendingOutgoingMessages as $pendingMessage) {
            if ($dateTime === null || $pendingMessage->getLastSentAt() < $dateTime) {
                $result[] = $pendingMessage;
            }
        }

        return $result;
    }

    public function getPendingOutgoingMessages(): array
    {
        return array_values($this->pendingOutgoingMessages);
    }

    /**
     * {@inheritDoc}
     */
    public function addPendingOutgoingMessage(PendingMessage $message): void
    {
        if (isset($this->pendingOutgoingMessages[$message->getMessageId()])) {
            throw new PendingMessageAlreadyExistsException($message->getMessageId());
        }

        $this->pendingOutgoingMessages[$message->getMessageId()] = $message;
    }

    /**
     * {@inheritDoc}
     */
    public function markPendingOutgoingPublishedMessageAsReceived(int $messageId): bool
    {
        if (!isset($this->pendingOutgoingMessages[$messageId]) ||
            !$this->pendingOutgoingMessages[$messageId] instanceof PublishedMessage) {
            throw new PendingMessageNotFoundException($messageId);
        }

        return $this->pendingOutgoingMessages[$messageId]->markAsReceived();
    }

    /**
     * {@inheritDoc}
     */
    public function removePendingOutgoingMessage(int $messageId): bool
    {
        if (!isset($this->pendingOutgoingMessages[$messageId])) {
            return false;
        }

        unset($this->pendingOutgoingMessages[$messageId], $this->queuedPublications[$messageId]);
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function countPendingIncomingMessages(): int
    {
        return count($this->pendingIncomingMessages);
    }

    /**
     * {@inheritDoc}
     */
    public function getPendingIncomingMessage(int $messageId): ?PendingMessage
    {
        return $this->pendingIncomingMessages[$messageId] ?? null;
    }

    /**
     * {@inheritDoc}
     */
    public function addPendingIncomingMessage(PendingMessage $message): void
    {
        if (isset($this->pendingIncomingMessages[$message->getMessageId()])) {
            throw new PendingMessageAlreadyExistsException($message->getMessageId());
        }

        $this->pendingIncomingMessages[$message->getMessageId()] = $message;
    }

    /**
     * {@inheritDoc}
     */
    public function removePendingIncomingMessage(int $messageId): bool
    {
        if (!isset($this->pendingIncomingMessages[$messageId])) {
            return false;
        }

        unset($this->pendingIncomingMessages[$messageId]);
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function countSubscriptions(): int
    {
        return count($this->subscriptions);
    }

    /**
     * {@inheritDoc}
     */
    public function addSubscription(Subscription $subscription): void
    {
        // Remove a potentially existing subscription for this topic filter.
        $this->removeSubscription($subscription->getTopicFilter());

        $this->subscriptions[] = $subscription;
    }

    /**
     * {@inheritDoc}
     */
    public function getSubscriptionsMatchingTopic(string $topicName): array
    {
        $result = [];

        foreach ($this->subscriptions as $subscription) {
            if (!$subscription->matchesTopic($topicName)) {
                continue;
            }

            $result[] = $subscription;
        }

        return $result;
    }

    public function getSubscriptions(): array
    {
        return array_values($this->subscriptions);
    }

    /**
     * {@inheritDoc}
     */
    public function removeSubscription(string $topicFilter): bool
    {
        foreach ($this->subscriptions as $index => $subscription) {
            if ($subscription->getTopicFilter() === $topicFilter) {
                unset($this->subscriptions[$index]);
                return true;
            }
        }

        return false;
    }

    public function addQueuedPublication(PublishedMessage $publication): void
    {
        $this->queuedPublications[$publication->getMessageId()] = $publication;
    }

    public function getQueuedPublications(): array
    {
        return array_values($this->queuedPublications);
    }

    public function removeQueuedPublication(int $messageId): bool
    {
        if (!isset($this->queuedPublications[$messageId])) {
            return false;
        }

        unset($this->queuedPublications[$messageId]);

        return true;
    }

    public function setSessionMetadata(string $key, $value): void
    {
        $this->sessionMetadata[$key] = $value;
    }

    public function getSessionMetadata(string $key)
    {
        return $this->sessionMetadata[$key] ?? null;
    }
}
