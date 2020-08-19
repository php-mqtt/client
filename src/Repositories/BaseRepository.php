<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Repositories;

use DateTime;
use PhpMqtt\Client\Exceptions\PendingPublishConfirmationAlreadyExistsException;
use PhpMqtt\Client\PublishedMessage;
use PhpMqtt\Client\TopicSubscription;
use PhpMqtt\Client\UnsubscribeRequest;

/**
 * A common base for repository implementations.
 *
 * @package PhpMqtt\Client\Repositories
 */
abstract class BaseRepository
{
    /**
     * Adds a topic subscription to the repository.
     *
     * @param TopicSubscription $subscription
     * @return void
     */
    abstract public function addTopicSubscription(TopicSubscription $subscription): void;

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
     * Adds a pending published message to the repository.
     *
     * @param PublishedMessage $message
     * @return void
     */
    abstract public function addPendingPublishedMessage(PublishedMessage $message): void;

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
     * Adds a pending unsubscribe request to the repository.
     *
     * @param UnsubscribeRequest $request
     * @return void
     */
    abstract public function addPendingUnsubscribeRequest(UnsubscribeRequest $request): void;

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
     * Adds a pending publish confirmation to the repository.
     *
     * @param PublishedMessage $message
     * @return void
     * @throws PendingPublishConfirmationAlreadyExistsException
     */
    abstract public function addPendingPublishConfirmation(PublishedMessage $message): void;

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
}
