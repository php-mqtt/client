<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Concerns;

use PhpMqtt\Client\Contracts\MQTTClient;

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
        $this->loopEventHandlers    = new \SplObjectStorage();
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
     * Multiple event handlers can be registered at the same time.
     *
     * @param \Closure $callback
     * @return MQTTClient
     */
    public function registerLoopEventHandler(\Closure $callback): MQTTClient
    {
        $this->loopEventHandlers->attach($callback);

        /** @var MQTTClient $this */
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
     * @return MQTTClient
     */
    public function unregisterLoopEventHandler(\Closure $callback = null): MQTTClient
    {
        if ($callback === null) {
            $this->loopEventHandlers->removeAll($this->loopEventHandlers);
        } else {
            $this->loopEventHandlers->detach($callback);
        }

        /** @var MQTTClient $this */
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
     * Multiple event handlers can be registered at the same time.
     *
     * @param \Closure $callback
     * @return MQTTClient
     */
    public function registerPublishEventHandler(\Closure $callback): MQTTClient
    {
        $this->publishEventHandlers->attach($callback);

        /** @var MQTTClient $this */
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
     * @return MQTTClient
     */
    public function unregisterPublishEventHandler(\Closure $callback = null): MQTTClient
    {
        if ($callback === null) {
            $this->publishEventHandlers->removeAll($this->publishEventHandlers);
        } else {
            $this->publishEventHandlers->detach($callback);
        }

        /** @var MQTTClient $this */
        return $this;
    }
}
