<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use PhpMqtt\Client\Mqtt5\FlowStage;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\PropertyIdentifier;

/**
 * A simple DTO for published messages which need to be stored in a repository
 * while waiting for the confirmation to be deliverable.
 *
 * @package PhpMqtt\Client
 */
class PublishedMessage extends PendingMessage
{
    private bool $received = false;
    private string $flowStage;
    private Properties $properties;
    private ?\DateTimeImmutable $expiresAt;

    /**
     * Creates a new published message object.
     */
    public function __construct(
        int $messageId,
        private string $topicName,
        private string $message,
        private int $qualityOfService,
        private bool $retain,
        ?Properties $properties = null,
        ?string $flowStage = null,
    )
    {
        parent::__construct($messageId);

        $this->properties = $properties ?? Properties::empty();
        $this->flowStage  = $flowStage ?? ($qualityOfService === 2
            ? FlowStage::AWAITING_PUBREC
            : FlowStage::AWAITING_PUBACK);

        $messageExpiryInterval = $this->properties->get(PropertyIdentifier::MESSAGE_EXPIRY_INTERVAL);
        $this->expiresAt = $messageExpiryInterval === null
            ? null
            : (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $messageExpiryInterval));
    }

    /**
     * Returns the topic name of the published message.
     */
    public function getTopicName(): string
    {
        return $this->topicName;
    }

    /**
     * Returns the content of the published message.
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Returns the requested quality of service level.
     */
    public function getQualityOfServiceLevel(): int
    {
        return $this->qualityOfService;
    }

    /**
     * Determines whether this message wants to be retained.
     */
    public function wantsToBeRetained(): bool
    {
        return $this->retain;
    }

    /**
     * Determines whether the message has been confirmed as received.
     */
    public function hasBeenReceived(): bool
    {
        return $this->received;
    }

    /**
     * Marks the published message as received (QoS level 2).
     *
     * Returns `true` if the message was not previously received. Otherwise `false` will be returned.
     */
    public function markAsReceived(): bool
    {
        $result = !$this->received;

        $this->received = true;
        $this->flowStage = FlowStage::AWAITING_PUBCOMP;

        return $result;
    }

    public function getFlowStage(): string
    {
        return $this->flowStage;
    }

    public function setFlowStage(string $flowStage): void
    {
        $this->flowStage = $flowStage;
        $this->received  = $flowStage === FlowStage::AWAITING_PUBCOMP;
    }

    public function getProperties(): Properties
    {
        return $this->properties;
    }

    public function hasExpired(?\DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= ($now ?? new \DateTimeImmutable());
    }

    public function getRemainingExpiryInterval(?\DateTimeImmutable $now = null): ?int
    {
        if ($this->expiresAt === null) {
            return null;
        }

        return max(0, $this->expiresAt->getTimestamp() - ($now ?? new \DateTimeImmutable())->getTimestamp());
    }
}
