<?php

declare(strict_types=1);

namespace PhpMqtt\Client\MessageProcessors;

use PhpMqtt\Client\Concerns\TranscodesData;
use PhpMqtt\Client\Concerns\WorksWithBuffers;
use Psr\Log\LoggerInterface;

/**
 * This message processor serves as base for other message processors, providing
 * default implementations for some methods.
 *
 * @package PhpMqtt\Client\MessageProcessors
 */
abstract class BaseMessageProcessor
{
    use TranscodesData,
        WorksWithBuffers;

    const QOS_AT_MOST_ONCE  = 0;
    const QOS_AT_LEAST_ONCE = 1;
    const QOS_EXACTLY_ONCE  = 2;

    protected LoggerInterface $logger;

    /**
     * BaseMessageProcessor constructor.
     *
     * @param LoggerInterface $logger
     */
    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
