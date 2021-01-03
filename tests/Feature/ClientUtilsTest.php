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
}
