<?php

declare(strict_types=1);

namespace Tests\Unit\Protocol\Wire;

use PhpMqtt\Client\Exceptions\MalformedPacketException;
use PhpMqtt\Client\Protocol\Wire\BinaryReader;
use PhpMqtt\Client\Protocol\Wire\BinaryWriter;
use PHPUnit\Framework\TestCase;

class BinaryPrimitivesTest extends TestCase
{
    public function test_writer_and_reader_round_trip_all_wire_types(): void
    {
        $wire = (new BinaryWriter())
            ->writeByte(0xAB)
            ->writeUInt16(0x1234)
            ->writeUInt32(0x89ABCDEF)
            ->writeVariableByteInteger(268435455)
            ->writeBinaryData("\x00\xFF")
            ->writeUtf8String('mqtt')
            ->writeUtf8Pair('name', 'value')
            ->getBuffer();

        $reader = new BinaryReader($wire);

        $this->assertSame(0xAB, $reader->readByte());
        $this->assertSame(0x1234, $reader->readUInt16());
        $this->assertSame(0x89ABCDEF, $reader->readUInt32());
        $this->assertSame(268435455, $reader->readVariableByteInteger());
        $this->assertSame("\x00\xFF", $reader->readBinaryData());
        $this->assertSame('mqtt', $reader->readUtf8String());
        $this->assertSame(['name', 'value'], $reader->readUtf8Pair());
        $this->assertFalse($reader->hasRemaining());
    }

    /**
     * @dataProvider malformedVariableByteIntegers
     */
    public function test_reader_rejects_malformed_variable_byte_integers(string $wire): void
    {
        $this->expectException(MalformedPacketException::class);

        (new BinaryReader($wire))->readVariableByteInteger();
    }

    public function malformedVariableByteIntegers(): array
    {
        return [
            'truncated' => ["\x80"],
            'non-canonical' => ["\x80\x00"],
            'overlong' => ["\xFF\xFF\xFF\xFF\x01"],
        ];
    }

    public function test_reader_rejects_truncated_binary_data(): void
    {
        $this->expectException(MalformedPacketException::class);

        (new BinaryReader("\x00\x02a"))->readBinaryData();
    }

    public function test_writer_rejects_prohibited_utf8(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new BinaryWriter())->writeUtf8String("invalid\0value");
    }
}
