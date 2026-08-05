<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

/**
 * Explicit MQTT QoS delivery stages.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class FlowStage
{
    public const QUEUED             = 'queued';
    public const AWAITING_PUBACK    = 'awaiting-puback';
    public const AWAITING_PUBREC    = 'awaiting-pubrec';
    public const AWAITING_PUBREL    = 'awaiting-pubrel';
    public const AWAITING_PUBCOMP   = 'awaiting-pubcomp';
    public const COMPLETED          = 'completed';
    public const FAILED             = 'failed';

    private function __construct()
    {
    }
}
