<?php

declare(strict_types=1);

namespace PhpMqtt\Client\MessageProcessors;

use PhpMqtt\Client\Exceptions\InvalidMessageException;
use PhpMqtt\Client\Exceptions\ProtocolViolationException;
use PhpMqtt\Client\Message;
use PhpMqtt\Client\MessageType;

/**
 * This message processor implements the MQTT protocol version 3.1.1.
 *
 * @package PhpMqtt\Client\MessageProcessors
 */
class Mqtt311MessageProcessor extends Mqtt31MessageProcessor
{
    /**
     * {@inheritDoc}
     */
    protected function getEncodedProtocolNameAndVersion(): string
    {
        return $this->buildLengthPrefixedString('MQTT') . chr(0x04); // protocol version (4)
    }

    /**
     * {@inheritDoc}
     */
    public function parseAndValidateMessage(string $message): ?Message
    {
        $result = parent::parseAndValidateMessage($message);

        if ($this->isPublishMessageWithNullCharacter($result)) {
            throw new ProtocolViolationException('The broker sent us a message with the forbidden unicode character U+0000.');
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    protected function parseAndValidateSubscribeAcknowledgementMessage(string $data): Message
    {
        if (strlen($data) < 3) {
            $this->logger->notice('Received invalid subscribe acknowledgement from the broker.');
            throw new InvalidMessageException('Received invalid subscribe acknowledgement from the broker.');
        }

        $messageId = $this->decodeMessageId($this->pop($data, 2));

        // Parse and validate the QoS acknowledgements.
        $acknowledgements = array_map('ord', str_split($data));
        foreach ($acknowledgements as $acknowledgement) {
            if (!in_array($acknowledgement, [0, 1, 2, 128])) {
                throw new InvalidMessageException('Received subscribe acknowledgement with invalid QoS values from the broker.');
            }
        }

        return (new Message(MessageType::SUBSCRIBE_ACKNOWLEDGEMENT()))
            ->setMessageId($messageId)
            ->setAcknowledgedQualityOfServices($acknowledgements);
    }

    /**
     * Determines if the given message is a PUBLISH message and contains the unicode null character U+0000.
     *
     * @param Message $message
     * @return bool
     */
    private function isPublishMessageWithNullCharacter(Message $message): bool
    {
        return $message !== null
            && $message->getType()->equals(MessageType::PUBLISH())
            && $message->getContent() !== null
            && preg_match('/\x{0000}/u', $message->getContent());
    }
}
