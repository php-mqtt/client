<?php

declare(strict_types=1);

namespace Feature;

use PhpMqtt\Client\MQTTClient;
use PHPUnit\Framework\TestCase;

/**
 * Tests that publishing messages and subscribing to topics using an MQTT broker works.
 *
 * @package Feature
 */
class PublishSubscribeTest extends TestCase
{
    /**
     * @small
     */
    public function test_publish_and_subscribing_using_quality_of_service_0_with_exact_topic_match_works(): void
    {
        // We publish a retained message, which allows us to disconnect this client and read the message
        // from another client, if everything works as expected.
        $publisher = new MQTTClient('localhost', 1883);
        $publisher->connect();

        $publisher->publish('test/foo/bar/baz', 'hello world', 0, true);

        // Here we connect and read the retained message using a second client.
        $subscriber = new MQTTClient('localhost', 1883);
        $subscriber->connect();

        $subscriber->subscribe('test/foo/bar/baz', function (string $topic, string $message, bool $retained) use ($subscriber) {
            // By asserting something here, we will avoid a no-assertions-in-test warning, making the test pass.
            $this->assertEquals('test/foo/bar/baz', $topic);
            $this->assertEquals('hello world', $message);
            $this->assertTrue($retained);

            $subscriber->interrupt(); // This allows us to exit the test as soon as possible.
        }, 0);

        $subscriber->loop();
    }
    /**
     * @small
     */
    public function test_publish_and_subscribing_using_quality_of_service_0_with_wildcard_topic_match_works(): void
    {
        // We publish a retained message, which allows us to disconnect this client and read the message
        // from another client, if everything works as expected.
        $publisher = new MQTTClient('localhost', 1883);
        $publisher->connect();

        $publisher->publish('test/foo/bar/baz', 'hello world', 0, true);

        // Here we connect and read the retained message using a second client.
        $subscriber = new MQTTClient('localhost', 1883);
        $subscriber->connect();

        $subscriber->subscribe('test/foo/bar/+', function (string $topic, string $message, bool $retained) use ($subscriber) {
            // By asserting something here, we will avoid a no-assertions-in-test warning, making the test pass.
            $this->assertEquals('test/foo/bar/baz', $topic);
            $this->assertEquals('hello world', $message);
            $this->assertTrue($retained);

            $subscriber->interrupt(); // This allows us to exit the test as soon as possible.
        }, 0);

        $subscriber->loop();
    }
}
