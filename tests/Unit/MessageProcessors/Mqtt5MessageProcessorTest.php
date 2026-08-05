<?php

declare(strict_types=1);

namespace Tests\Unit\MessageProcessors;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Logger;
use PhpMqtt\Client\MessageProcessors\Mqtt5MessageProcessor;
use PhpMqtt\Client\Mqtt5\ConnectionResult;
use PHPUnit\Framework\TestCase;

class Mqtt5MessageProcessorTest extends TestCase
{
    private Mqtt5MessageProcessor $processor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processor = new Mqtt5MessageProcessor('client', new Logger('localhost', 1883, 'client'));
    }

    public function test_builds_mqtt5_connect_with_empty_properties(): void
    {
        $wire = $this->processor->buildConnectMessage(new ConnectionSettings(), true);

        $this->assertSame('101300044d5154540502000a000006636c69656e74', bin2hex($wire));
    }

    public function test_processes_connack_and_exposes_connection_result(): void
    {
        $this->assertNull($this->processor->processConnectionHandshake(hex2bin('2003000000')));

        $this->assertInstanceOf(ConnectionResult::class, $this->processor->getConnectionResult());
        $this->assertFalse($this->processor->getConnectionResult()->isSessionPresent());
    }

    public function test_legacy_publish_wrapper_includes_mqtt5_property_length(): void
    {
        $wire = $this->processor->buildPublishMessage('a', 'b', 0, false);

        $this->assertSame('30050001610062', bin2hex($wire));
    }
}
