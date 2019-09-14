<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Contracts;

use DateTime;
use PhpMqtt\Client\MQTTPublishedMessage;
use PhpMqtt\Client\MQTTTopicSubscription;

interface Repository
{
    /**
     * Adds a topic subscription to the repository.
     * 
     * @param MQTTTopicSubscription $subscription
     * @return void
     */
    public function addTopicSubscription(MQTTTopicSubscription $subscription): void;

    /**
     * Adds a new topic subscription with the given settings to the repository.
     *
     * @param string   $topic
     * @param callable $callback
     * @param int      $messageId
     * @param int      $qualityOfService
     * @return MQTTTopicSubscription
     */
    public function addNewTopicSubscription(string $topic, callable $callback, int $messageId, int $qualityOfService): MQTTTopicSubscription;

    /**
     * Get all topic subscriptions with the given message identifier.
     *
     * @param int $messageId
     * @return MQTTTopicSubscription[]
     */
    public function getTopicSubscriptionsWithMessageId(int $messageId): array;

    /**
     * Get all topic subscriptions matching the given topic.
     * 
     * @param string $topic
     * @return MQTTTopicSubscription[]
     */
    public function getTopicSubscriptionsMatchingTopic(string $topic): array;

    /**
     * Adds a pending published message to the repository.
     * 
     * @param MQTTPublishedMessage $message
     * @return void
     */
    public function addPendingPublishedMessage(MQTTPublishedMessage $message): void;

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
    public function addNewPendingPublishedMessage(int $messageId, string $topic, string $message, int $qualityOfService, bool $retain, DateTime $sentAt = null): MQTTPublishedMessage;

    /**
     * Gets a pending published message with the given message identifier, if found.
     * 
     * @param int $messageId
     * @return MQTTPublishedMessage|null
     */
    public function getPendingPublishedMessageWithMessageId(int $messageId): ?MQTTPublishedMessage;

    /**
     * Gets a list of pending published messages last sent before the given date time.
     * 
     * @param DateTime $dateTime
     * @return MQTTPublishedMessage[]
     */
    public function getPendingPublishedMessagesLastSentBefore(DateTime $dateTime): array;

    /**
     * Removes a pending published message from the repository. If a pending message
     * with the given identifier is found and successfully removed from the repository,
     * `true` is returned. Otherwise `false` will be returned.
     * 
     * @param int $messageId
     * @return bool
     */
    public function removePendingPublishedMessage(int $messageId): bool;
}
