<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Wire;

/**
 * Validates the UTF-8 data representation defined by MQTT.
 *
 * @package PhpMqtt\Client\Protocol\Wire
 */
final class Utf8Validator
{
    private function __construct()
    {
    }

    public static function isValid(string $value): bool
    {
        if (preg_match('//u', $value) !== 1) {
            return false;
        }

        foreach (self::codePoints($value) as $codePoint) {
            if ($codePoint === 0
                || ($codePoint >= 0xD800 && $codePoint <= 0xDFFF)
                || ($codePoint >= 0xFDD0 && $codePoint <= 0xFDEF)
                || ($codePoint & 0xFFFF) === 0xFFFE
                || ($codePoint & 0xFFFF) === 0xFFFF) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return \Generator<int>
     */
    private static function codePoints(string $value): \Generator
    {
        $length = strlen($value);

        for ($index = 0; $index < $length; $index++) {
            $first = ord($value[$index]);

            if ($first < 0x80) {
                yield $first;
                continue;
            }

            if (($first & 0xE0) === 0xC0) {
                yield (($first & 0x1F) << 6) | (ord($value[++$index]) & 0x3F);
                continue;
            }

            if (($first & 0xF0) === 0xE0) {
                yield (($first & 0x0F) << 12)
                    | ((ord($value[++$index]) & 0x3F) << 6)
                    | (ord($value[++$index]) & 0x3F);
                continue;
            }

            yield (($first & 0x07) << 18)
                | ((ord($value[++$index]) & 0x3F) << 12)
                | ((ord($value[++$index]) & 0x3F) << 6)
                | (ord($value[++$index]) & 0x3F);
        }
    }
}
