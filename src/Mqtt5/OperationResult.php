<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\ReasonCode;

/**
 * Typed result of an MQTT 5 acknowledged operation.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class OperationResult
{
    /** @var int[] */
    private array $reasonCodes;

    /**
     * @param int[] $reasonCodes
     */
    public function __construct(
        private int $packetType,
        private int $packetIdentifier,
        array $reasonCodes,
        private Properties $properties
    )
    {
        $this->reasonCodes = array_values($reasonCodes);
    }

    public function getPacketType(): int
    {
        return $this->packetType;
    }

    public function getPacketIdentifier(): int
    {
        return $this->packetIdentifier;
    }

    /**
     * @return int[]
     */
    public function getReasonCodes(): array
    {
        return $this->reasonCodes;
    }

    public function isSuccessful(): bool
    {
        foreach ($this->reasonCodes as $reasonCode) {
            if (ReasonCode::isError($reasonCode)) {
                return false;
            }
        }

        return true;
    }

    public function getProperties(): Properties
    {
        return $this->properties;
    }
}
