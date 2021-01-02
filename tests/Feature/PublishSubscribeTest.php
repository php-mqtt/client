<?php

declare(strict_types=1);

namespace Tests\Feature;

use PhpMqtt\Client\MqttClient;
use Tests\TestCase;

/**
 * Tests that publishing messages and subscribing to topics using an MQTT broker works.
 *
 * @package Tests\Feature
 */
class PublishSubscribeTest extends TestCase
{
    /**
     * @small
     */
    public function test_publish_and_subscribing_using_quality_of_service_0_with_exact_topic_match_works(): void
    {
        // We connect and subscribe to a topic using the first client.
        $subscriber = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber');
        $subscriber->connect();

        $subscriber->subscribe('test/foo/bar/baz', function (string $topic, string $message, bool $retained) use ($subscriber) {
            // By asserting something here, we will avoid a no-assertions-in-test warning, making the test pass.
            $this->assertEquals('test/foo/bar/baz', $topic);
            $this->assertEquals('hello world', $message);
            $this->assertFalse($retained);

            $subscriber->interrupt(); // This allows us to exit the test as soon as possible.
        }, 0);

        // We publish a message from a second client on the same topic.
        $publisher = new MqttClient('localhost', 1883, 'publisher');
        $publisher->connect();

        $publisher->publish('test/foo/bar/baz', 'hello world', 0, false);

        // Finally, we loop on the subscriber to (hopefully) receive the published message.
        $subscriber->loop();
    }
    /**
     * @small
     */
    public function test_publish_and_subscribing_using_quality_of_service_0_with_wildcard_topic_match_works(): void
    {
        // We connect and subscribe to a topic using the first client.
        $subscriber = new MqttClient($this->mqttBrokerHost, $this->mqttBrokerPort, 'subscriber');
        $subscriber->connect();

        $subscriber->subscribe('test/foo/bar/+', function (string $topic, string $message, bool $retained) use ($subscriber) {
            // By asserting something here, we will avoid a no-assertions-in-test warning, making the test pass.
            $this->assertEquals('test/foo/bar/baz', $topic);
            $this->assertEquals('hello world', $message);
            $this->assertFalse($retained);

            $subscriber->interrupt(); // This allows us to exit the test as soon as possible.
        }, 0);

        // We publish a message from a second client on the same topic.
        $publisher = new MqttClient('localhost', 1883, 'publisher');
        $publisher->connect();

        $publisher->publish('test/foo/bar/baz', 'hello world', 0, false);

        // Finally, we loop on the subscriber to (hopefully) receive the published message.
        $subscriber->loop();
    }
}
