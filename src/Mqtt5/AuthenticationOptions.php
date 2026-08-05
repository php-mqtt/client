<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\Wire\Utf8Validator;

/**
 * Immutable MQTT 5 enhanced-authentication options.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class AuthenticationOptions
{
    private Properties $properties;

    public function __construct(
        private string $method,
        private ?string $data = null,
        ?Properties $properties = null
    )
    {
        if ($method === '' || !Utf8Validator::isValid($method)) {
            throw new \InvalidArgumentException('Authentication Method must be non-empty valid MQTT UTF-8.');
        }

        $this->properties = $properties ?? Properties::empty();
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function getProperties(): Properties
    {
        return $this->properties;
    }
}
