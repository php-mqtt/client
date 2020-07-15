<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Contracts;

/**
 * Implementations of this interface provide message id managing capabilities.
 *
 * Managing message ids is necessary because the MQTT protocol expects messages
 * to use unique ids. In other words, message ids may not be used a second time
 * while the message flow of the first usage is not completed yet.
 *
 * @package PhpMqtt\Client\Contracts
 */
interface MessageIdManager
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
}
