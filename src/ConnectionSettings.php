<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

/**
 * Connection settings for MQTTClient
 */
class ConnectionSettings
{
    /** @var int */
    public $qualityOfService = 0;

    /** @var bool */
    public $retain = false;

    /** @var bool */
    public $blockSocket = false;

    /** @var int */
    public $connectTimeout = 60;

    /** @var int */
    public $socketTimeout = 5;

    /** @var int */
    public $keepAliveInterval = 10;

    /** @var int */
    public $resendTimeout = 10;

    /** @var string|null */
    public $lastWillTopic = null;

    /** @var string|null */
    public $lastWillMessage = null;

    /** @var bool */
    public $useTls = false;

    /** @var bool */
    public $tlsVerifyPeer = true;

    /** @var bool */
    public $tlsVerifyPeerName = true;

    /** @var bool */
    public $tlsSelfSignedAllowed = false;

    /** @var string|null */
    public $tlsCertificateAuthorityFile = null;

    /** @var string|null */
    public $tlsCertificateAuthorityPath = null;
}
