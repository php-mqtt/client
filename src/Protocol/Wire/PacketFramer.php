<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Wire;

use PhpMqtt\Client\Exceptions\MalformedPacketException;

/**
 * Incrementally extracts MQTT control packets from a network buffer.
 *
 * @package PhpMqtt\Client\Protocol\Wire
 */
final class PacketFramer
{
    public const MAXIMUM_PACKET_SIZE = 268435460;

    public function __construct(private int $maximumPacketSize = self::MAXIMUM_PACKET_SIZE)
    {
        if ($maximumPacketSize < 2 || $maximumPacketSize > self::MAXIMUM_PACKET_SIZE) {
            throw new \InvalidArgumentException('The maximum packet size is outside the MQTT packet size range.');
        }
    }

    public function setMaximumPacketSize(int $maximumPacketSize): void
    {
        if ($maximumPacketSize < 2 || $maximumPacketSize > self::MAXIMUM_PACKET_SIZE) {
            throw new \InvalidArgumentException('The maximum packet size is outside the MQTT packet size range.');
        }

        $this->maximumPacketSize = $maximumPacketSize;
    }

    public function tryExtract(string $buffer, ?string &$packet = null, int &$requiredBytes = -1): bool
    {
        $bufferLength = strlen($buffer);

        if ($bufferLength < 2) {
            return false;
        }

        $remainingLength = 0;
        $multiplier      = 1;
        $lengthBytes     = 0;
        $index           = 1;

        do {
            if ($index >= $bufferLength) {
                return false;
            }

            if ($lengthBytes === 4) {
                throw new MalformedPacketException('Remaining Length exceeds four bytes.');
            }

            $digit            = ord($buffer[$index]);
            $remainingLength += ($digit & 0x7F) * $multiplier;
            $lengthBytes++;
            $index++;

            if (($digit & 0x80) === 0) {
                break;
            }

            $multiplier *= 128;
        } while (true);

        if ($lengthBytes > 1 && $remainingLength < (128 ** ($lengthBytes - 1))) {
            throw new MalformedPacketException('Remaining Length is not canonically encoded.');
        }

        $packetLength = 1 + $lengthBytes + $remainingLength;

        if ($packetLength > $this->maximumPacketSize) {
            throw new MalformedPacketException(sprintf(
                'Packet length [%d] exceeds the configured inbound maximum [%d].',
                $packetLength,
                $this->maximumPacketSize
            ));
        }

        if ($bufferLength < $packetLength) {
            $requiredBytes = $packetLength;
            return false;
        }

        $packet = substr($buffer, 0, $packetLength);

        return true;
    }
}
