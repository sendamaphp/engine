<?php

namespace Sendama\Engine\Events;

use Sendama\Engine\Core\Interfaces\SingletonInterface;
use Sendama\Engine\Events\Enumerations\EventType;
use Sendama\Engine\Events\Interfaces\EventInterface;
use Sendama\Engine\Events\Interfaces\EventListenerInterface;
use Sendama\Engine\Events\Interfaces\EventTargetInterface;

/**
 * The EventManager class is responsible for managing event listeners and dispatching events.
 */
class EventManager implements SingletonInterface, EventTargetInterface
{
    protected static ?EventManager $instance = null;

    /**
     * @var array<string, EventListenerInterface|callable> $listeners
     */
    protected array $listeners = [];

    /**
     * Constructs an event manager.
     */
    private function __construct()
    {
        // This is a singleton class.
    }

    /**
     * Returns the instance of the event manager.
     *
     * @return EventManager The instance of the event manager.
     */
    public static function getInstance(): self
    {
        if (!self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @inheritDoc
     */
    public function addEventListener(EventType $type, callable $listener, bool $useCapture = false): void
    {
        $this->listeners[$type->value][] = $listener;
    }

    /**
     * @inheritDoc
     */
    public function removeEventListener(EventType $type, callable $listener, bool $useCapture = false): void
    {
        if (isset($this->listeners[$type->value])) {
            foreach ($this->listeners[$type->value] as $index => $entry) {
                if ($entry instanceof EventListenerInterface) {
                    if ($listener->equals($entry)) {
                        unset($this->listeners[$type->value][$index]);
                    }
                } else {
                    if ($listener === $entry) {
                        unset($this->listeners[$type->value][$index]);
                    }
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function dispatchEvent(EventInterface $event): bool
    {
        if (isset($this->listeners[$event->getType()->value])) {
            foreach ($this->listeners[$event->getType()->value] as $listener) {
                if ($listener instanceof EventListenerInterface) {
                    $listener->handle($event);
                } // TODO: Check if this is correct, should be callable.
                else {
                    $listener($event);
                }
            }
        }

        return true;
    }
}