<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use DateTime;

/**
 * Represents an unsubscribe request. Is used to store pending unsubscribe requests.
 * If an unsubscribe request is not acknowledged by the broker, having one of these
 * objects allows the client to resend the request.
 *
 * @package PhpMqtt\Client
 */
class UnsubscribeRequest
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
