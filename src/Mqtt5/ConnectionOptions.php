<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

use PhpMqtt\Client\Contracts\AuthenticationHandler;
use PhpMqtt\Client\Protocol\Properties;

/**
 * Immutable MQTT 5 CONNECT options.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class ConnectionOptions
{
    private Properties $properties;

    public function __construct(
        ?Properties $properties = null,
        private ?WillOptions $will = null,
        private ?AuthenticationOptions $authentication = null,
        private ?AuthenticationHandler $authenticationHandler = null,
        private bool $followServerReference = false
    )
    {
        $this->properties = $properties ?? Properties::empty();
    }

    public function getProperties(): Properties
    {
        return $this->properties;
    }

    public function getWill(): ?WillOptions
    {
        return $this->will;
    }

    public function getAuthentication(): ?AuthenticationOptions
    {
        return $this->authentication;
    }

    public function getAuthenticationHandler(): ?AuthenticationHandler
    {
        return $this->authenticationHandler;
    }

    public function shouldFollowServerReference(): bool
    {
        return $this->followServerReference;
    }
}
