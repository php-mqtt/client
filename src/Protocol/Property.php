<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol;

/**
 * A single MQTT 5 property.
 *
 * @package PhpMqtt\Client\Protocol
 */
final class Property
{
    /** @var mixed */
    private $value;

    /**
     * @param mixed $value
     */
    public function __construct(private int $identifier, $value)
    {
        $this->value = $value;
    }

    public function getIdentifier(): int
    {
        return $this->identifier;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
