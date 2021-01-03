<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Contracts;

use DateTime;
use PhpMqtt\Client\Exceptions\PendingMessageAlreadyExistsException;
use PhpMqtt\Client\Exceptions\PendingMessageNotFoundException;
use PhpMqtt\Client\Exceptions\RepositoryException;
use PhpMqtt\Client\PendingMessage;
use PhpMqtt\Client\Subscription;

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
     * Re-initializes the repository by deleting all persisted data and restoring the original state,
     * which was given when the repository was first created. This is used when a clean session
     * is requested by a client during connection.
     *
     * @return bool
     */
    public function reset(): void;

    /**
     * Returns a new message id. The message id might have been used before,
     * but it is currently not being used (i.e. in a resend queue).
     *
     * @return int
     * @throws RepositoryException
     */
    public function newMessageId(): int;

    /**
     * Returns the number of pending outgoing messages.
     *
     * @return int
     */
    public function countPendingOutgoingMessages(): int;

    /**
     * Gets a pending outgoing message with the given message identifier, if found.
     *
     * @param int $messageId
     * @return PendingMessage|null
     */
    public function getPendingOutgoingMessage(int $messageId): ?PendingMessage;

    /**
     * Gets a list of pending outgoing messages last sent before the given date time.
     *
     * If date time is `null`, all pending messages are returned.
     *
     * The messages are returned in the same order they were added to the repository.
     *
     * @param DateTime|null $dateTime
     * @return PendingMessage[]
     */
    public function getPendingOutgoingMessagesLastSentBefore(DateTime $dateTime = null): array;

    /**
     * Adds a pending outgoing message to the repository.
     *
     * @param PendingMessage $message
     * @return void
     * @throws PendingMessageAlreadyExistsException
     */
    public function addPendingOutgoingMessage(PendingMessage $message): void;

    /**
     * Marks an existing pending outgoing published message as received in the repository.
     *
     * If the message does not exists, an exception is thrown,
     * otherwise `true` is returned if the message was marked as received, and `false`
     * in case it was already marked as received.
     *
     * @param int $messageId
     * @return bool
     * @throws PendingMessageNotFoundException
     */
    public function markPendingOutgoingPublishedMessageAsReceived(int $messageId): bool;

    /**
     * Removes a pending outgoing message from the repository.
     *
     * If a pending message with the given identifier is found and
     * successfully removed from the repository, `true` is returned.
     * Otherwise `false` will be returned.
     *
     * @param int $messageId
     * @return bool
     */
    public function removePendingOutgoingMessage(int $messageId): bool;

    /**
     * Returns the number of pending incoming messages.
     *
     * @return int
     */
    public function countPendingIncomingMessages(): int;

    /**
     * Gets a pending incoming message with the given message identifier, if found.
     *
     * @param int $messageId
     * @return PendingMessage|null
     */
    public function getPendingIncomingMessage(int $messageId): ?PendingMessage;

    /**
     * Adds a pending outgoing message to the repository.
     *
     * @param PendingMessage $message
     * @return void
     * @throws PendingMessageAlreadyExistsException
     */
    public function addPendingIncomingMessage(PendingMessage $message): void;

    /**
     * Removes a pending incoming message from the repository.
     *
     * If a pending message with the given identifier is found and
     * successfully removed from the repository, `true` is returned.
     * Otherwise `false` will be returned.
     *
     * @param int $messageId
     * @return bool
     */
    public function removePendingIncomingMessage(int $messageId): bool;

    /**
     * Returns the number of registered subscriptions.
     *
     * @return int
     */
    public function countSubscriptions(): int;

    /**
     * Adds a subscription to the repository.
     *
     * @param Subscription $subscription
     * @return void
     */
    public function addSubscription(Subscription $subscription): void;

    /**
     * Gets all subscriptions matching the given topic.
     *
     * @param string $topicName
     * @return Subscription[]
     */
    public function getSubscriptionsMatchingTopic(string $topicName): array;

    /**
     * Removes the subscription with the given topic filter from the repository.
     *
     * Returns `true` if a topic subscription existed and has been removed.
     * Otherwise, `false` is returned.
     *
     * @param string $topicFilter
     * @return bool
     */
    public function removeSubscription(string $topicFilter): bool;
}
