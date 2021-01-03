<?php

/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that the client utils (optional methods) work as intended.
 *
 * @package Tests\Feature
 */
class ClientUtilsTest extends TestCase
{
    public function test_counts_sent_and_received_bytes_correctly(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-byte-count');

        $client->connect();

        // Even the connection request and acknowledgement have bytes.
        $this->assertGreaterThan(0, $client->getSentBytes());
        $this->assertGreaterThan(0, $client->getReceivedBytes());

        // We therefore remember the current transfer stats and send some more data.
        $sentBytesBeforePublish     = $client->getSentBytes();
        $receivedBytesBeforePublish = $client->getReceivedBytes();

        $client->publish('foo/bar', 'baz-01', MqttClient::QOS_AT_MOST_ONCE);
        $client->publish('foo/bar', 'baz-02', MqttClient::QOS_AT_LEAST_ONCE);
        $client->publish('foo/bar', 'baz-03', MqttClient::QOS_EXACTLY_ONCE);

        $this->assertGreaterThan($sentBytesBeforePublish, $client->getSentBytes());
        $this->assertSame($receivedBytesBeforePublish, $client->getReceivedBytes());

        // Also we receive all acknowledgements to update our transfer stats correctly.
        $client->loop(true, true);

        $this->assertGreaterThan($receivedBytesBeforePublish, $client->getReceivedBytes());

        $client->disconnect();
    }

    public function test_loop_event_handlers_are_called_for_each_loop_iteration(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-loop-event-handler');

        $loopCount           = 0;
        $previousElapsedTime = 0;
        $client->registerLoopEventHandler(function (MqttClient $client, float $elapsedTime) use (&$loopCount, &$previousElapsedTime) {
            $this->assertGreaterThanOrEqual($previousElapsedTime, $elapsedTime);

            $previousElapsedTime = $elapsedTime;

            $loopCount++;

            if ($loopCount >= 3) {
                $client->interrupt();
                return;
            }
        });

        $client->connect();

        $client->loop();

        $this->assertSame(3, $loopCount);

        $client->disconnect();
    }

    public function test_loop_event_handler_can_be_unregistered_and_will_not_be_called_anymore(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-loop-event-handler');

        $loopCount = 0;
        $handler = function (MqttClient $client) use (&$loopCount) {
            $loopCount++;

            if ($loopCount >= 1) {
                $client->interrupt();
                return;
            }
        };

        $client->registerLoopEventHandler($handler);

        $client->connect();

        $client->loop();

        $this->assertSame(1, $loopCount);

        $client->unregisterLoopEventHandler($handler);

        $client->loop(true, true);

        $this->assertSame(1, $loopCount);

        $client->disconnect();
    }

    public function test_all_loop_event_handlers_can_be_unregistered_and_will_not_be_called_anymore(): void
    {
        $client = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'test-loop-event-handler');

        $loopCount1 = 0;
        $loopCount2 = 0;
        $handler1 = function (MqttClient $client) use (&$loopCount1) {
            $loopCount1++;

            if ($loopCount1 >= 1) {
                $client->interrupt();
                return;
            }
        };
        $handler2 = function () use (&$loopCount2) {
            $loopCount2++;
        };

        $client->registerLoopEventHandler($handler1);
        $client->registerLoopEventHandler($handler2);

        $client->connect();

        $client->loop();

        $this->assertSame(1, $loopCount1);
        $this->assertSame(1, $loopCount2);

        $client->unregisterLoopEventHandler();

        $client->loop(true, true);

        $this->assertSame(1, $loopCount1);
        $this->assertSame(1, $loopCount2);

        $client->disconnect();
    }
}
