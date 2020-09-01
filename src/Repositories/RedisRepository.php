<?php

/** @noinspection PhpComposerExtensionStubsInspection */

declare(strict_types=1);

namespace PhpMqtt\Client\Repositories;

use Datetime;
use PhpMqtt\Client\Contracts\Repository;
use PhpMqtt\Client\Exceptions\ConfigurationInvalidException;
use PhpMqtt\Client\Exceptions\PendingMessageAlreadyExistsException;
use PhpMqtt\Client\Exceptions\PendingMessageNotFoundException;
use PhpMqtt\Client\Exceptions\RepositoryException;
use PhpMqtt\Client\PendingMessage;
use PhpMqtt\Client\PublishedMessage;
use PhpMqtt\Client\Subscription;

/**
 * Provides a Redis repository implementation which manages message ids, subscriptions and pending messages.
 * The repository uses a unique identifier to prefix managed data, allowing multiple Redis repository instances
 * to be used for several clients side-by-side.
 *
 * @package PhpMqtt\Client\Repositories
 */
class RedisRepository implements Repository
{
    private const KEY_NEXT_MESSAGE_ID           = 'next_message_id';
    private const KEY_SUBSCRIPTIONS             = 'subscriptions';
    private const KEY_PENDING_OUTGOING_MESSAGES = 'pending_outgoing_messages';
    private const KEY_PENDING_INCOMING_MESSAGES = 'pending_incoming_messages';

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
        // Set the next to be used message id to one, since zero is an invalid message id.
        $this->redis->setnx(self::KEY_NEXT_MESSAGE_ID, 1);
    }

    /**
     * Returns a new message id. The message id might have been used before,
     * but it is currently not being used (i.e. in a resend queue).
     *
     * @return int
     * @throws RepositoryException
     */
    public function newMessageId(): int
    {
        if ($this->countPendingOutgoingMessages() >= 65535) {
            // This should never happen, as the server receive queue is normally smaller than the actual
            // number of message ids. Also, when using MQTT 5.0 the server can specify a smaller receive
            // queue size (mosquitto for example has 20 by default), so the client has to implement the
            // logic to honor this restriction and fallback to the protocol limit.
            throw new RepositoryException('No more message identifiers available. The queue is full.');
        }

        $nextMessageId = $this->redis->get(static::KEY_NEXT_MESSAGE_ID);
        while ($this->redis->hExists(static::KEY_PENDING_OUTGOING_MESSAGES, $nextMessageId)) {
            $nextMessageId++;
            if ($nextMessageId > 65535) {
                $nextMessageId = 1;
            }
        }

        $this->redis->set(static::KEY_NEXT_MESSAGE_ID, $nextMessageId);

        return $nextMessageId;
    }

    /**
     * Returns the number of pending outgoing messages.
     *
     * @return int
     */
    public function countPendingOutgoingMessages(): int
    {
        $result = $this->redis->hLen(static::KEY_PENDING_OUTGOING_MESSAGES);

        if ($result === false) {
            $result = 0;
        }

        return $result;
    }

    /**
     * Gets a pending outgoing message with the given message identifier, if found.
     *
     * @param int $messageId
     * @return PendingMessage|null
     */
    public function getPendingOutgoingMessage(int $messageId): ?PendingMessage
    {
        /** @var PendingMessage|false $pendingMessage */
        $pendingMessage = $this->redis->hGet(static::KEY_PENDING_OUTGOING_MESSAGES, $messageId);

        if ($pendingMessage === false) {
            return null;
        }

        return $pendingMessage;
    }

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
    public function getPendingOutgoingMessagesLastSentBefore(DateTime $dateTime = null): array
    {
        $result = [];

        $iterator = null;
        while ($messages = $this->redis->hScan(self::KEY_PENDING_OUTGOING_MESSAGES, $iterator)) {
            /** @var PendingMessage[] $messages */
            foreach ($messages as $message) {
                if ($message->getLastSentAt() < $dateTime) {
                    $result[] = $message;
                }
            }
        }

        return $result;
    }

    /**
     * Adds a pending outgoing message to the repository.
     *
     * @param PendingMessage $message
     * @return void
     * @throws PendingMessageAlreadyExistsException
     */
    public function addPendingOutgoingMessage(PendingMessage $message): void
    {
        $added = $this->redis->hSetNx(static::KEY_PENDING_OUTGOING_MESSAGES, $message->getMessageId(), $message);

        if ($added === false) {
            throw new PendingMessageAlreadyExistsException($message->getMessageId());
        }
    }

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
    public function markPendingOutgoingPublishedMessageAsReceived(int $messageId): bool
    {
        $message = $this->getPendingOutgoingMessage($messageId);

        if ($message === null || !($message instanceof PublishedMessage)) {
            throw new PendingMessageNotFoundException($messageId);
        }

        $result = $message->markAsReceived();

        $this->redis->hSet(static::KEY_PENDING_OUTGOING_MESSAGES, $messageId, $message);

        return $result;
    }

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
    public function removePendingOutgoingMessage(int $messageId): bool
    {
        $result = $this->redis->hDel(static::KEY_PENDING_OUTGOING_MESSAGES, $messageId);

        if ($result === false) {
            return false;
        }

        return true;
    }

    /**
     * Returns the number of pending incoming messages.
     *
     * @return int
     */
    public function countPendingIncomingMessages(): int
    {
        $result = $this->redis->hLen(static::KEY_PENDING_INCOMING_MESSAGES);

        if ($result === false) {
            $result = 0;
        }

        return $result;
    }

    /**
     * Gets a pending incoming message with the given message identifier, if found.
     *
     * @param int $messageId
     * @return PendingMessage|null
     */
    public function getPendingIncomingMessage(int $messageId): ?PendingMessage
    {
        /** @var PendingMessage|false $pendingMessage */
        $pendingMessage = $this->redis->hGet(static::KEY_PENDING_INCOMING_MESSAGES, $messageId);

        if ($pendingMessage === false) {
            return null;
        }

        return $pendingMessage;
    }

    /**
     * Adds a pending outgoing message to the repository.
     *
     * @param PendingMessage $message
     * @return void
     * @throws PendingMessageAlreadyExistsException
     */
    public function addPendingIncomingMessage(PendingMessage $message): void
    {
        $added = $this->redis->hSetNx(static::KEY_PENDING_INCOMING_MESSAGES, $message->getMessageId(), $message);

        if ($added === false) {
            throw new PendingMessageAlreadyExistsException($message->getMessageId());
        }
    }

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
    public function removePendingIncomingMessage(int $messageId): bool
    {
        $result = $this->redis->hDel(static::KEY_PENDING_INCOMING_MESSAGES, $messageId);

        if ($result === false) {
            return false;
        }

        return true;
    }

    /**
     * Returns the number of registered subscriptions.
     *
     * @return int
     */
    public function countSubscriptions(): int
    {
        return $this->redis->sCard(static::KEY_SUBSCRIPTIONS);
    }

    /**
     * Adds a subscription to the repository.
     *
     * @param Subscription $subscription
     * @return void
     */
    public function addSubscription(Subscription $subscription): void
    {
        $this->redis->sAdd(static::KEY_SUBSCRIPTIONS, $subscription);
    }

    /**
     * Gets all subscriptions matching the given criteria.
     *
     * @param string|null $topicName
     * @param int|null    $subscriptionId
     * @return Subscription[]
     */
    public function getMatchingSubscriptions(string $topicName = null, int $subscriptionId = null): array
    {
        $result = [];

        $iterator = null;
        while ($subscriptions = $this->redis->sScan(self::KEY_SUBSCRIPTIONS, $iterator)) {
            /** @var Subscription[] $subscriptions */
            foreach ($subscriptions as $subscription) {
                if ($topicName !== null && !$subscription->matchTopicFilter($topicName)) {
                    continue;
                }

                if ($subscriptionId !== null && $subscription->getSubscriptionId() !== $subscriptionId) {
                    continue;
                }

                $result[] = $subscription;
            }
        }

        return $result;
    }

    /**
     * Removes the subscription with the given topic filter from the repository.
     *
     * Returns `true` if a topic subscription existed and has been removed.
     * Otherwise, `false` is returned.
     *
     * @param string $topicFilter
     * @return bool
     */
    public function removeSubscription(string $topicFilter): bool
    {
        $subscription = $this->getTopicSubscriptionByTopicFilter($topicFilter);

        if ($subscription === null) {
            return false;
        }

        $result = $this->redis->sRem(self::KEY_SUBSCRIPTIONS, $subscription);
        if ($result === false || $result === 0) {
            return false;
        }

        return true;
    }

    /**
     * Find a topic subscription with the given topic filter.
     *
     * @param string $topicFilter
     * @return Subscription|null
     */
    protected function getTopicSubscriptionByTopicFilter(string $topicFilter): ?Subscription
    {
        $iterator = null;
        while ($subscriptions = $this->redis->sScan(self::KEY_SUBSCRIPTIONS, $iterator)) {
            /** @var Subscription[] $subscriptions */
            foreach ($subscriptions as $subscription) {
                if ($subscription->getTopicFilter() === $topicFilter) {
                    return $subscription;
                }
            }
        }

        return null;
    }
}
