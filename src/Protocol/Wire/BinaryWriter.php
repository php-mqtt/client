<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Wire;

/**
 * MQTT binary data writer.
 *
 * @package PhpMqtt\Client\Protocol\Wire
 */
final class BinaryWriter
{
    public const MAXIMUM_VARIABLE_BYTE_INTEGER = 268435455;

    private string $buffer = '';

    public function writeByte(int $value): self
    {
        self::assertRange($value, 0, 0xFF, 'byte');
        $this->buffer .= chr($value);

        return $this;
    }

    public function writeUInt16(int $value): self
    {
        self::assertRange($value, 0, 0xFFFF, 'two-byte integer');
        $this->buffer .= pack('n', $value);

        return $this;
    }

    public function writeUInt32(int $value): self
    {
        self::assertRange($value, 0, 0xFFFFFFFF, 'four-byte integer');
        $this->buffer .= pack('N', $value);

        return $this;
    }

    public function writeVariableByteInteger(int $value): self
    {
        self::assertRange($value, 0, self::MAXIMUM_VARIABLE_BYTE_INTEGER, 'variable-byte integer');

        do {
            $digit = $value % 128;
            $value = intdiv($value, 128);

            if ($value > 0) {
                $digit |= 0x80;
            }

            $this->buffer .= chr($digit);
        } while ($value > 0);

        return $this;
    }

    public function writeBinaryData(string $value): self
    {
        self::assertRange(strlen($value), 0, 0xFFFF, 'binary data length');
        $this->writeUInt16(strlen($value));
        $this->buffer .= $value;

        return $this;
    }

    public function writeUtf8String(string $value): self
    {
        if (!Utf8Validator::isValid($value)) {
            throw new \InvalidArgumentException('The value is not valid MQTT UTF-8 data.');
        }

        return $this->writeBinaryData($value);
    }

    public function writeUtf8Pair(string $name, string $value): self
    {
        return $this->writeUtf8String($name)->writeUtf8String($value);
    }

    public function writeRaw(string $value): self
    {
        $this->buffer .= $value;

        return $this;
    }

    public function getBuffer(): string
    {
        return $this->buffer;
    }

    private static function assertRange(int $value, int $minimum, int $maximum, string $type): void
    {
        if ($value < $minimum || $value > $maximum) {
            throw new \InvalidArgumentException(sprintf('The %s value [%d] is out of range.', $type, $value));
        }
    }
}
