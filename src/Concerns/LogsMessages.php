<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Concerns;

use Psr\Log\LoggerInterface;

/**
 * Provides logger overloads which add additional context to the output and prepend
 * the log message with broker and client information.
 *
 * Note: This trait can only be used on classes which have the following properties.
 *
 * @property-read LoggerInterface $logger
 * @property-read string          $clientId
 * @property-read string          $host
 * @property-read int             $port
 * @package PhpMqtt\Client\Concerns
 */
trait LogsMessages
{
    /**
     * System is unusable.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    protected function logEmergency(string $message, array $context = []): void
    {
        $this->logger->emergency($this->wrapLogMessage($message), $this->mergeContext($context));
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
    protected function logAlert(string $message, array $context = []): void
    {
        $this->logger->alert($this->wrapLogMessage($message), $this->mergeContext($context));
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
    protected function logCritical(string $message, array $context = []): void
    {
        $this->logger->critical($this->wrapLogMessage($message), $this->mergeContext($context));
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        $this->logger->error($this->wrapLogMessage($message), $this->mergeContext($context));
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
    protected function logWarning(string $message, array $context = []): void
    {
        $this->logger->warning($this->wrapLogMessage($message), $this->mergeContext($context));
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    protected function logNotice(string $message, array $context = []): void
    {
        $this->logger->notice($this->wrapLogMessage($message), $this->mergeContext($context));
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
    protected function logInfo(string $message, array $context = []): void
    {
        $this->logger->info($this->wrapLogMessage($message), $this->mergeContext($context));
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array  $context
     * @return void
     */
    protected function logDebug(string $message, array $context = []): void
    {
        $this->logger->debug($this->wrapLogMessage($message), $this->mergeContext($context));
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
