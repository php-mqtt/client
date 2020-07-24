<?php

declare(strict_types=1);

namespace PhpMqtt\Client\MessageProcessors;

use PhpMqtt\Client\Contracts\MessageProcessor;
use PhpMqtt\Client\Contracts\MqttClient;
use PhpMqtt\Client\Exceptions\MqttClientException;

/**
 * This message processor implements the MQTT protocol version 3.
 *
 * @package PhpMqtt\Client\MessageProcessors
 */
class Mqtt3MessageProcessor implements MessageProcessor
{
    /**
     * {@inheritDoc}
     */
    public function parseAndHandleMessages(string $buffer, int $bufferLength, MqttClient $client): int
    {
        $consumedBytes  = 0;

        do
        {
            $result = $this->tryParseAndHandleMessage($buffer, $bufferLength, $client);

            // The buffer was empty or not enough information has been in the buffer.
            if ($result === 0) {
                // In case we processed multiple messages, we need to return the already
                // consumed amount of bytes, which can be zero or negative.
                return $consumedBytes;
            }

            // The buffer contains only part of a new message.
            if ($result > 0) {
                // If we already processed a message, we need to return the amount of
                // consumed bytes.
                if ($consumedBytes !== 0) {
                    return $consumedBytes;
                }

                // Otherwise, we can return the required amount of bytes.
                return $result;
            }

            // A message has been found in the buffer and was processed. Therefore we can
            // reduce the buffer by the given amount of bytes.
            $bufferLength -= $result;
            if ($bufferLength > 0) {
                $buffer = substr($buffer, $result);
            }
        } while ($bufferLength > 0);

        // When the buffer is empty, we might have processed a message. So we need to return
        // the amount of consumed bytes.
        return $consumedBytes;
    }

    /**
     * @param string     $buffer
     * @param int        $bufferLength
     * @param MqttClient $client
     * @return int
     * @throws MqttClientException
     */
    protected function tryParseAndHandleMessage(string $buffer, int $bufferLength, MqttClient $client): int
    {
        // If we received no input, we can return immediately without doing work.
        if ($bufferLength === 0) {
            return 0;
        }

        // If we received not at least the fixed header with one length indicating byte,
        // we know that there can't be a valid message in the buffer. So we return early.
        if ($bufferLength < 2) {
            return 0;
        }

        // Read the first byte of a message (command and flags).
        $byte             = $buffer[0];
        $command          = (int)(ord($byte) / 16);
        $qualityOfService = (ord($byte) & 0x06) >> 1;

        // Read the second byte of a message (remaining length).
        // If the continuation bit (8) is set on the length byte, another byte will be read as length.
        $byteIndex       = 1;
        $remainingLength = 0;
        $multiplier      = 1;
        do {
            // If the buffer has no more data, but we need to read more for the length header,
            // we cannot give useful information about the remaining length and exit early.
            if ($byteIndex + 1 > $bufferLength) {
                return 0;
            }

            // Otherwise, we can take seven bits to calculate the length and the remaining eighth bit
            // as continuation bit.
            $digit            = ord($buffer[$byteIndex]);
            $remainingLength += ($digit & 127) * $multiplier;
            $multiplier      *= 128;
            $byteIndex++;
        } while (($digit & 128) !== 0);

        // At this point, we can now tell whether the remaining length amount of bytes are available
        // or not. If not, we return the amount of bytes required for the message to be complete.
        $requiredBufferLength = $byteIndex + 1 + $remainingLength;
        if ($requiredBufferLength > $bufferLength) {
            return $requiredBufferLength;
        }

        // Now that we know we have a full message in the buffer, we can forward the processing.
        $this->handleMessage($command, $qualityOfService, substr($buffer, $byteIndex, $remainingLength), $client);

        // We then inform the calling method about the amount of used bytes.
        return $requiredBufferLength * (-1);
    }

    /**
     * Handle a complete incoming message.
     *
     * @param int        $command
     * @param int        $qualityOfService
     * @param string     $data
     * @param MqttClient $client
     * @throws MqttClientException
     */
    protected function handleMessage(int $command, int $qualityOfService, string $data, MqttClient $client): void
    {

    }
}
