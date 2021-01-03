<?php

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that connecting to an MQTT broker works.
 *
 * @package Tests\Feature
 */
class BasicConnectionTest extends TestCase
{
    /**
     * @doesNotPerformAssertions
     */
    public function test_connecting_to_broker_using_v3_1_works_correctly(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-client');

        $client->connect();

        $client->disconnect();
    }
}
