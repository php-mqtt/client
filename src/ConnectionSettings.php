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
    /** @var string|null */
    private $username = null;

    /** @var string|null */
    private $password = null;

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

    /** @var int */
    private $lastWillQualityOfService = 0;

    /** @var bool */
    private $lastWillRetain = false;

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
     * @param string|null $username
     * @return ConnectionSettings
     */
    public function setUsername(?string $username): ConnectionSettings
    {
        $copy = clone $this;

        $copy->username = $username;

        return $copy;
    }

    /**
     * @return string|null
     */
    public function getUsername(): ?string
    {
        return $this->username;
    }

    /**
     * @param string|null $password
     * @return ConnectionSettings
     */
    public function setPassword(?string $password): ConnectionSettings
    {
        $copy = clone $this;

        $copy->password = $password;

        return $copy;
    }

    /**
     * @return string|null
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    /**
     * @param bool $blockSocket
     * @return ConnectionSettings
     */
    public function setBlockSocket(bool $blockSocket): ConnectionSettings
    {
        $copy = clone $this;

        $copy->blockSocket = $blockSocket;

        return $copy;
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
        $copy = clone $this;

        $copy->connectTimeout = $connectTimeout;

        return $copy;
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
        $copy = clone $this;

        $copy->socketTimeout = $socketTimeout;

        return $copy;
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
        $copy = clone $this;

        $copy->keepAliveInterval = $keepAliveInterval;

        return $copy;
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
        $copy = clone $this;

        $copy->resendTimeout = $resendTimeout;

        return $copy;
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
        $copy = clone $this;

        $copy->lastWillTopic = $lastWillTopic;

        return $copy;
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
        $copy = clone $this;

        $copy->lastWillMessage = $lastWillMessage;

        return $copy;
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
     * @param int $lastWillQualityOfService
     * @return ConnectionSettings
     */
    public function setLastWillQualityOfService(int $lastWillQualityOfService): ConnectionSettings
    {
        $copy = clone $this;

        $copy->lastWillQualityOfService = $lastWillQualityOfService;

        return $copy;
    }

    /**
     * @return int
     */
    public function getLastWillQualityOfService(): int
    {
        return $this->lastWillQualityOfService;
    }

    /**
     * @param bool $lastWillRetain
     * @return ConnectionSettings
     */
    public function setRetainLastWill(bool $lastWillRetain): ConnectionSettings
    {
        $copy = clone $this;

        $copy->lastWillRetain = $lastWillRetain;

        return $copy;
    }

    /**
     * @return bool
     */
    public function shouldRetainLastWill(): bool
    {
        return $this->lastWillRetain;
    }

    /**
     * @param bool $useTls
     * @return ConnectionSettings
     */
    public function setUseTls(bool $useTls): ConnectionSettings
    {
        $copy = clone $this;

        $copy->useTls = $useTls;

        return $copy;
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
        $copy = clone $this;

        $copy->tlsVerifyPeer = $tlsVerifyPeer;

        return $copy;
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
        $copy = clone $this;

        $copy->tlsVerifyPeerName = $tlsVerifyPeerName;

        return $copy;
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
        $copy = clone $this;

        $copy->tlsSelfSignedAllowed = $tlsSelfSignedAllowed;

        return $copy;
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
        $copy = clone $this;

        $copy->tlsCertificateAuthorityFile = $tlsCertificateAuthorityFile;

        return $copy;
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
        $copy = clone $this;

        $copy->tlsCertificateAuthorityPath = $tlsCertificateAuthorityPath;

        return $copy;
    }

    /**
     * @return string|null
     */
    public function getTlsCertificateAuthorityPath(): ?string
    {
        return $this->tlsCertificateAuthorityPath;
    }
}
