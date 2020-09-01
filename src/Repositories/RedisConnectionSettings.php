<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Repositories;

/**
 * Connection settings DTO for Redis used by {@see RedisRepository}.
 *
 * @package PhpMqtt\Client\Repositories
 */
class RedisConnectionSettings
{
    /** @var string|null */
    private $host = null;

    /** @var int */
    private $port = 6379;

    /** @var float */
    private $connectTimeout = 5.0;

    /** @var int */
    private $database = 0;

    /**
     * @return string|null
     */
    public function getHost(): ?string
    {
        return $this->host;
    }

    /**
     * @param string $host
     * @return RedisConnectionSettings
     */
    public function setHost(string $host): RedisConnectionSettings
    {
        $clone = clone $this;

        $clone->host = $host;

        return $clone;
    }

    /**
     * @return int
     */
    public function getPort(): int
    {
        return $this->port;
    }

    /**
     * @param int $port
     * @return RedisConnectionSettings
     */
    public function setPort(int $port): RedisConnectionSettings
    {
        $clone = clone $this;

        $clone->port = $port;

        return $clone;
    }

    /**
     * @return float
     */
    public function getConnectTimeout(): float
    {
        return $this->connectTimeout;
    }

    /**
     * @param float $connectTimeout
     * @return RedisConnectionSettings
     */
    public function setConnectTimeout(float $connectTimeout): RedisConnectionSettings
    {
        $clone = clone $this;

        $clone->connectTimeout = $connectTimeout;

        return $clone;
    }

    /**
     * @return int
     */
    public function getDatabase(): int
    {
        return $this->database;
    }

    /**
     * @param int $database
     * @return RedisConnectionSettings
     */
    public function setDatabase(int $database): RedisConnectionSettings
    {
        $clone = clone $this;

        $clone->database = $database;

        return $clone;
    }
}
