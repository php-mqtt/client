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
 * An in-memory implementation of the repository which loses all its data when
 * being deleted or when a script ends. Using this implementation is fine for
 * simple uses cases and testing though.
 *
 * @package PhpMqtt\Client\Repositories
 */
class MemoryRepository implements Repository
{
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
     * Adds a topic subscription to the repository.
     *
     * @param string   $topic
     * @param callable $callback
     * @param int      $messageId
     * @param int      $qualityOfService
     * @return TopicSubscription
     */
    public function addNewTopicSubscription(string $topic, callable $callback, int $messageId, int $qualityOfService): TopicSubscription
    {
        $subscription = new TopicSubscription($topic, $callback, $messageId, $qualityOfService);

        $this->addTopicSubscription($subscription);

        return $subscription;
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
        // TODO: add message id already pending exception

        $this->pendingPublishedMessages->attach($message);
    }

    /**
     * Adds a new pending published message with the given settings to the repository.
     *
     * @param int           $messageId
     * @param string        $topic
     * @param string        $message
     * @param int           $qualityOfService
     * @param bool          $retain
     * @param DateTime|null $sentAt
     * @return PublishedMessage
     */
    public function addNewPendingPublishedMessage(
        int $messageId,
        string $topic,
        string $message,
        int $qualityOfService,
        bool $retain,
        DateTime $sentAt = null
    ): PublishedMessage
    {
        $message = new PublishedMessage($messageId, $topic, $message, $qualityOfService, $retain, $sentAt);

        $this->addPendingPublishedMessage($message);

        return $message;
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
     * Adds a new pending unsubscribe request with the given settings to the repository.
     *
     * @param int           $messageId
     * @param string        $topic
     * @param DateTime|null $sentAt
     * @return UnsubscribeRequest
     */
    public function addNewPendingUnsubscribeRequest(int $messageId, string $topic, DateTime $sentAt = null): UnsubscribeRequest
    {
        $request = new UnsubscribeRequest($messageId, $topic, $sentAt);

        $this->addPendingUnsubscribeRequest($request);

        return $request;
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
     * Adds a new pending publish confirmation with the given settings to the repository.
     *
     * @param int    $messageId
     * @param string $topic
     * @param string $message
     * @return PublishedMessage
     * @throws PendingPublishConfirmationAlreadyExistsException
     */
    public function addNewPendingPublishConfirmation(int $messageId, string $topic, string $message): PublishedMessage
    {
        $message = new PublishedMessage($messageId, $topic, $message, 2, false);

        $this->addPendingPublishConfirmation($message);

        return $message;
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
