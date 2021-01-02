<?php

declare(strict_types=1);

namespace Tests;

/**
 * A base class for all tests.
 *
 * @package Tests
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $mqttBrokerHost;

    /** @var int */
    protected $mqttBrokerPort;

    /**
     * {@inheritdoc}
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->mqttBrokerHost = getenv('MQTT_BROKER_HOST');
        $this->mqttBrokerPort = intval(getenv('MQTT_BROKER_PORT'));
    }
}
