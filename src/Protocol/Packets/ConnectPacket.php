<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Protocol\Packets;

use PhpMqtt\Client\Protocol\PacketType;
use PhpMqtt\Client\Protocol\Properties;
use PhpMqtt\Client\Protocol\Wire\Utf8Validator;

/**
 * MQTT 5 CONNECT packet.
 *
 * @package PhpMqtt\Client\Protocol\Packets
 */
final class ConnectPacket extends AbstractControlPacket
{
    public function __construct(
        private string $clientId,
        private bool $cleanStart,
        private int $keepAlive,
        Properties $properties,
        private ?Will $will = null,
        private ?string $username = null,
        private ?string $password = null
    )
    {
        parent::__construct(PacketType::CONNECT, $properties);

        if (strlen($clientId) > 0xFFFF || ($clientId !== '' && !Utf8Validator::isValid($clientId))) {
            throw new \InvalidArgumentException('Invalid MQTT client identifier.');
        }

        if ($keepAlive < 0 || $keepAlive > 0xFFFF) {
            throw new \InvalidArgumentException('The MQTT keep alive value is out of range.');
        }

        if ($username !== null && !Utf8Validator::isValid($username)) {
            throw new \InvalidArgumentException('The MQTT username is not valid UTF-8.');
        }

        if ($password !== null && strlen($password) > 0xFFFF) {
            throw new \InvalidArgumentException('The MQTT password exceeds 65535 bytes.');
        }
    }

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function usesCleanStart(): bool
    {
        return $this->cleanStart;
    }

    public function getKeepAlive(): int
    {
        return $this->keepAlive;
    }

    public function getWill(): ?Will
    {
        return $this->will;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
}
