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
    private $qualityOfService;

    /** @var bool */
    private $retain;

    /** @var bool */
    private $blockSocket;

    /** @var int */
    private $socketTimeout;

    /** @var int */
    private $keepAlive;

    /** @var int */
    private $resendTimeout;

    /** @var string */
    private $lastWillTopic;

    /** @var string */
    private $lastWillMessage;

    /** @var bool */
    private $useTls;

    /** @var bool */
    private $tlsVerifyPeer;

    /** @var bool */
    private $tlsVerifyName;

    /** @var string|null */
    private $tlsClientCertificateFile;

    /** @var string|null */
    private $tlsClientCertificateKeyFile;

    /** @var string|null */
    private $tlsClientCertificatePassphrase;

    /**
     * Constructs a new settings object.
     *
     * @param int         $qualityOfService
     * @param bool        $retain
     * @param bool        $blockSocket
     * @param int         $socketTimeout
     * @param int         $keepAlive
     * @param int         $resendTimeout
     * @param string|null $lastWillTopic
     * @param string|null $lastWillMessage
     * @param bool        $useTls
     * @param bool        $tlsVerifyPeer
     * @param bool        $tlsVerifyName
     * @param string|null $tlsClientCertificateFile
     * @param string|null $tlsClientCertificateKeyFile
     * @param string|null $tlsClientCertificatePassphrase
     */
    public function __construct(
        int $qualityOfService = 0,
        bool $retain = false,
        bool $blockSocket = false,
        int $socketTimeout = 5,
        int $keepAlive = 10,
        int $resendTimeout = 10,
        string $lastWillTopic = null,
        string $lastWillMessage = null,
        bool $useTls = false,
        bool $tlsVerifyPeer = true,
        bool $tlsVerifyName = true,
        string $tlsClientCertificateFile = null,
        string $tlsClientCertificateKeyFile = null,
        string $tlsClientCertificatePassphrase = null
    )
    {
        $this->qualityOfService               = $qualityOfService;
        $this->retain                         = $retain;
        $this->blockSocket                    = $blockSocket;
        $this->socketTimeout                  = $socketTimeout;
        $this->keepAlive                      = $keepAlive;
        $this->resendTimeout                  = $resendTimeout;
        $this->lastWillTopic                  = $lastWillTopic;
        $this->lastWillMessage                = $lastWillMessage;
        $this->useTls                         = $useTls;
        $this->tlsVerifyPeer                  = $tlsVerifyPeer;
        $this->tlsVerifyName                  = $tlsVerifyName;
        $this->tlsClientCertificateFile       = $tlsClientCertificateFile;
        $this->tlsClientCertificateKeyFile    = $tlsClientCertificateKeyFile;
        $this->tlsClientCertificatePassphrase = $tlsClientCertificatePassphrase;
    }

    /**
     * Returns the desired quality of service level of the client.
     *
     * @return int
     */
    public function getQualityOfServiceLevel(): int
    {
        return $this->qualityOfService;
    }

    /**
     * Determines whether the client is supposed to block the socket.
     *
     * @return bool
     */
    public function wantsToBlockSocket(): bool
    {
        return $this->blockSocket;
    }

    /**
     * Returns the socket timeout of the client in seconds.
     *
     * @return int
     */
    public function getSocketTimeout(): int
    {
        return $this->socketTimeout;
    }

    /**
     * Returns the keep alive interval used by the client in seconds.
     *
     * @return int
     */
    public function getKeepAlive(): int
    {
        return $this->keepAlive;
    }

    /**
     * Returns the resend timeout used by the client in seconds.
     *
     * @return int
     */
    public function getResendTimeout(): int
    {
        return $this->resendTimeout;
    }

    /**
     * Returns the last will topic of the client. When the client loses connection
     * to the broker, this topic will be used to publish the last will message.
     *
     * @return string|null
     */
    public function getLastWillTopic(): ?string
    {
        return $this->lastWillTopic;
    }

    /**
     * Returns the last will message of the client. When the client loses connection
     * to the broker, this message will be published.
     *
     * @return string|null
     */
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

    /**
     * Determines whether the client wants to use TLS.
     *
     * @return bool
     */
    public function shouldUseTls(): bool
    {
        return $this->useTls;
    }

    /**
     * Determines whether the TLS peer certificate validation must occur.
     *
     * @return bool
     */
    public function shouldTlsVerifyPeer(): bool
    {
        return $this->tlsVerifyPeer;
    }

    /**
     * Determines whether we should check for name match of the certificate.
     *
     * @return bool
     */
    public function shouldTlsVerifyPeerName(): bool
    {
        return $this->tlsVerifyName;
    }
    
    /**
     * Returns the full path to the configured client certificate file,
     * or null if none is configured.
     *
     * The client certificate can be of any format supported by the `local_cert`
     * option described by https://www.php.net/manual/en/context.ssl.php.
     *
     * @return string|null
     */
    public function getTlsClientCertificateFile(): ?string
    {
        return $this->tlsClientCertificateFile;
    }

    /**
     * Returns the full path to the configured client certificate key file,
     * or null if none is configured.
     *
     * The client certificate key can be of any format supported by the `local_pk`
     * option described by https://www.php.net/manual/en/context.ssl.php.
     *
     * @return string|null
     */
    public function getTlsClientCertificateKeyFile(): ?string
    {
        return $this->tlsClientCertificateKeyFile;
    }

    /**
     * Returns the passphrase for the configured client certificate key,
     * or null if none is configured.
     *
     * @return string|null
     */
    public function getTlsClientCertificatePassphrase(): ?string
    {
        return $this->tlsClientCertificatePassphrase;
    }
}
