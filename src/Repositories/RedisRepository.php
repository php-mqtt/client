<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace PhpMqtt\Client\Repositories;

use Datetime;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\Exceptions\ConfigurationInvalidException;
use PhpMqtt\Client\Exceptions\PendingPublishConfirmationAlreadyExistsException;
use PhpMqtt\Client\PublishedMessage;
use PhpMqtt\Client\TopicSubscription;
use PhpMqtt\Client\UnsubscribeRequest;

/**
 * Provides a Redis repository implementation which manages message ids, subscriptions and pending messages.
 * The repository uses a unique identifier to prefix managed data, allowing multiple Redis repositories
 * to be used for several clients side-by-side.
 *
 * @package PhpMqtt\Client\Repositories
 */
class RedisRepository extends BaseRepository implements Repository
{
    private const KEY_LAST_MESSAGE_ID               = 'last_message_id';
    private const KEY_RESERVED_MESSAGE_IDS          = 'reserved_message_ids';
    private const KEY_TOPIC_SUBSCRIPTIONS           = 'topic_subscriptions';
    private const KEY_PENDING_PUBLISH_MESSAGES      = 'pending_published_messages';
    private const KEY_PENDING_UNSUBSCRIBE_REQUESTS  = 'pending_unsubscribe_requests';
    private const KEY_PENDING_PUBLISH_CONFIRMATIONS = 'pending_publish_confirmations';

    /** @var string */
    private $identifier;

    /** @var \Redis */
    private $redis;

    /**
     * RedisRepository constructor.
     *
     * @param string                       $identifier
     * @param RedisConnectionSettings|null $connectionSettings
     * @param \Redis|null                  $redis
     * @throws ConfigurationInvalidException
     */
    public function __construct(
        string $identifier = 'mqtt_client',
        RedisConnectionSettings $connectionSettings = null,
        \Redis $redis = null
    )
    {
        if ($connectionSettings === null && ($redis === null || !$redis->isConnected())) {
            throw new ConfigurationInvalidException('Redis repository requires connection settings or connected Redis instance.');
        }

        if ($redis !== null && $redis->isConnected()) {
            $this->redis = $redis;
        } else {
            $this->ensureConnectionSettingsAreValid($connectionSettings);

            $redis  = new \Redis();
            $result = $redis->connect($connectionSettings->getHost(), $connectionSettings->getPort(), $connectionSettings->getConnectTimeout());

            if ($result === false) {
                throw new ConfigurationInvalidException('Connecting to the Redis server failed. Is the configuration correct?');
            }

            $redis->select($connectionSettings->getDatabase());

            $this->redis = $redis;
        }

        $this->identifier = $identifier;

        $this->redis->setOption(\Redis::OPT_PREFIX, $this->identifier . ':');
        $this->redis->setOption(\Redis::OPT_SCAN, \Redis::SCAN_RETRY);
        $this->redis->setOption(\Redis::OPT_SERIALIZER, \Redis::SERIALIZER_PHP);

        $this->ensureRepositoryIsInitialized();
    }

    /**
     * Ensures the given connection settings are valid (i.e. usable to connect to a Redis instance).
     * This method does not validate whether connecting is actually possible.
     *
     * @param RedisConnectionSettings $connectionSettings
     * @return void
     * @throws ConfigurationInvalidException
     */
    protected function ensureConnectionSettingsAreValid(RedisConnectionSettings $connectionSettings): void
    {
        if ($connectionSettings->getHost() === null) {
            throw new ConfigurationInvalidException('No host has been configured for the Redis repository.');
        }

        if ($connectionSettings->getDatabase() < 0 || $connectionSettings->getDatabase() > 15) {
            throw new ConfigurationInvalidException('The configured Redis database is invalid. Only databases 0 to 15 are supported.');
        }
    }

