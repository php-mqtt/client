<?php

declare(strict_types=1);

namespace Tests\Unit\Mqtt5;

use PhpMqtt\Client\Mqtt5\FlowStage;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\PropertyIdentifier;
use PhpMqtt\Client\PublishedMessage;
use PHPUnit\Framework\TestCase;

class PublishedMessageStateTest extends TestCase
{
    public function test_qos2_flow_moves_from_pubrec_to_pubcomp_stage(): void
    {
        $publication = new PublishedMessage(1, 'topic', 'payload', 2, false);

        $this->assertSame(FlowStage::AWAITING_PUBREC, $publication->getFlowStage());
        $this->assertTrue($publication->markAsReceived());
        $this->assertSame(FlowStage::AWAITING_PUBCOMP, $publication->getFlowStage());
        $this->assertFalse($publication->markAsReceived());
    }

    public function test_zero_message_expiry_is_expired_immediately(): void
    {
        $publication = new PublishedMessage(
            1,
            'topic',
            'payload',
            1,
            false,
            Properties::empty()->with(PropertyIdentifier::MESSAGE_EXPIRY_INTERVAL, 0)
        );

        $this->assertTrue($publication->hasExpired());
        $this->assertSame(0, $publication->getRemainingExpiryInterval());
    }

    public function test_queued_stage_is_explicit(): void
    {
        $publication = new PublishedMessage(1, 'topic', 'payload', 1, false, null, FlowStage::QUEUED);

        $this->assertSame(FlowStage::QUEUED, $publication->getFlowStage());
    }
}
