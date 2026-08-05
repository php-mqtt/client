<?php

declare(strict_types=1);

namespace Tests\Unit\Protocol\Wire;

use PhpMqtt\Client\Exceptions\MalformedPacketException;
use PhpMqtt\Client\Protocol\Wire\PacketFramer;
use PHPUnit\Framework\TestCase;

class PacketFramerTest extends TestCase
{
    public function test_framer_extracts_one_packet_and_leaves_following_bytes_to_the_caller(): void
    {
        $packet        = null;
        $requiredBytes = -1;
        $result = (new PacketFramer())->tryExtract(hex2bin('300161c000'), $packet, $requiredBytes);

        $this->assertTrue($result);
        $this->assertSame(hex2bin('300161'), $packet);
        $this->assertSame(-1, $requiredBytes);
    }

    public function test_framer_reports_complete_packet_length_before_buffering_payload(): void
    {
        $packet        = null;
        $requiredBytes = -1;
        $result = (new PacketFramer())->tryExtract(hex2bin('308001'), $packet, $requiredBytes);

        $this->assertFalse($result);
        $this->assertSame(131, $requiredBytes);
    }

    public function test_framer_enforces_configured_limit_from_the_header(): void
    {
        $this->expectException(MalformedPacketException::class);

        (new PacketFramer(10))->tryExtract(hex2bin('3009'), $packet, $requiredBytes);
    }

    public function test_framer_rejects_non_canonical_remaining_length(): void
    {
        $this->expectException(MalformedPacketException::class);

        (new PacketFramer())->tryExtract(hex2bin('308000'), $packet, $requiredBytes);
    }
}
