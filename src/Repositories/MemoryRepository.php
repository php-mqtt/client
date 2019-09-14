<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Repositories;

use Datetime;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\Exceptions\PendingPublishConfirmationAlreadyExistsException;
use PhpMqtt\Client\PublishedMessage;
use PhpMqtt\Client\TopicSubscription;
use PhpMqtt\Client\UnsubscribeRequest;

class MemoryRepository implements Repository
{
    /** @var TopicSubscription[] */
    private $topicSubscriptions = [];

    /** @var PublishedMessage[] */
    private $pendingPublishedMessages = [];

    /** @var UnsubscribeRequest[] */
    private $pendingUnsubscribeRequests = [];

    /** @var string[] */
    private $pendingPublishConfirmations = [];

    /**
     * Adds a topic subscription to the repository.
     * 
     * @param TopicSubscription $subscription
     * @return void
     */
    public function addTopicSubscription(TopicSubscription $subscription): void
    {
        $this->topicSubscriptions[] = $subscription;
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
        return array_values(array_filter($this->topicSubscriptions, function (TopicSubscription $subscription) use ($messageId) {
            return $subscription->getMessageId() === $messageId;
        }));
    }

    /**
     * Get all topic subscriptions matching the given topic.
     * 
     * @param string $topic
     * @return TopicSubscription[]
     */
    public function getTopicSubscriptionsMatchingTopic(string $topic): array
    {
        return array_values(array_filter($this->topicSubscriptions, function (TopicSubscription $subscription) use ($topic) {
            return preg_match($subscription->getRegexifiedTopic(), $topic);
        }));
    }

    /**
     * Adds a pending published message to the repository.
     * 
     * @param PublishedMessage $message
     * @return void
     */
    public function addPendingPublishedMessage(PublishedMessage $message): void
    {
        $this->pendingPublishedMessages[] = $message;
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
    public function addNewPendingPublishedMessage(int $messageId, string $topic, string $message, int $qualityOfService, bool $retain, DateTime $sentAt = null): PublishedMessage
    {
        $message = new PublishedMessage($messageId, $topic, $message, $qualityOfService, $retain, $sentAt);

        $this->addPendingPublishedMessage($message);

        return $message;

        // TODO: add message id already pending exception
    }

    /**
     * Gets a pending published message with the given message identifier, if found.
     * 
     * @param int $messageId
     * @return PublishedMessage|null
     */
    public function getPendingPublishedMessageWithMessageId(int $messageId): ?PublishedMessage
    {
        $messages = array_filter($this->pendingPublishedMessages, function (PublishedMessage $message) use ($messageId) {
            return $message->getMessageId() === $messageId;
        });

        return empty($messages) ? null : $messages[0];
    }

    /**
     * Gets a list of pending published messages last sent before the given date time.
     * 
     * @param DateTime $dateTime
     * @return PublishedMessage[]
     */
    public function getPendingPublishedMessagesLastSentBefore(DateTime $dateTime): array
    {
        return array_values(array_filter($this->pendingPublishedMessages, function (PublishedMessage $message) use ($dateTime) {
            return $message->getLastSentAt() < $dateTime;
        }));
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

        $this->pendingPublishedMessages = array_diff($this->pendingPublishedMessages, [$message]);
        
        return true;
    }

    /**
     * Adds a pending unsubscribe request to the repository.
     *
     * @param UnsubscribeRequest $request
     * @return void
     */
    public function addPendingUnsubscribeRequest(UnsubscribeRequest $request): void
    {
        $this->pendingUnsubscribeRequests[] = $request;
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
        $requests = array_filter($this->pendingUnsubscribeRequests, function (UnsubscribeRequest $request) use ($messageId) {
            return $request->getMessageId() === $messageId;
        });

        return empty($requests) ? null : $requests[0];
    }

    /**
     * Gets a list of pending unsubscribe requests last sent before the given date time.
     *
     * @param DateTime $dateTime
     * @return UnsubscribeRequest[]
     */
    public function getPendingUnsubscribeRequestsLastSentBefore(DateTime $dateTime): array
    {
        return array_values(array_filter($this->pendingUnsubscribeRequests, function (UnsubscribeRequest $request) use ($dateTime) {
            return $request->getLastSentAt() < $dateTime;
        }));
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

        $this->pendingUnsubscribeRequests = array_diff($this->pendingUnsubscribeRequests, [$request]);

        return true;
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

        $this->pendingPublishConfirmations[] = $message;
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
        $messages = array_filter($this->pendingPublishConfirmations, function (PublishedMessage $message) use ($messageId) {
            return $message->getMessageId() === $messageId;
        });

        return empty($messages) ? null : $messages[0];
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
        if (!in_array($messageId, $this->pendingPublishConfirmations)) {
            return false;
        }

        $this->pendingPublishConfirmations = array_diff($this->pendingPublishConfirmations, [$messageId]);

        return true;
    }
}
