<?php

declare(strict_types=1);

namespace Tests\Unit\MessageProcessors;

use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\Contracts\AuthenticationHandler;
use PhpMqtt\Client\Logger;
use PhpMqtt\Client\MessageProcessors\Mqtt5MessageProcessor;
use PhpMqtt\Client\Mqtt5\AuthenticationEvent;
use PhpMqtt\Client\Mqtt5\AuthenticationOptions;
use PhpMqtt\Client\Mqtt5\ConnectionOptions;
use PhpMqtt\Client\Mqtt5\ConnectionResult;
use PhpMqtt\Client\Protocol\Mqtt5\PacketCodec;
use PhpMqtt\Client\Protocol\Mqtt5\PropertyCodec;
use PhpMqtt\Client\Protocol\Packets\AuthPacket;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\PropertyIdentifier;
use PhpMqtt\Client\Protocol\ReasonCode;
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

    public function test_enhanced_authentication_challenge_uses_configured_handler(): void
    {
        $handler = new class implements AuthenticationHandler {
            public function respond(AuthenticationEvent $event): ?AuthenticationOptions
            {
                return new AuthenticationOptions($event->getMethod(), 'response');
            }
        };
        $this->processor->setConnectionOptions(new ConnectionOptions(
            null,
            null,
            new AuthenticationOptions('SCRAM', 'initial'),
            $handler
        ));
        $challenge = (new PacketCodec())->encode(
            new AuthPacket(
                ReasonCode::CONTINUE_AUTHENTICATION,
                Properties::empty()
                    ->with(PropertyIdentifier::AUTHENTICATION_METHOD, 'SCRAM')
                    ->with(PropertyIdentifier::AUTHENTICATION_DATA, 'challenge')
            ),
            PropertyCodec::DIRECTION_SERVER_TO_CLIENT
        );

        $response = $this->processor->processConnectionHandshake($challenge);
        $packet   = (new PacketCodec())->decode($response, PropertyCodec::DIRECTION_CLIENT_TO_SERVER);

        $this->assertInstanceOf(AuthPacket::class, $packet);
        $this->assertSame('response', $packet->getProperties()->get(PropertyIdentifier::AUTHENTICATION_DATA));
    }
}
