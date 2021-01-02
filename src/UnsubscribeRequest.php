<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

/**
 * Represents an unsubscribe request.
 *
 * @package PhpMqtt\Client
 */
class UnsubscribeRequest extends PendingMessage
{
    /** @var string[] */
    private array $topicFilters;

    /**
     * Creates a new unsubscribe request object.
     *
     * @param int      $messageId
     * @param string[] $topicFilters
     */
    public function __construct(int $messageId, array $topicFilters)
    {
        parent::__construct($messageId);

        $this->topicFilters = array_values($topicFilters);
    }

    /**
     * Returns the topic filters in this request.
     *
     * @return string[]
     */
    public function getTopicFilters(): array
    {
        return $this->topicFilters;
    }
}
