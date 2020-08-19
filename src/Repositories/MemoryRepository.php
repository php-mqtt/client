<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Repositories;

use Datetime;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\Exceptions\PendingPublishConfirmationAlreadyExistsException;
use PhpMqtt\Client\PublishedMessage;
use PhpMqtt\Client\TopicSubscription;
use PhpMqtt\Client\UnsubscribeRequest;
use SplObjectStorage;

/**
 * Provides an in-memory implementation which manages message ids, subscriptions and pending messages.
 * Instances of this type do not persist any data and are only meant for simple uses cases and testing.
 *
 * @package PhpMqtt\Client\Repositories
 */
class MemoryRepository implements Repository
{
    /** @var int */
    private $lastMessageId = 0;

    /** @var int[] */
    private $reservedMessageIds = [];

    /** @var SplObjectStorage|TopicSubscription[] */
    private $topicSubscriptions;

    /** @var SplObjectStorage|PublishedMessage[] */
    private $pendingPublishedMessages;

    /** @var SplObjectStorage|UnsubscribeRequest[] */
    private $pendingUnsubscribeRequests;

    /** @var SplObjectStorage|PublishedMessage[] */
    private $pendingPublishConfirmations;

    /**
     * MemoryRepository constructor.
     */
    public function __construct()
    {
        $this->topicSubscriptions          = new SplObjectStorage();
        $this->pendingPublishedMessages    = new SplObjectStorage();
        $this->pendingUnsubscribeRequests  = new SplObjectStorage();
        $this->pendingPublishConfirmations = new SplObjectStorage();
    }

    /**
     * Returns a new message id. The message id might have been used before,
     * but it is currently not being used (i.e. in a resend queue).
     *
     * @return int
     */
    public function newMessageId(): int
    {
        do {
            $this->rotateMessageId();

            $messageId = $this->lastMessageId;
        } while ($this->isReservedMessageId($messageId));

        $this->reservedMessageIds[] = $messageId;

        return $messageId;
    }

    /**
     * Releases the given message id, allowing it to be reused in the future.
     *
     * @param int $messageId
     * @return void
     */
    public function releaseMessageId(int $messageId): void
    {
        $this->reservedMessageIds = array_diff($this->reservedMessageIds, [$messageId]);
    }

    /**
     * This method rotates the message id. This normally means incrementing it,
     * but when we reach the limit (65535), the message id is reset to zero.
     *
     * @return void
     */
    protected function rotateMessageId(): void
    {
        if ($this->lastMessageId === 65535) {
            $this->lastMessageId = 0;
        }

        $this->lastMessageId++;
    }

    /**
     * Determines if the given message id is currently reserved.
     *
     * @param int $messageId
     * @return bool
     */
    protected function isReservedMessageId(int $messageId): bool
    {
        return in_array($messageId, $this->reservedMessageIds);
    }

    /**
     * Returns the number of registered topic subscriptions. The method does
     * not differentiate between pending and acknowledged subscriptions.
     *
     * @return int
     */
    public function countTopicSubscriptions(): int
    {
        return $this->topicSubscriptions->count();
    }

    /**
     * Adds a topic subscription to the repository.
     *
     * @param TopicSubscription $subscription
     * @return void
     */
    public function addTopicSubscription(TopicSubscription $subscription): void
    {
        $this->topicSubscriptions->attach($subscription);
    }

    /**
     * Get all topic subscriptions with the given message identifier.
     *
     * @param int $messageId
     * @return TopicSubscription[]
     */
    public function getTopicSubscriptionsWithMessageId(int $messageId): array
    {
        $result = [];

        foreach ($this->topicSubscriptions as $subscription) {
            if ($subscription->getMessageId() === $messageId) {
                $result[] = $subscription;
            }
        }

        return $result;
    }

    /**
     * Find a topic subscription with the given topic.
     *
     * @param string $topic
     * @return TopicSubscription|null
     */
    public function getTopicSubscriptionByTopic(string $topic): ?TopicSubscription
    {
        foreach ($this->topicSubscriptions as $subscription) {
            if ($subscription->getTopic() === $topic) {
                return $subscription;
            }
        }

        return null;
    }

    /**
     * Get all topic subscriptions matching the given topic.
     *
     * @param string $topic
     * @return TopicSubscription[]
     */
    public function getTopicSubscriptionsMatchingTopic(string $topic): array
    {
        $result = [];

        foreach ($this->topicSubscriptions as $subscription) {
            if (preg_match($subscription->getRegexifiedTopic(), $topic)) {
                $result[] = $subscription;
            }
        }

        return $result;
    }