    /**
     * This method initializes the required keys for this repository using the established Redis connection.
     *
     * @return void
     */
    protected function ensureRepositoryIsInitialized(): void
    {
        // Set the last used message id to zero, making the first message id to be used a 1.
        $this->redis->setnx(self::KEY_LAST_MESSAGE_ID, 0);
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

            $messageId = $this->redis->get(self::KEY_LAST_MESSAGE_ID);
        } while ($this->isReservedMessageId($messageId));

        $this->redis->sAdd(self::KEY_RESERVED_MESSAGE_IDS, $messageId);

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
        $this->redis->sRem(self::KEY_RESERVED_MESSAGE_IDS, $messageId);
    }

    /**
     * This method rotates the message id. This normally means incrementing it,
     * but when we reach the limit (65535), the message id is reset to zero.
     *
     * @return void
     */
    protected function rotateMessageId(): void
    {
        $lastMessageId = $this->redis->get(self::KEY_LAST_MESSAGE_ID);

        if ($lastMessageId === 65535) {
            $this->redis->set(self::KEY_LAST_MESSAGE_ID, 1);
        } else {
            $this->redis->set(self::KEY_LAST_MESSAGE_ID, $lastMessageId + 1);
        }
    }

    /**
     * Determines if the given message id is currently reserved.
     *
     * @param int $messageId
     * @return bool
     */
    protected function isReservedMessageId(int $messageId): bool
    {
        return $this->redis->sIsMember(self::KEY_RESERVED_MESSAGE_IDS, $messageId);
    }

    /**
     * Returns the number of registered topic subscriptions. The method does
     * not differentiate between pending and acknowledged subscriptions.
     *
     * @return int
     */
    public function countTopicSubscriptions(): int
    {
        return $this->redis->sCard(self::KEY_TOPIC_SUBSCRIPTIONS);
    }

    /**
     * Adds a topic subscription to the repository.
     *
     * @param TopicSubscription $subscription
     * @return void
     */
    public function addTopicSubscription(TopicSubscription $subscription): void
    {
        $this->redis->sAdd(self::KEY_TOPIC_SUBSCRIPTIONS, $subscription);
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

        $iterator = null;
        while ($subscriptions = $this->redis->sScan(self::KEY_TOPIC_SUBSCRIPTIONS, $iterator)) {
            /** @var TopicSubscription[] $subscriptions */
            foreach ($subscriptions as $subscription) {
                if ($subscription->getMessageId() === $messageId) {
                    $result[] = $subscription;
                }
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
        $iterator = null;
        while ($subscriptions = $this->redis->sScan(self::KEY_TOPIC_SUBSCRIPTIONS, $iterator)) {
            /** @var TopicSubscription[] $subscriptions */
            foreach ($subscriptions as $subscription) {
                if ($subscription->getTopic() === $topic) {
                    return $subscription;
                }
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

        $iterator = null;
        while ($subscriptions = $this->redis->sScan(self::KEY_TOPIC_SUBSCRIPTIONS, $iterator)) {
            /** @var TopicSubscription[] $subscriptions */
            foreach ($subscriptions as $subscription) {
                if (preg_match($subscription->getRegexifiedTopic(), $topic)) {
                    $result[] = $subscription;
                }
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
        $subscription = $this->getTopicSubscriptionByTopic($topic);

        if ($subscription === null) {
            return false;
        }

        if ($this->redis->sRem(self::KEY_TOPIC_SUBSCRIPTIONS, $subscription) === false) {
            return false;
        }

        return true;
    }

    /**
     * Returns the number of pending publish messages.
     *
     * @return int
     */
    public function countPendingPublishMessages(): int
    {
        return $this->redis->sCard(self::KEY_PENDING_PUBLISH_MESSAGES);
    }

    /**
     * Adds a pending published message to the repository.
     *
     * @param PublishedMessage $message
     * @return void
     */
    public function addPendingPublishedMessage(PublishedMessage $message): void
    {
        $this->redis->sAdd(self::KEY_TOPIC_SUBSCRIPTIONS, $message);
    }

    /**
     * Gets a pending published message with the given message identifier, if found.
     *
     * @param int $messageId
     * @return PublishedMessage|null
     */
    public function getPendingPublishedMessageWithMessageId(int $messageId): ?PublishedMessage
    {
        $iterator = null;
        while ($messages = $this->redis->sScan(self::KEY_PENDING_PUBLISH_MESSAGES, $iterator)) {
            /** @var PublishedMessage[] $messages */
            foreach ($messages as $message) {
                if ($message->getMessageId() === $messageId) {
                    return $message;
                }
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

        $iterator = null;
        while ($messages = $this->redis->sScan(self::KEY_PENDING_PUBLISH_MESSAGES, $iterator)) {
            /** @var PublishedMessage[] $messages */
            foreach ($messages as $message) {
                if ($message->hasBeenReceived() === false && $message->getLastSentAt() < $dateTime) {
                    $result[] = $message;
                }
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

        if ($this->redis->sRem(self::KEY_PENDING_PUBLISH_MESSAGES, $message) === false) {
            return false;
        }

        $message->setReceived(true);

        $this->addPendingPublishedMessage($message);

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

        if ($this->redis->sRem(self::KEY_PENDING_PUBLISH_MESSAGES, $message) === false) {
            return false;
        }

        return true;
    }

    /**
     * Returns the number of pending unsubscribe requests.
     *
     * @return int
     */
    public function countPendingUnsubscribeRequests(): int
    {
        return $this->redis->sCard(self::KEY_PENDING_UNSUBSCRIBE_REQUESTS);
    }

    /**
     * Adds a pending unsubscribe request to the repository.
     *
     * @param UnsubscribeRequest $request
     * @return void
     */
    public function addPendingUnsubscribeRequest(UnsubscribeRequest $request): void
    {
        $this->redis->sAdd(self::KEY_PENDING_UNSUBSCRIBE_REQUESTS, $request);
    }

    /**
     * Gets a pending unsubscribe request with the given message identifier, if found.
     *
     * @param int $messageId
     * @return UnsubscribeRequest|null
     */
    public function getPendingUnsubscribeRequestWithMessageId(int $messageId): ?UnsubscribeRequest
    {
        $iterator = null;
        while ($requests = $this->redis->sScan(self::KEY_PENDING_UNSUBSCRIBE_REQUESTS, $iterator)) {
            /** @var UnsubscribeRequest[] $requests */
            foreach ($requests as $request) {
                if ($request->getMessageId() === $messageId) {
                    return $request;
                }
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

        $iterator = null;
        while ($requests = $this->redis->sScan(self::KEY_PENDING_UNSUBSCRIBE_REQUESTS, $iterator)) {
            /** @var UnsubscribeRequest[] $requests */
            foreach ($requests as $request) {
                if ($request->getLastSentAt() < $dateTime) {
                    $result[] = $request;
                }
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

        if ($this->redis->sRem(self::KEY_PENDING_UNSUBSCRIBE_REQUESTS, $request) === false) {
            return false;
        }

        return true;
    }

    /**
     * Returns the number of pending publish confirmations.
     *
     * @return int
     */
    public function countPendingPublishConfirmations(): int
    {
        return $this->redis->sCard(self::KEY_PENDING_PUBLISH_CONFIRMATIONS);
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

        $this->redis->sAdd(self::KEY_PENDING_PUBLISH_CONFIRMATIONS, $message);
    }

    /**
     * Gets a pending publish confirmation with the given message identifier, if found.
     *
     * @param int $messageId
     * @return PublishedMessage|null
     */
    public function getPendingPublishConfirmationWithMessageId(int $messageId): ?PublishedMessage
    {
        $iterator = null;
        while ($confirmations = $this->redis->sScan(self::KEY_PENDING_PUBLISH_CONFIRMATIONS, $iterator)) {
            /** @var PublishedMessage[] $confirmations */
            foreach ($confirmations as $confirmation) {
                if ($confirmation->getMessageId() === $messageId) {
                    return $confirmation;
                }
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

        if ($this->redis->sRem(self::KEY_PENDING_PUBLISH_CONFIRMATIONS, $confirmation) === false) {
            return false;
        }

        return true;
    }
}
