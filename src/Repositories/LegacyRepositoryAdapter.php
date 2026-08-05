<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Repositories;

use DateTime;
use PhpMqtt\Client\Contracts\Mqtt5Repository;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\PendingMessage;
use PhpMqtt\Client\PublishedMessage;
use PhpMqtt\Client\Subscription;

/**
 * Adds process-local MQTT 5 state to an existing legacy Repository.
 *
 * The wrapped repository still owns its original state. MQTT 5 queue metadata and a
 * subscription index are not durable across adapter recreation.
 *
 * @package PhpMqtt\Client\Repositories
 */
final class LegacyRepositoryAdapter implements Mqtt5Repository
{
    /** @var array<int, PublishedMessage> */
    private array $queuedPublications = [];

    /** @var array<string, Subscription> */
    private array $subscriptions = [];

    /** @var array<string, mixed> */
    private array $sessionMetadata = [];

    public function __construct(private Repository $repository)
    {
    }

    public function reset(): void
    {
        $this->repository->reset();
        $this->queuedPublications = [];
        $this->subscriptions      = [];
        $this->sessionMetadata    = [];
    }

    public function newMessageId(): int
    {
        return $this->repository->newMessageId();
    }

    public function countPendingOutgoingMessages(): int
    {
        return $this->repository->countPendingOutgoingMessages();
    }

    public function getPendingOutgoingMessage(int $messageId): ?PendingMessage
    {
        return $this->repository->getPendingOutgoingMessage($messageId);
    }

    public function getPendingOutgoingMessagesLastSentBefore(?DateTime $dateTime = null): array
    {
        return $this->repository->getPendingOutgoingMessagesLastSentBefore($dateTime);
    }

    public function getPendingOutgoingMessages(): array
    {
        return $this->repository->getPendingOutgoingMessagesLastSentBefore();
    }

    public function addPendingOutgoingMessage(PendingMessage $message): void
    {
        $this->repository->addPendingOutgoingMessage($message);
    }

    public function markPendingOutgoingPublishedMessageAsReceived(int $messageId): bool
    {
        return $this->repository->markPendingOutgoingPublishedMessageAsReceived($messageId);
    }

    public function removePendingOutgoingMessage(int $messageId): bool
    {
        $this->removeQueuedPublication($messageId);

        return $this->repository->removePendingOutgoingMessage($messageId);
    }

    public function countPendingIncomingMessages(): int
    {
        return $this->repository->countPendingIncomingMessages();
    }

    public function getPendingIncomingMessage(int $messageId): ?PendingMessage
    {
        return $this->repository->getPendingIncomingMessage($messageId);
    }

    public function addPendingIncomingMessage(PendingMessage $message): void
    {
        $this->repository->addPendingIncomingMessage($message);
    }

    public function removePendingIncomingMessage(int $messageId): bool
    {
        return $this->repository->removePendingIncomingMessage($messageId);
    }

    public function countSubscriptions(): int
    {
        return $this->repository->countSubscriptions();
    }

    public function addSubscription(Subscription $subscription): void
    {
        $this->repository->addSubscription($subscription);
        $this->subscriptions[$subscription->getTopicFilter()] = $subscription;
    }

    public function getSubscriptionsMatchingTopic(string $topicName): array
    {
        return $this->repository->getSubscriptionsMatchingTopic($topicName);
    }

    public function getSubscriptions(): array
    {
        return array_values($this->subscriptions);
    }

    public function removeSubscription(string $topicFilter): bool
    {
        unset($this->subscriptions[$topicFilter]);

        return $this->repository->removeSubscription($topicFilter);
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
