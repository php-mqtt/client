<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol;

use PhpMqtt\Client\Protocol\Wire\Utf8Validator;

/**
 * MQTT topic and topic-filter validation and matching.
 *
 * @package PhpMqtt\Client\Protocol
 */
final class Topic
{
    private function __construct()
    {
    }

    public static function assertValidName(string $topicName, bool $allowEmptyForAlias = false): void
    {
        if ((!$allowEmptyForAlias && $topicName === '')
            || strlen($topicName) > 0xFFFF
            || !Utf8Validator::isValid($topicName)
            || str_contains($topicName, '+')
            || str_contains($topicName, '#')) {
            throw new \InvalidArgumentException('Invalid MQTT topic name.');
        }
    }

    public static function assertValidFilter(string $topicFilter): void
    {
        if ($topicFilter === '' || strlen($topicFilter) > 0xFFFF || !Utf8Validator::isValid($topicFilter)) {
            throw new \InvalidArgumentException('Invalid MQTT topic filter.');
        }

        [$filter, $isShared] = self::unwrapSharedFilter($topicFilter);

        if ($isShared && (str_starts_with($filter, '$share/') || $filter === '')) {
            throw new \InvalidArgumentException('Invalid shared subscription topic filter.');
        }

        $levels = explode('/', $filter);
        foreach ($levels as $index => $level) {
            if (str_contains($level, '#') && ($level !== '#' || $index !== count($levels) - 1)) {
                throw new \InvalidArgumentException('The multi-level wildcard must occupy the final topic level.');
            }

            if (str_contains($level, '+') && $level !== '+') {
                throw new \InvalidArgumentException('A single-level wildcard must occupy an entire topic level.');
            }
        }
    }

    public static function matches(string $topicFilter, string $topicName): bool
    {
        self::assertValidFilter($topicFilter);
        self::assertValidName($topicName);

        [$filter] = self::unwrapSharedFilter($topicFilter);

        if (str_starts_with($topicName, '$') && !str_starts_with($filter, '$')) {
            return false;
        }

        $filterLevels = explode('/', $filter);
        $topicLevels  = explode('/', $topicName);
        $topicIndex   = 0;

        foreach ($filterLevels as $filterLevel) {
            if ($filterLevel === '#') {
                return true;
            }

            if (!array_key_exists($topicIndex, $topicLevels)) {
                return false;
            }

            if ($filterLevel !== '+' && $filterLevel !== $topicLevels[$topicIndex]) {
                return false;
            }

            $topicIndex++;
        }

        return $topicIndex === count($topicLevels);
    }

    /**
     * @return array<int, string>
     */
    public static function matchedWildcards(string $topicFilter, string $topicName): array
    {
        if (!self::matches($topicFilter, $topicName)) {
            return [];
        }

        [$filter]     = self::unwrapSharedFilter($topicFilter);
        $filterLevels = explode('/', $filter);
        $topicLevels  = explode('/', $topicName);
        $matches      = [];

        foreach ($filterLevels as $index => $filterLevel) {
            if ($filterLevel === '+') {
                $matches[] = $topicLevels[$index];
            } elseif ($filterLevel === '#') {
                foreach (array_slice($topicLevels, $index) as $level) {
                    $matches[] = $level;
                }
            }
        }

        return $matches;
    }

    /**
     * @return array{string, bool}
     */
    private static function unwrapSharedFilter(string $topicFilter): array
    {
        if (!str_starts_with($topicFilter, '$share/')) {
            return [$topicFilter, false];
        }

        $separator = strpos($topicFilter, '/', 7);
        $group     = $separator === false ? '' : substr($topicFilter, 7, $separator - 7);

        if ($separator === false || $group === '' || str_contains($group, '+') || str_contains($group, '#')) {
            throw new \InvalidArgumentException('Invalid shared subscription group.');
        }

        return [substr($topicFilter, $separator + 1), true];
    }
}
