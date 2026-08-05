<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol;

/**
 * MQTT quality of service levels.
 *
 * @package PhpMqtt\Client\Protocol
 */
final class Qos
{
    public const AT_MOST_ONCE  = 0;
    public const AT_LEAST_ONCE = 1;
    public const EXACTLY_ONCE  = 2;

    private function __construct()
    {
    }

    public static function assertValid(int $qualityOfService, bool $allowExactlyOnce = true): void
    {
        $maximum = $allowExactlyOnce ? self::EXACTLY_ONCE : self::AT_LEAST_ONCE;

        if ($qualityOfService < self::AT_MOST_ONCE || $qualityOfService > $maximum) {
            throw new \InvalidArgumentException(sprintf('Invalid MQTT QoS level [%d].', $qualityOfService));
        }
    }
}
