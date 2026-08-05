<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol;

/**
 * Immutable ordered MQTT 5 property collection.
 *
 * Duplicate User Properties and Subscription Identifiers are retained in insertion order.
 *
 * @package PhpMqtt\Client\Protocol
 */
final class Properties implements \Countable, \IteratorAggregate
{
    /** @var Property[] */
    private array $properties;

    /**
     * @param Property[] $properties
     */
    public function __construct(array $properties = [])
    {
        foreach ($properties as $property) {
            if (!$property instanceof Property) {
                throw new \InvalidArgumentException('MQTT properties must be Property instances.');
            }
        }

        $this->properties = array_values($properties);
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @param mixed $value
     */
    public function with(int $identifier, $value): self
    {
        $copy               = clone $this;
        $copy->properties[] = new Property($identifier, $value);

        return $copy;
    }

    public function withProperty(Property $property): self
    {
        $copy               = clone $this;
        $copy->properties[] = $property;

        return $copy;
    }

    public function has(int $identifier): bool
    {
        return $this->get($identifier) !== null;
    }

    /**
     * @return mixed|null
     */
    public function get(int $identifier)
    {
        foreach ($this->properties as $property) {
            if ($property->getIdentifier() === $identifier) {
                return $property->getValue();
            }
        }

        return null;
    }

    /**
     * @return array<int, mixed>
     */
    public function getAll(int $identifier): array
    {
        $result = [];

        foreach ($this->properties as $property) {
            if ($property->getIdentifier() === $identifier) {
                $result[] = $property->getValue();
            }
        }

        return $result;
    }

    /**
     * @return Property[]
     */
    public function toArray(): array
    {
        return $this->properties;
    }

    public function count(): int
    {
        return count($this->properties);
    }

    /**
     * @return \Traversable<int, Property>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->properties);
    }
}
