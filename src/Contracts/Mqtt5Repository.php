<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Contracts;

use PhpMqtt\Client\PublishedMessage;
use PhpMqtt\Client\Subscription;

/**
 * Persistence contract for MQTT 5 session and flow state.
 *
 * This is separate from Repository so existing repository implementations remain valid.
 *
 * @package PhpMqtt\Client\Contracts
 */
interface Mqtt5Repository extends Repository
{
    /**
     * @return \PhpMqtt\Client\PendingMessage[]
     */
    public function getPendingOutgoingMessages(): array;

    /**
     * @return Subscription[]
     */
    public function getSubscriptions(): array;

    public function addQueuedPublication(PublishedMessage $publication): void;

    /**
     * @return PublishedMessage[]
     */
    public function getQueuedPublications(): array;

    public function removeQueuedPublication(int $messageId): bool;

    /**
     * @param mixed $value
     */
    public function setSessionMetadata(string $key, $value): void;

    /**
     * @return mixed|null
     */
    public function getSessionMetadata(string $key);
}
