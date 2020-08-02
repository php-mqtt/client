<?php

declare(strict_types=1);

namespace Feature;

use PhpMqtt\Client\MQTTClient;
use PHPUnit\Framework\TestCase;

/**
 * Tests that connecting to an MQTT broker works.
 *
 * @package Feature
 */
class BasicConnectionTest extends TestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function test_connecting_to_broker_using_v3_1_works_correctly(): void
    {
        $client = new MQTTClient('localhost', 1883, 'test-client');

        $client->connect();
    }
}
