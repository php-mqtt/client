<?php

declare(strict_types=1);

namespace PhpMqtt\Protocol;

/**
 * Protocol orchestrator
 */
abstract class Protocol
{
    /**
     * QoS levels - At most once
     */
    const QOS_AT_MOST_ONCE  = 0;

    /**
     * QoS levels - At least once
     */
    const QOS_AT_LEAST_ONCE = 1;

    /**
     * QoS levels - Exactly once
     */
    const QOS_EXACTLY_ONCE  = 2;

    /**
     * Protocol version-specific feature sniffing
     *
     * For more higher-level features the actual version implementation
     * should override this and return whether they support the given feature.
     *
     * Simpler features can be discovered with `property_exists($packet, 'fieldname')`.
     *
     * @param string $feature Feature name
     * @return bool TRUE if this protocol version supports the named feature, FALSE otherwise
     */
    public function supports(string $feature): bool
    {
        // FIXME: version-specific implementation should override this
        return false;
    }

    /**
     * Packet instance factory
     *
     * @param string $type Packet type name
     * @return Packet Packet instance
     */
    abstract public function packet(string $type): Packet;
}
