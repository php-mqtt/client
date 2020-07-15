<?php

declare(strict_types=1);

namespace PhpMqtt\Client\MessageIdManagers;

use PhpMqtt\Client\Contracts\MessageIdManager;

/**
 * Provides an in-memory implementation which manages message ids. The uniqueness is
 * guaranteed though, since track is kept of all generated message ids.
 *
 * @package PhpMqtt\Client\MessageIdManagers
 */
class MemoryMessageIdManager implements MessageIdManager
{
    /** @var int */
    private $lastMessageId = 0;

    /** @var int[] */
    private $reservedMessageIds = [];

    /**
     * Returns a new message id. The message id might have been used before,
     * but it is currently not being used (i.e. in a resend queue).
     *
     * @return int
     */
    public function newMessageId(): int
    {
        do
        {
            $this->rotateMessageId();

            $messageId = $this->lastMessageId;
        } while ($this->isReservedMessageId($messageId));

        $this->reservedMessageIds[] = $messageId;

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
        $this->reservedMessageIds = array_diff($this->reservedMessageIds, [$messageId]);
    }

    /**
     * This method rotates the message id. This normally means incrementing it,
     * but when we reach the limit (65535), the message id is reset to zero.
     *
     * @return void
     */
    protected function rotateMessageId(): void
    {
        if ($this->lastMessageId === 65535) {
            $this->lastMessageId = 0;
        }

        $this->lastMessageId++;
    }

    /**
     * Determines if the given message id is currently reserved.
     *
     * @param int $messageId
     * @return bool
     */
    protected function isReservedMessageId(int $messageId): bool
    {
        return in_array($messageId, $this->reservedMessageIds);
    }
}
