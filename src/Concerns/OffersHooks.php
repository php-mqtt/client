<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Concerns;

use PhpMqtt\Client\Contracts\MqttClient;

/**
 * Contains common methods and properties necessary to offer hooks.
 *
 * @package PhpMqtt\Client\Concerns
 */
trait OffersHooks
{
    /** @var \SplObjectStorage|\Closure[] */
    protected $loopEventHandlers;

    /** @var \SplObjectStorage|\Closure[] */
    protected $publishEventHandlers;

    /**
     * Needs to be called in order to initialize the trait.
     *
     * @return void
     */
    protected function initializeEventHandlers(): void
    {
        $this->loopEventHandlers = new \SplObjectStorage();
        $this->publishEventHandlers = new \SplObjectStorage();
    }

    /**
     * Registers a loop event handler which is called each iteration of the loop.
     * This event handler can be used for example to interrupt the loop under
     * certain conditions.
     *
     * The loop event handler is passed the MQTT client instance as first and
     * the elapsed time which the loop is already running for as second
     * parameter. The elapsed time is a float containing seconds.
     *
     * Example:
     * ```php
     * $mqtt->registerLoopEventHandler(function (
     *     MqttClient $mqtt,
     *     float $elapsedTime
     * ) use ($logger) {
     *     $logger->info("Running for [{$elapsedTime}] seconds already.");
     * });
     * ```
     *
     * Multiple event handlers can be registered at the same time.
     *
     * @param \Closure $callback
     * @return MqttClient
     */
    public function registerLoopEventHandler(\Closure $callback): MqttClient
    {
        $this->loopEventHandlers->attach($callback);

        /** @var MqttClient $this */
        return $this;
    }

    /**
     * Unregisters a loop event handler which prevents it from being called
     * in the future.
     *
     * This does not affect other registered event handlers. It is possible
     * to unregister all registered event handlers by passing null as callback.
     *
     * @param \Closure|null $callback
     * @return MqttClient
     */
    public function unregisterLoopEventHandler(\Closure $callback = null): MqttClient
    {
        if ($callback === null) {
            $this->loopEventHandlers->removeAll($this->loopEventHandlers);
        } else {
            $this->loopEventHandlers->detach($callback);
        }

        /** @var MqttClient $this */
        return $this;
    }

    /**
     * Registers a loop event handler which is called when a message is published.
     *
     * The loop event handler is passed the MQTT client as first, the topic as
     * second and the message as third parameter. As fourth parameter, the
     * message identifier will be passed. The QoS level as well as the retained
     * flag will also be passed as fifth and sixth parameters.
     *
     * Example:
     * ```php
     * $mqtt->registerPublishEventHandler(function (
     *     MqttClient $mqtt,
     *     string $topic,
     *     string $message,
     *     int $messageId,
     *     int $qualityOfService,
     *     bool $retain
     * ) use ($logger) {
     *     $logger->info("Received message on topic [{$topic}]: {$message}");
     * });
     * ```
     *
     * Multiple event handlers can be registered at the same time.
     *
     * @param \Closure $callback
     * @return MqttClient
     */
    public function registerPublishEventHandler(\Closure $callback): MqttClient
    {
        $this->publishEventHandlers->attach($callback);

        /** @var MqttClient $this */
        return $this;
    }

    /**
     * Unregisters a publish event handler which prevents it from being called
     * in the future.
     *
     * This does not affect other registered event handlers. It is possible
     * to unregister all registered event handlers by passing null as callback.
     *
     * @param \Closure|null $callback
     * @return MqttClient
     */
    public function unregisterPublishEventHandler(\Closure $callback = null): MqttClient
    {
        if ($callback === null) {
            $this->publishEventHandlers->removeAll($this->publishEventHandlers);
        } else {
            $this->publishEventHandlers->detach($callback);
        }

        /** @var MqttClient $this */
        return $this;
    }
}
