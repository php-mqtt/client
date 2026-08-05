<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Contracts;

use PhpMqtt\Client\Mqtt5\AuthenticationEvent;
use PhpMqtt\Client\Mqtt5\AuthenticationOptions;

/**
 * Handles MQTT 5 enhanced-authentication challenges.
 *
 * @package PhpMqtt\Client\Contracts
 */
interface AuthenticationHandler
{
    public function respond(AuthenticationEvent $event): ?AuthenticationOptions;
}
