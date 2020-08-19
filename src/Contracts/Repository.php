<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Contracts;

use DateTime;
use PhpMqtt\Client\Exceptions\PendingPublishConfirmationAlreadyExistsException;
use PhpMqtt\Client\PublishedMessage;
use PhpMqtt\Client\TopicSubscription;
use PhpMqtt\Client\UnsubscribeRequest;

/**
 * Implementations of this interface provide storage capabilities to an MQTT client.
 *
 * Services of this type have three primary goals:
 *   1. Providing and keeping track of message identifiers, since they must be unique
 *      within the message flow (i.e. there may not be duplicates of different messages
 *      at the same time).
 *   2. Storing and keeping track of subscriptions, which is especially necessary in case
 *      of persisted sessions.
 *   3. Storing and keeping track of pending messages (i.e. sent messages, which have not
 *      been acknowledged yet by the broker).
 *
 * @package PhpMqtt\Client\Contracts
 */
interface Repository
{
    /**
     * Returns a new message id. The message id might have been used before,
     * but it is currently not being used (i.e. in a resend queue).
     *
     * @return int
     */
    public function newMessageId(): int;

    /**
     * Releases the given message id, allowing it to be reused in the future.
     *
     * @param int $messageId
     * @return void
     */
    public function releaseMessageId(int $messageId): void;

    /**
     * Returns the number of registered topic subscriptions. The method does
     * not differentiate between pending and acknowledged subscriptions.
     *
     * @return int
     */
    public function countTopicSubscriptions(): int;

    /**
     * Adds a topic subscription to the repository.
     *
     * @param TopicSubscription $subscription
     * @return void
     */
    public function addTopicSubscription(TopicSubscription $subscription): void;

    /**
     * Get all topic subscriptions with the given message identifier.
     *
     * @param int $messageId
     * @return TopicSubscription[]
     */
    public function getTopicSubscriptionsWithMessageId(int $messageId): array;

    /**
     * Find a topic subscription with the given topic.
     *
     * @param string $topic
     * @return TopicSubscription|null
     */
    public function getTopicSubscriptionByTopic(string $topic): ?TopicSubscription;

    /**
     * Get all topic subscriptions matching the given topic.
     *
     * @param string $topic
     * @return TopicSubscription[]
     */
    public function getTopicSubscriptionsMatchingTopic(string $topic): array;

    /**
     * Removes the topic subscription with the given topic from the repository.
     * Returns true if a topic subscription existed and has been removed.
     * Otherwise, false is returned.
     *
     * @param string $topic
     * @return bool
     */
    public function removeTopicSubscription(string $topic): bool;

    /**
     * Returns the number of pending publish messages.
     *
     * @return int
     */
    public function countPendingPublishMessages(): int;

    /**
     * Adds a pending published message to the repository.
     *
     * @param PublishedMessage $message
     * @return void
     */
    public function addPendingPublishedMessage(PublishedMessage $message): void;

    /**
     * Gets a pending published message with the given message identifier, if found.
     *
     * @param int $messageId
     * @return PublishedMessage|null
     */
    public function getPendingPublishedMessageWithMessageId(int $messageId): ?PublishedMessage;

    /**
     * Gets a list of pending published messages last sent before the given date time.
     *
     * @param DateTime $dateTime
     * @return PublishedMessage[]
     */
    public function getPendingPublishedMessagesLastSentBefore(DateTime $dateTime): array;

    /**
     * Marks the pending published message with the given message identifier as received.
     * If the message has no QoS level of 2, is not found or has already been received,
     * false is returned. Otherwise the result will be true.
     *
     * @param int $messageId
     * @return bool
     */
    public function markPendingPublishedMessageAsReceived(int $messageId): bool;

    /**
     * Removes a pending published message from the repository. If a pending message
     * with the given identifier is found and successfully removed from the repository,
     * `true` is returned. Otherwise `false` will be returned.
     *
     * @param int $messageId
     * @return bool
     */
    public function removePendingPublishedMessage(int $messageId): bool;

    /**
     * Returns the number of pending unsubscribe requests.
     *
     * @return int
     */
    public function countPendingUnsubscribeRequests(): int;

    /**
     * Adds a pending unsubscribe request to the repository.
     *
     * @param UnsubscribeRequest $request
     * @return void
     */
    public function addPendingUnsubscribeRequest(UnsubscribeRequest $request): void;

    /**
     * Gets a pending unsubscribe request with the given message identifier, if found.
     *
     * @param int $messageId
     * @return UnsubscribeRequest|null
     */
    public function getPendingUnsubscribeRequestWithMessageId(int $messageId): ?UnsubscribeRequest;

    /**
     * Gets a list of pending unsubscribe requests last sent before the given date time.
     *
     * @param DateTime $dateTime
     * @return UnsubscribeRequest[]
     */
    public function getPendingUnsubscribeRequestsLastSentBefore(DateTime $dateTime): array;

    /**
     * Removes a pending unsubscribe requests from the repository. If a pending request
     * with the given identifier is found and successfully removed from the repository,
     * `true` is returned. Otherwise `false` will be returned.
     *
     * @param int $messageId
     * @return bool
     */
    public function removePendingUnsubscribeRequest(int $messageId): bool;

    /**
     * Returns the number of pending publish confirmations.
     *
     * @return int
     */
    public function countPendingPublishConfirmations(): int;

    /**
     * Adds a pending publish confirmation to the repository.
     *
     * @param PublishedMessage $message
     * @return void
     * @throws PendingPublishConfirmationAlreadyExistsException
     */
    public function addPendingPublishConfirmation(PublishedMessage $message): void;

    /**
     * Gets a pending publish confirmation with the given message identifier, if found.
     *
     * @param int $messageId
     * @return PublishedMessage|null
     */
    public function getPendingPublishConfirmationWithMessageId(int $messageId): ?PublishedMessage;

    /**
     * Removes the pending publish confirmation with the given message identifier
     * from the repository. This is normally done as soon as a transaction has been
     * successfully finished by the publisher.
     *
     * @param int $messageId
     * @return bool
     */
    public function removePendingPublishConfirmation(int $messageId): bool;
}
