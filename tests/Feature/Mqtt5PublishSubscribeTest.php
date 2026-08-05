<?php

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\Exceptions\ConnectingToBrokerFailedException;
use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

class Mqtt5PublishSubscribeTest extends TestCase
{
    public function test_mqtt5_publish_and_subscribe_with_legacy_methods(): void
    {
        $namespace = bin2hex(random_bytes(8));
        $topic     = sprintf('php-mqtt/client/%s', $namespace);
        $client    = new MqttClient(
            $this->mqttBrokerHost,
            $this->mqttBrokerPort,
            sprintf('mqtt5-%s', $namespace),
            MqttClient::MQTT_5_0
        );

        try {
            $client->connect();
        } catch (ConnectingToBrokerFailedException $exception) {
            if ($exception->getCode() === ConnectingToBrokerFailedException::EXCEPTION_CONNECTION_PROTOCOL_VERSION) {
                $this->markTestSkipped('The configured broker does not support MQTT 5.');
            }

            throw $exception;
        }

        $received = null;
        $client->subscribe($topic, static function (string $receivedTopic, string $message) use (&$received): void {
            $received = [$receivedTopic, $message];
        });
        $client->publish($topic, 'mqtt5');

        $deadline = microtime(true) + 2;
        while ($received === null && microtime(true) < $deadline) {
            $client->loopOnce(microtime(true), true, 10000);
        }

        $client->disconnect();

        $this->assertSame([$topic, 'mqtt5'], $received);
    }
}
