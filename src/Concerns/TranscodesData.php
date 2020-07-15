<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Concerns;

/**
 * Provides common methods to encode data before sending it to a broker
 * and to decode data received from a broker.
 *
 * @package PhpMqtt\Client\Concerns
 */
trait TranscodesData
{
    /**
     * Creates a string which is prefixed with its own length as bytes.
     * This means a string like 'hello world' will become
     *
     *   \x00\x0bhello world
     *
     * where \x00\0x0b is the hex representation of 00000000 00001011 = 11
     *
     * @param string $data
     * @return string
     */
    protected function buildLengthPrefixedString(string $data): string
    {
        $length = strlen($data);
        $msb    = $length >> 8;
        $lsb    = $length % 256;

        return chr($msb) . chr($lsb) . $data;
    }

    /**
     * Converts the given string to a number, assuming it is an MSB encoded message id.
     * MSB means preceding characters have higher value.
     *
     * @param string $encodedMessageId
     * @return int
     */
    protected function decodeMessageId(string $encodedMessageId): int
    {
        $length = strlen($encodedMessageId);
        $result = 0;

        foreach (str_split($encodedMessageId) as $index => $char) {
            $result += ord($char) << (($length - 1) * 8 - ($index * 8));
        }

        return $result;
    }

    /**
     * Encodes the given message identifier as string.
     *
     * @param int $messageId
     * @return string
     */
    protected function encodeMessageId(int $messageId): string
    {
        return chr($messageId >> 8) . chr($messageId % 256);
    }

    /**
     * Encodes the length of a message as string, so it can be transmitted
     * over the wire.
     *
     * @param int $length
     * @return string
     */
    protected function encodeMessageLength(int $length): string
    {
        $result = '';

        do {
            $digit  = $length % 128;
            $length = $length >> 7;

            // if there are more digits to encode, set the top bit of this digit
            if ($length > 0) {
                $digit = ($digit | 0x80);
            }

            $result .= chr($digit);
        } while ($length > 0);

        return $result;
    }
}
