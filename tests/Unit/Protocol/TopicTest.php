<?php

declare(strict_types=1);

namespace Tests\Unit\Protocol;

use PhpMqtt\Client\Protocol\Topic;
use PHPUnit\Framework\TestCase;

class TopicTest extends TestCase
{
    /**
     * @dataProvider matches
     */
    public function test_topic_matching(string $filter, string $topic, bool $expected): void
    {
        $this->assertSame($expected, Topic::matches($filter, $topic));
    }

    public function matches(): array
    {
        return [
            ['sport/+/player1', 'sport/tennis/player1', true],
            ['sport/#', 'sport', true],
            ['sport/#', 'sport/', true],
            ['#', '$SYS/status', false],
            ['$SYS/#', '$SYS/status', true],
            ['$share/group/sport/+', 'sport/tennis', true],
        ];
    }

    /**
     * @dataProvider invalidFilters
     */
    public function test_invalid_filters_are_rejected(string $filter): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Topic::assertValidFilter($filter);
    }

    public function invalidFilters(): array
    {
        return [
            [''],
            ['sport/#/ranking'],
            ['sport/ten+nis'],
            ['$share//sport/#'],
            ['$share/group'],
        ];
    }
}
