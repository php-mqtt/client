<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Wire;

use PhpMqtt\Client\Exceptions\MalformedPacketException;

/**
 * Bounds-checked MQTT binary data reader.
 *
 * @package PhpMqtt\Client\Protocol\Wire
 */
final class BinaryReader
{
    private int $offset = 0;

    public function __construct(private string $buffer)
    {
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getRemainingLength(): int
    {
        return strlen($this->buffer) - $this->offset;
    }

    public function hasRemaining(): bool
    {
        return $this->getRemainingLength() > 0;
    }

    public function readByte(): int
    {
        return ord($this->readRaw(1));
    }

    public function readUInt16(): int
    {
        return unpack('nvalue', $this->readRaw(2))['value'];
    }

    public function readUInt32(): int
    {
        return unpack('Nvalue', $this->readRaw(4))['value'];
    }

    public function readVariableByteInteger(): int
    {
        $value      = 0;
        $multiplier = 1;
        $bytes      = 0;

        do {
            if ($bytes === 4) {
                throw new MalformedPacketException('Variable-byte integer exceeds four bytes.');
            }

            $digit = $this->readByte();
            $value += ($digit & 0x7F) * $multiplier;
            $bytes++;

            if (($digit & 0x80) === 0) {
                break;
            }

            $multiplier *= 128;
        } while (true);

        if ($bytes > 1 && $value < (128 ** ($bytes - 1))) {
            throw new MalformedPacketException('Variable-byte integer is not canonically encoded.');
        }

        if ($value > BinaryWriter::MAXIMUM_VARIABLE_BYTE_INTEGER) {
            throw new MalformedPacketException('Variable-byte integer exceeds the MQTT maximum.');
        }

        return $value;
    }

    public function readBinaryData(): string
    {
        return $this->readRaw($this->readUInt16());
    }

    public function readUtf8String(): string
    {
        $value = $this->readBinaryData();

        if (!Utf8Validator::isValid($value)) {
            throw new MalformedPacketException('A UTF-8 string contains prohibited or malformed data.');
        }

        return $value;
    }

    /**
     * @return string[]
     */
    public function readUtf8Pair(): array
    {
        return [$this->readUtf8String(), $this->readUtf8String()];
    }

    public function readRaw(int $length): string
    {
        if ($length < 0 || $length > $this->getRemainingLength()) {
            throw new MalformedPacketException(sprintf(
                'Packet is truncated: requested [%d] bytes with [%d] remaining.',
                $length,
                $this->getRemainingLength()
            ));
        }

        $result        = substr($this->buffer, $this->offset, $length);
        $this->offset += $length;

        return $result;
    }

    public function readRemaining(): string
    {
        return $this->readRaw($this->getRemainingLength());
    }

    public function createLimitedReader(int $length): self
    {
        return new self($this->readRaw($length));
    }
}
