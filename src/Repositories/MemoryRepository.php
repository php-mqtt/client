<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Repositories;

use Datetime;
use PhpMqtt\Client\MQTTPublishedMessage;
use PhpMqtt\Client\MQTTTopicSubscription;

class MemoryRepository implements \PhpMqtt\Client\Contracts\Repository
{
    /** @var MQTTTopicSubscription[] */
    private $topicSubscriptions = [];

    /** @var MQTTPublishedMessage[] */
    private $pendingPublishedMessages = [];

    /**
     * Adds a topic subscription to the repository.
     * 
     * @param MQTTTopicSubscription $subscription
     * @return void
     */
    public function addTopicSubscription(MQTTTopicSubscription $subscription): void
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
     * @return MQTTTopicSubscription
     */
    public function addNewTopicSubscription(string $topic, callable $callback, int $messageId, int $qualityOfService): MQTTTopicSubscription
    {
        $subscription = new MQTTTopicSubscription($topic, $callback, $messageId, $qualityOfService);
        
        $this->addTopicSubscription($subscription);

        return $subscription;
    }

    /**
     * Get all topic subscriptions with the given message identifier.
     *
     * @param int $messageId
     * @return MQTTTopicSubscription[]
     */
    public function getTopicSubscriptionsWithMessageId(int $messageId): array
    {
        return array_values(array_filter($this->topicSubscriptions, function (MQTTTopicSubscription $subscription) use ($messageId) {
            return $subscription->getMessageId() === $messageId;
        }));
    }

    /**
     * Get all topic subscriptions matching the given topic.
     * 
     * @param string $topic
     * @return MQTTTopicSubscription[]
     */
    public function getTopicSubscriptionsMatchingTopic(string $topic): array
    {
        return array_values(array_filter($this->topicSubscriptions, function (MQTTTopicSubscription $subscription) use ($topic) {
            return preg_match($subscription->getRegexifiedTopic(), $topic);
        }));
    }

    /**
     * Adds a pending published message to the repository.
     * 
     * @param MQTTPublishedMessage $message
     * @return void
     */
    public function addPendingPublishedMessage(MQTTPublishedMessage $message): void
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
     * @return MQTTPublishedMessage
     */
    public function addNewPendingPublishedMessage(int $messageId, string $topic, string $message, int $qualityOfService, bool $retain, DateTime $sentAt = null): MQTTPublishedMessage
    {
        $message = new MQTTPublishedMessage($messageId, $topic, $message, $qualityOfService, $retain, $sentAt);

        $this->addPendingPublishedMessage($message);

        return $message;

        // TODO: add message id already pending exception
    }

    /**
     * Gets a pending published message with the given message identifier, if found.
     * 
     * @param int $messageId
     * @return MQTTPublishedMessage|null
     */
    public function getPendingPublishedMessageWithMessageId(int $messageId): ?MQTTPublishedMessage
    {
        $messages = array_filter($this->pendingPublishedMessages, function (MQTTPublishedMessage $message) use ($messageId) {
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
     * @return MQTTPublishedMessage[]
     */
    public function getPendingPublishedMessagesLastSentBefore(DateTime $dateTime): array
    {
        return array_values(array_filter($this->pendingPublishedMessages, function (MQTTPublishedMessage $message) use ($dateTime) {
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
