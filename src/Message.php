<?php

declare(strict_types=1);

namespace MqttClient\Protocol;

/**
 * Logical representation of a message
 *
 * This class implements features available as of MQTT 5.0, but that might not
 * be able to be represented in lower versions of the protocol.
 *
 */
class Message
{
    /**
     * Message topic
     *
     * @var string
     */
    protected $topic;

    /**
     * Message content
     *
     * @var string
     */
    protected $content;

    /**
     * Quality of Service (0-2)
     *
     * @var int
     */
    protected $qos = 0;

    /**
     * User properties (available since MQTT v5.0)
     *
     * @var array<string, string>
     */
    protected $userProperties = array();

    /**
     * ...
     *
     * ...
     */
    public function __construct(string $topic, string $content)
    {
      $this->topic = $topic;
      $this->content = $content;
    }

    /**
     * ...
     *
     * ...
     */
    public function getTopic(): string
    {
      return $this->topic;
    }

    /**
     * ...
     *
     * ...
     */
    public function getUserProperty(string $name): ?string
    {
      return ($this->userProperties[$name] ?? null);
    }

    /**
     * ...
     *
     * ...
     */
    public function setUserProperty(string $name, string $value): self
    {
      $this->userProperties[$name] = $value;
      return $this;
    }

    public function getQoS(): int
    {
        return $this->qos;
    }
}