    /**
     * Removes the topic subscription with the given topic from the repository.
     * Returns true if a topic subscription existed and has been removed.
     * Otherwise, false is returned.
     *
     * @param string $topic
     * @return bool
     */
    public function removeTopicSubscription(string $topic): bool
    {
        $result = false;

        foreach ($this->topicSubscriptions as $subscription) {
            if ($subscription->getTopic() === $topic) {
                $this->topicSubscriptions->detach($subscription);
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * Returns the number of pending publish messages.
     *
     * @return int
     */
    public function countPendingPublishMessages(): int
    {
        return $this->pendingPublishedMessages->count();
    }

    /**
     * Adds a pending published message to the repository.
     *
     * @param PublishedMessage $message
     * @return void
     */
    public function addPendingPublishedMessage(PublishedMessage $message): void
    {
        $this->pendingPublishedMessages->attach($message);
    }

    /**
     * Gets a pending published message with the given message identifier, if found.
     *
     * @param int $messageId
     * @return PublishedMessage|null
     */
    public function getPendingPublishedMessageWithMessageId(int $messageId): ?PublishedMessage
    {
        foreach ($this->pendingPublishedMessages as $message) {
            if ($message->getMessageId() === $messageId) {
                return $message;
            }
        }

        return null;
    }

    /**
     * Gets a list of pending published messages last sent before the given date time.
     *
     * @param DateTime $dateTime
     * @return PublishedMessage[]
     */
    public function getPendingPublishedMessagesLastSentBefore(DateTime $dateTime): array
    {
        $result = [];

        foreach ($this->pendingPublishedMessages as $message) {
            if ($message->hasBeenReceived() === false && $message->getLastSentAt() < $dateTime) {
                $result[] = $message;
            }
        }

        return $result;
    }

    /**
     * Marks the pending published message with the given message identifier as received.
     * If the message has no QoS level of 2, is not found or has already been received,
     * false is returned. Otherwise the result will be true.
     *
     * @param int $messageId
     * @return bool
     */
    public function markPendingPublishedMessageAsReceived(int $messageId): bool
    {
        $message = $this->getPendingPublishedMessageWithMessageId($messageId);

        if ($message === null || $message->getQualityOfServiceLevel() < 2 || $message->hasBeenReceived()) {
            return false;
        }

        $message->setReceived(true);

        return true;
    }

    /**
     * Removes a pending published message from the repository. If a pending message
     * with the given identifier is found and successfully removed from the repository,
     * `true` is returned. Otherwise `false` will be returned.
     *
     * @param int $messageId
     * @return bool
     */
    public function removePendingPublishedMessage(int $messageId): bool
    {
        $message = $this->getPendingPublishedMessageWithMessageId($messageId);

        if ($message === null) {
            return false;
        }

        $this->pendingPublishedMessages->detach($message);

        return true;
    }

    /**
     * Returns the number of pending unsubscribe requests.
     *
     * @return int
     */
    public function countPendingUnsubscribeRequests(): int
    {
        return $this->pendingUnsubscribeRequests->count();
    }

    /**
     * Adds a pending unsubscribe request to the repository.
     *
     * @param UnsubscribeRequest $request
     * @return void
     */
    public function addPendingUnsubscribeRequest(UnsubscribeRequest $request): void
    {
        $this->pendingUnsubscribeRequests->attach($request);
    }

    /**
     * Gets a pending unsubscribe request with the given message identifier, if found.
     *
     * @param int $messageId
     * @return UnsubscribeRequest|null
     */
    public function getPendingUnsubscribeRequestWithMessageId(int $messageId): ?UnsubscribeRequest
    {
        foreach ($this->pendingUnsubscribeRequests as $request) {
            if ($request->getMessageId() === $messageId) {
                return $request;
            }
        }

        return null;
    }

    /**
     * Gets a list of pending unsubscribe requests last sent before the given date time.
     *
     * @param DateTime $dateTime
     * @return UnsubscribeRequest[]
     */
    public function getPendingUnsubscribeRequestsLastSentBefore(DateTime $dateTime): array
    {
        $result = [];

        foreach ($this->pendingUnsubscribeRequests as $request) {
            if ($request->getLastSentAt() < $dateTime) {
                $result[] = $request;
            }
        }

        return $result;
    }

    /**
     * Removes a pending unsubscribe requests from the repository. If a pending request
     * with the given identifier is found and successfully removed from the repository,
     * `true` is returned. Otherwise `false` will be returned.
     *
     * @param int $messageId
     * @return bool
     */
    public function removePendingUnsubscribeRequest(int $messageId): bool
    {
        $request = $this->getPendingUnsubscribeRequestWithMessageId($messageId);

        if ($request === null) {
            return false;
        }

        $this->pendingUnsubscribeRequests->detach($request);

        return true;
    }

    /**
     * Returns the number of pending publish confirmations.
     *
     * @return int
     */
    public function countPendingPublishConfirmations(): int
    {
        return $this->pendingPublishConfirmations->count();
    }

    /**
     * Adds a pending publish confirmation to the repository.
     *
     * @param PublishedMessage $message
     * @return void
     * @throws PendingPublishConfirmationAlreadyExistsException
     */
    public function addPendingPublishConfirmation(PublishedMessage $message): void
    {
        if ($this->getPendingPublishConfirmationWithMessageId($message->getMessageId()) !== null) {
            throw new PendingPublishConfirmationAlreadyExistsException($message->getMessageId());
        }

        $this->pendingPublishConfirmations->attach($message);
    }

    /**
     * Gets a pending publish confirmation with the given message identifier, if found.
     *
     * @param int $messageId
     * @return PublishedMessage|null
     */
    public function getPendingPublishConfirmationWithMessageId(int $messageId): ?PublishedMessage
    {
        foreach ($this->pendingPublishConfirmations as $confirmation) {
            if ($confirmation->getMessageId() === $messageId) {
                return $confirmation;
            }
        }

        return null;
    }

    /**
     * Removes the given message identifier from the list of pending publish confirmations.
     * This is normally done as soon as a transaction has been successfully finished.
     *
     * @param int $messageId
     * @return bool
     */
    public function removePendingPublishConfirmation(int $messageId): bool
    {
        $confirmation = $this->getPendingPublishConfirmationWithMessageId($messageId);

        if ($confirmation === null) {
            return false;
        }

        $this->pendingPublishConfirmations->detach($confirmation);

        return true;
    }
}
