<?php

declare(strict_types=1);

namespace Namoshek\MQTT;

class MQTTConnectionSettings
{
    /** @var int */
    private $qualityOfService;

    /** @var bool */
    private $retain;

    /** @var bool */
    private $blockSocket;

    /** @var int */
    private $socketTimeout;

    /** @var int */
    private $keepAlive;

    /** @var string */
    private $lastWillTopic;

    /** @var string */
    private $lastWillMessage;

    /**
     * Constructs a new settings object.
     * 
     * @param int  $qualityOfService
     * @param bool $retain
     * @param bool $blockSocket
     * @param int  $socketTimeout
     * @param int  $keepAlive
     */
    public function __construct(
        int $qualityOfService = 0,
        bool $retain = false,
        bool $blockSocket = false,
        int $socketTimeout = 5,
        int $keepAlive = 10,
        string $lastWillTopic = null,
        string $lastWillMessage = null
    )
    {
        $this->qualityOfService = $qualityOfService;
        $this->retain           = $retain;
        $this->blockSocket      = $blockSocket;
        $this->socketTimeout    = $socketTimeout;
        $this->keepAlive        = $keepAlive;
        $this->lastWillTopic    = $lastWillTopic;
        $this->lastWillMessage  = $lastWillMessage;
    }

    public function getQualityOfServiceLevel(): int
    {
        return $this->qualityOfService;
    }

    public function wantsToBlockSocket(): bool
    {
        return $this->blockSocket;
    }

    public function getSocketTimeout(): int
    {
        return $this->socketTimeout;
    }

    public function getKeepAlive(): int
    {
        return $this->keepAlive;
    }

    public function getLastWillTopic(): ?string
    {
        return $this->lastWillTopic;
    }

    public function getLastWillMessage(): ?string
    {
        return $this->lastWillMessage;
    }

    /**
     * Determines whether quality of service is required.
     * 
     * @return bool
     */
    public function requiresQualityOfService(): bool
    {
        return $this->qualityOfService > 0;
    }

    /**
     * Determines whether message retention is required.
     * 
     * @return bool
     */
    public function requiresMessageRetention(): bool
    {
        return $this->retain;
    }

    /**
     * Determines whether the client has a last will.
     * 
     * @return bool
     */
    public function hasLastWill(): bool
    {
        return $this->lastWillTopic !== null && $this->lastWillMessage !== null;
    }
}
