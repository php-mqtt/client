<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use PhpMqtt\Client\Mqtt5\FlowStage;
use PhpMqtt\Client\PublishedMessage;
use PhpMqtt\Client\Repositories\MemoryRepository;
use PHPUnit\Framework\TestCase;

class MemoryRepositoryMqtt5Test extends TestCase
{
    public function test_repository_tracks_queued_flow_stage_and_session_metadata(): void
    {
        $repository  = new MemoryRepository();
        $publication = new PublishedMessage(1, 'topic', 'payload', 1, false, null, FlowStage::QUEUED);

        $repository->addPendingOutgoingMessage($publication);
        $repository->addQueuedPublication($publication);
        $repository->setSessionMetadata('assignedClientId', 'assigned');

        $this->assertSame([$publication], $repository->getPendingOutgoingMessages());
        $this->assertSame([$publication], $repository->getQueuedPublications());
        $this->assertSame('assigned', $repository->getSessionMetadata('assignedClientId'));

        $repository->removePendingOutgoingMessage(1);
        $this->assertSame([], $repository->getQueuedPublications());
    }
}
