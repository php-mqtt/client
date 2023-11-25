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
    private ?int $messageId  = null;
    private ?string $topic   = null;
    private ?string $content = null;

    /** @var int[] */
    private array $acknowledgedQualityOfServices = [];

    /**
     * Message constructor.
     */
    public function __construct(
        private MessageType $type,
        private int $qualityOfService = 0,
        private bool $retained = false,
    )
    {
    }

    public function getType(): MessageType
    {
        return $this->type;
    }

    public function getQualityOfService(): int
    {
        return $this->qualityOfService;
    }

    public function getRetained(): bool
    {
        return $this->retained;
    }

    public function getMessageId(): ?int
    {
        return $this->messageId;
    }

    public function setMessageId(?int $messageId): Message
    {
        $this->messageId = $messageId;

        return $this;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function setTopic(?string $topic): Message
    {
        $this->topic = $topic;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

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
     */
    public function setAcknowledgedQualityOfServices(array $acknowledgedQualityOfServices): Message
    {
        $this->acknowledgedQualityOfServices = $acknowledgedQualityOfServices;

        return $this;
    }
}
