<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Contracts;

use PhpMqtt\Client\Exceptions\MqttClientException;

/**
 * Implementations of this interface provide message parsing capabilities.
 * Services of this type are used by the {@see MqttClient} to implement multiple protocol versions.
 *
 * @package PhpMqtt\Client\Contracts
 */
interface MessageProcessor
{
    /**
     * Parses and handles messages found in the given buffer.
     *
     * A return value of zero indicates that the processor has not consumed anything of the
     * buffer, or that the buffer has been empty (i.e. length was zero). It is also possible
     * that the processor was not able to determine the remaining length of a message, in case
     * only one or few bytes were given.
     *
     * A positive return value indicates that the buffer contains the beginning of a message
     * but that the returned amount of bytes are missing for the message to be complete.
     * The buffer has not been consumed in this case. Ideally, the method is only invoked
     * when the remaining bytes have been received as well.
     *
     * A negative return value indicates that the returned amount of bytes of the buffer
     * have been processed and should be removed by the caller.
     *
     * @param string     $buffer
     * @param string     $bufferLength
     * @param MqttClient $client
     * @return int
     * @throws MqttClientException
     */
    public function parseAndHandleMessages(string $buffer, int $bufferLength, MqttClient $client): int;
}
