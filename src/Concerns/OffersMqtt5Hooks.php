<?php

declare(strict_types=1);

namespace PhpMqtt\Client\Concerns;

use PhpMqtt\Client\Contracts\Mqtt5Client;
use PhpMqtt\Client\Mqtt5\AuthenticationEvent;
use PhpMqtt\Client\Mqtt5\IncomingPublication;
use PhpMqtt\Client\Mqtt5\OperationResult;
use PhpMqtt\Client\Mqtt5\ServerDisconnect;

/**
 * Contains MQTT 5-specific typed event hooks without changing legacy callbacks.
 *
 * @mixin Mqtt5Client
 * @package PhpMqtt\Client\Concerns
 */
trait OffersMqtt5Hooks
{
    /** @var \SplObjectStorage|array<\Closure> */
    private $incomingPublicationEventHandlers;

    /** @var \SplObjectStorage|array<\Closure> */
    private $operationResultEventHandlers;

    /** @var \SplObjectStorage|array<\Closure> */
    private $serverDisconnectEventHandlers;

    /** @var \SplObjectStorage|array<\Closure> */
    private $authenticationEventHandlers;

    protected function initializeMqtt5EventHandlers(): void
    {
        $this->incomingPublicationEventHandlers = new \SplObjectStorage();
        $this->operationResultEventHandlers      = new \SplObjectStorage();
        $this->serverDisconnectEventHandlers    = new \SplObjectStorage();
        $this->authenticationEventHandlers      = new \SplObjectStorage();
    }

    public function registerIncomingPublicationEventHandler(\Closure $callback): Mqtt5Client
    {
        $this->incomingPublicationEventHandlers->offsetSet($callback);

        return $this;
    }

    public function unregisterIncomingPublicationEventHandler(?\Closure $callback = null): Mqtt5Client
    {
        $this->removeMqtt5Handler($this->incomingPublicationEventHandlers, $callback);

        return $this;
    }

    public function registerOperationResultEventHandler(\Closure $callback): Mqtt5Client
    {
        $this->operationResultEventHandlers->offsetSet($callback);

        return $this;
    }

    public function unregisterOperationResultEventHandler(?\Closure $callback = null): Mqtt5Client
    {
        $this->removeMqtt5Handler($this->operationResultEventHandlers, $callback);

        return $this;
    }

    public function registerServerDisconnectEventHandler(\Closure $callback): Mqtt5Client
    {
        $this->serverDisconnectEventHandlers->offsetSet($callback);

        return $this;
    }

    public function unregisterServerDisconnectEventHandler(?\Closure $callback = null): Mqtt5Client
    {
        $this->removeMqtt5Handler($this->serverDisconnectEventHandlers, $callback);

        return $this;
    }

    public function registerAuthenticationEventHandler(\Closure $callback): Mqtt5Client
    {
        $this->authenticationEventHandlers->offsetSet($callback);

        return $this;
    }

    public function unregisterAuthenticationEventHandler(?\Closure $callback = null): Mqtt5Client
    {
        $this->removeMqtt5Handler($this->authenticationEventHandlers, $callback);

        return $this;
    }

    private function runIncomingPublicationEventHandlers(IncomingPublication $publication): void
    {
        $this->runMqtt5Handlers($this->incomingPublicationEventHandlers, $publication, 'incoming publication');
    }

    private function runOperationResultEventHandlers(OperationResult $result): void
    {
        $this->runMqtt5Handlers($this->operationResultEventHandlers, $result, 'operation result');
    }

    private function runServerDisconnectEventHandlers(ServerDisconnect $disconnect): void
    {
        $this->runMqtt5Handlers($this->serverDisconnectEventHandlers, $disconnect, 'server disconnect');
    }

    private function runAuthenticationEventHandlers(AuthenticationEvent $event): void
    {
        $this->runMqtt5Handlers($this->authenticationEventHandlers, $event, 'authentication');
    }

    /**
     * @param \SplObjectStorage<\Closure, mixed> $handlers
     */
    private function removeMqtt5Handler(\SplObjectStorage $handlers, ?\Closure $callback): void
    {
        if ($callback === null) {
            $handlers->removeAll($handlers);
        } else {
            $handlers->offsetUnset($callback);
        }
    }

    /**
     * @param \SplObjectStorage<\Closure, mixed> $handlers
     * @param mixed                             $event
     */
    private function runMqtt5Handlers(\SplObjectStorage $handlers, $event, string $name): void
    {
        foreach ($handlers as $handler) {
            try {
                call_user_func($handler, $this, $event);
            } catch (\Throwable $exception) {
                $this->logger->error(sprintf('MQTT 5 %s hook callback threw an exception.', $name), [
                    'exception' => $exception,
                ]);
            }
        }
    }
}
