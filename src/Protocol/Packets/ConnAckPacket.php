<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Packets;

use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\ReasonCode;

/**
 * MQTT 5 CONNACK packet.
 *
 * @package PhpMqtt\Client\Protocol\Packets
 */
final class ConnAckPacket extends AbstractControlPacket
{
    public function __construct(private bool $sessionPresent, private int $reasonCode, Properties $properties)
    {
        parent::__construct(PacketType::CONNACK, $properties);
        ReasonCode::assertAllowed(PacketType::CONNACK, $reasonCode);

        if ($reasonCode !== ReasonCode::SUCCESS && $sessionPresent) {
            throw new \InvalidArgumentException('Session Present cannot be set on a failed CONNACK.');
        }
    }

    public function isSessionPresent(): bool
    {
        return $this->sessionPresent;
    }

    public function getReasonCode(): int
    {
        return $this->reasonCode;
    }
}
