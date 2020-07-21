<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

/**
 * The settings used during connection to a broker.
 *
 * @package PhpMqtt\Client
 */
class ConnectionSettings
{
    /** @var int */
    private $qualityOfService = 0;

    /** @var bool */
    private $retain = false;

    /** @var bool */
    private $blockSocket = false;

    /** @var int */
    private $connectTimeout = 60;

    /** @var int */
    private $socketTimeout = 5;

    /** @var int */
    private $keepAliveInterval = 10;

    /** @var int */
    private $resendTimeout = 10;

    /** @var string|null */
    private $lastWillTopic = null;

    /** @var string|null */
    private $lastWillMessage = null;

    /** @var bool */
    private $useTls = false;

    /** @var bool */
    private $tlsVerifyPeer = true;

    /** @var bool */
    private $tlsVerifyPeerName = true;

    /** @var bool */
    private $tlsSelfSignedAllowed = false;

    /** @var string|null */
    private $tlsCertificateAuthorityFile = null;

    /** @var string|null */
    private $tlsCertificateAuthorityPath = null;

    /**
     * @param int $qualityOfService
     * @return ConnectionSettings
     */
    public function setQualityOfService(int $qualityOfService): ConnectionSettings
    {
        $this->qualityOfService = $qualityOfService;

        return $this;
    }

    /**
     * @return int
     */
    public function getQualityOfService(): int
    {
        return $this->qualityOfService;
    }

    /**
     * @param bool $retain
     * @return ConnectionSettings
     */
    public function setRetain(bool $retain): ConnectionSettings
    {
        $this->retain = $retain;

        return $this;
    }

    /**
     * @return bool
     */
    public function shouldRetain(): bool
    {
        return $this->retain;
    }

    /**
     * @param bool $blockSocket
     * @return ConnectionSettings
     */
    public function setBlockSocket(bool $blockSocket): ConnectionSettings
    {
        $this->blockSocket = $blockSocket;

        return $this;
    }

    /**
     * @return bool
     */
    public function shouldBlockSocket(): bool
    {
        return $this->blockSocket;
    }

    /**
     * @param int $connectTimeout
     * @return ConnectionSettings
     */
    public function setConnectTimeout(int $connectTimeout): ConnectionSettings
    {
        $this->connectTimeout = $connectTimeout;

        return $this;
    }

    /**
     * @return int
     */
    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    /**
     * @param int $socketTimeout
     * @return ConnectionSettings
     */
    public function setSocketTimeout(int $socketTimeout): ConnectionSettings
    {
        $this->socketTimeout = $socketTimeout;

        return $this;
    }

    /**
     * @return int
     */
    public function getSocketTimeout(): int
    {
        return $this->socketTimeout;
    }

    /**
     * @param int $keepAliveInterval
     * @return ConnectionSettings
     */
    public function setKeepAliveInterval(int $keepAliveInterval): ConnectionSettings
    {
        $this->keepAliveInterval = $keepAliveInterval;

        return $this;
    }

    /**
     * @return int
     */
    public function getKeepAliveInterval(): int
    {
        return $this->keepAliveInterval;
    }

    /**
     * @param int $resendTimeout
     * @return ConnectionSettings
     */
    public function setResendTimeout(int $resendTimeout): ConnectionSettings
    {
        $this->resendTimeout = $resendTimeout;

        return $this;
    }

    /**
     * @return int
     */
    public function getResendTimeout(): int
    {
        return $this->resendTimeout;
    }

    /**
     * @param string|null $lastWillTopic
     * @return ConnectionSettings
     */
    public function setLastWillTopic(?string $lastWillTopic): ConnectionSettings
    {
        $this->lastWillTopic = $lastWillTopic;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLastWillTopic(): ?string
    {
        return $this->lastWillTopic;
    }

    /**
     * @param string|null $lastWillMessage
     * @return ConnectionSettings
     */
    public function setLastWillMessage(?string $lastWillMessage): ConnectionSettings
    {
        $this->lastWillMessage = $lastWillMessage;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getLastWillMessage(): ?string
    {
        return $this->lastWillMessage;
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

    /**
     * @param bool $useTls
     * @return ConnectionSettings
     */
    public function setUseTls(bool $useTls): ConnectionSettings
    {
        $this->useTls = $useTls;

        return $this;
    }

    /**
     * @return bool
     */
    public function shouldUseTls(): bool
    {
        return $this->useTls;
    }

    /**
     * @param bool $tlsVerifyPeer
     * @return ConnectionSettings
     */
    public function setTlsVerifyPeer(bool $tlsVerifyPeer): ConnectionSettings
    {
        $this->tlsVerifyPeer = $tlsVerifyPeer;

        return $this;
    }

    /**
     * @return bool
     */
    public function shouldTlsVerifyPeer(): bool
    {
        return $this->tlsVerifyPeer;
    }

    /**
     * @param bool $tlsVerifyPeerName
     * @return ConnectionSettings
     */
    public function setTlsVerifyPeerName(bool $tlsVerifyPeerName): ConnectionSettings
    {
        $this->tlsVerifyPeerName = $tlsVerifyPeerName;

        return $this;
    }

    /**
     * @return bool
     */
    public function shouldTlsVerifyPeerName(): bool
    {
        return $this->tlsVerifyPeerName;
    }

    /**
     * @param bool $tlsSelfSignedAllowed
     * @return ConnectionSettings
     */
    public function setTlsSelfSignedAllowed(bool $tlsSelfSignedAllowed): ConnectionSettings
    {
        $this->tlsSelfSignedAllowed = $tlsSelfSignedAllowed;

        return $this;
    }

    /**
     * @return bool
     */
    public function isTlsSelfSignedAllowed(): bool
    {
        return $this->tlsSelfSignedAllowed;
    }

    /**
     * @param string|null $tlsCertificateAuthorityFile
     * @return ConnectionSettings
     */
    public function setTlsCertificateAuthorityFile(?string $tlsCertificateAuthorityFile): ConnectionSettings
    {
        $this->tlsCertificateAuthorityFile = $tlsCertificateAuthorityFile;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTlsCertificateAuthorityFile(): ?string
    {
        return $this->tlsCertificateAuthorityFile;
    }

    /**
     * @param string|null $tlsCertificateAuthorityPath
     * @return ConnectionSettings
     */
    public function setTlsCertificateAuthorityPath(?string $tlsCertificateAuthorityPath): ConnectionSettings
    {
        $this->tlsCertificateAuthorityPath = $tlsCertificateAuthorityPath;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getTlsCertificateAuthorityPath(): ?string
    {
        return $this->tlsCertificateAuthorityPath;
    }
}
