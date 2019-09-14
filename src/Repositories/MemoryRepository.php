<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Repositories;

use Datetime;
use PhpMqtt\Client\PublishedMessage;
use PhpMqtt\Client\TopicSubscription;

class MemoryRepository implements \PhpMqtt\Client\Contracts\Repository
{
    /** @var TopicSubscription[] */
    private $topicSubscriptions = [];

    /** @var PublishedMessage[] */
    private $pendingPublishedMessages = [];

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
            return $messageId->getMessageId() === $messageId;
        });

        if (empty($messages)) {
            return null;
        }

        return $messages[0];
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
}
