<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Mqtt5;

use PhpMqtt\Client\Protocol\Properties;

/**
 * Immutable MQTT 5 PUBLISH options.
 *
 * @package PhpMqtt\Client\Mqtt5
 */
final class PublishOptions
{
    private Properties $properties;

    public function __construct(?Properties $properties = null)
    {
        $this->properties = $properties ?? Properties::empty();
    }

    public function getProperties(): Properties
    {
        return $this->properties;
    }
}
