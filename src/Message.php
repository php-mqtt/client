<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use PhpMqtt\Client\Contracts\MessageProcessor;
use PhpMqtt\Client\Contracts\MqttClient;

/**
 * Describes an action which is supposed to be performed after receiving a message.
 * Objects of this type are used by the {@see MessageProcessor} to instruct the
 * {@see MqttClient} about required steps to take.
 *
 * @package PhpMqtt\Client
 */
class Message
{
    private MessageType $type;
    private int $qualityOfService;
    private ?int $messageId  = null;
    private ?string $topic   = null;
    private ?string $content = null;

    /** @var int[] */
    private array $acknowledgedQualityOfServices = [];

    /**
     * Message constructor.
     *
     * @param MessageType $type
     * @param int         $qualityOfService
     */
    public function __construct(MessageType $type, int $qualityOfService = 0)
    {
        $this->type             = $type;
        $this->qualityOfService = $qualityOfService;
    }

    /**
     * @return MessageType
     */
    public function getType(): MessageType
    {
        return $this->type;
    }

    /**
     * @return int
     */
    public function getQualityOfService(): int
    {
        return $this->qualityOfService;
    }

    /**
     * @return int|null
     */
    public function getMessageId(): ?int
    {
        return $this->messageId;
    }

    /**
     * @param int|null $messageId
     * @return Message
     */
    public function setMessageId(?int $messageId): Message
    {
        $this->messageId = $messageId;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTopic(): ?string
    {
        return $this->topic;
    }

    /**
     * @param string|null $topic
     * @return Message
     */
    public function setTopic(?string $topic): Message
    {
        $this->topic = $topic;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * @param string|null $content
     * @return Message
     */
    public function setContent(?string $content): Message
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return int[]
     */
    public function getAcknowledgedQualityOfServices(): array
    {
        return $this->acknowledgedQualityOfServices;
    }

    /**
     * @param int[] $acknowledgedQualityOfServices
     * @return Message
     */
    public function setAcknowledgedQualityOfServices(array $acknowledgedQualityOfServices): Message
    {
        $this->acknowledgedQualityOfServices = $acknowledgedQualityOfServices;

        return $this;
    }
}
