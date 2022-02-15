<?php

declare(strict_types=1);

namespace PhpMqtt\Client;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Wrapper for another logger. Drops logged messages if no logger is available.
 *
 * @internal This class is not part of the public API of the library and used internally only.
 * @package PhpMqtt\Client
 */
class Logger implements LoggerInterface
{
    private string $host;
    private int $port;
    private string $clientId;
    private ?LoggerInterface $logger;

    /**
     * Logger constructor.
     *
     * @param string               $host
     * @param int                  $port
     * @param string               $clientId
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        string $host,
        int $port,
        string $clientId,
        LoggerInterface $logger = null
    )
    {
        $this->host     = $host;
        $this->port     = $port;
        $this->clientId = $clientId;
        $this->logger   = $logger;
    }

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed  $level
     * @param string $message
     * @param array  $context
     * @return void
     */
    public function log($level, $message, array $context = []): void
    {
        if ($this->logger === null) {
            return;
        }

        $this->logger->log($level, $this->wrapLogMessage($message), $this->mergeContext($context));
    }

    /**
     * Wraps the given log message by prepending the client id and broker.
     *
     * @param string $message
     * @return string
     */
    protected function wrapLogMessage(string $message): string
    {
        return 'MQTT [{host}:{port}] [{clientId}] ' . $message;
    }

    /**
     * Adds global context like host, port and client id to the log context.
     *
     * @param array $context
     * @return array
     */
    protected function mergeContext(array $context): array
    {
        return array_merge([
            'host' => $this->host,
            'port' => $this->port,
            'clientId' => $this->clientId,
        ], $context);
    }
}
