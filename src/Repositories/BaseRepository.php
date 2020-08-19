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
     * Adds a pending published message to the repository.
     *
     * @param PublishedMessage $message
     * @return void
     */
    abstract public function addPendingPublishedMessage(PublishedMessage $message): void;

    /**
     * Adds a pending unsubscribe request to the repository.
     *
     * @param UnsubscribeRequest $request
     * @return void
     */
    abstract public function addPendingUnsubscribeRequest(UnsubscribeRequest $request): void;

    /**
     * Adds a pending publish confirmation to the repository.
     *
     * @param PublishedMessage $message
     * @return void
     * @throws PendingPublishConfirmationAlreadyExistsException
     */
    abstract public function addPendingPublishConfirmation(PublishedMessage $message): void;
}
