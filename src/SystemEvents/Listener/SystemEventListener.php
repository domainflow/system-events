<?php

declare(strict_types=1);

namespace DomainFlow\SystemEvents\Listener;

use DomainFlow\SystemEvents\Interface\SystemEventProcessorInterface;

/**
 * Class SystemEventListener
 *
 * Forwards events to a SystemEventProcessorInterface. SystemEventsServiceProvider::boot()
 * wires this up manually via $app->on('*', ...), not through Core's attribute-based
 * autoRegisterEventListeners(): that mechanism runs before service providers register,
 * while this class's writer dependency is only bound once register() has run — so it
 * cannot express this package's wiring. This class is a plain injectable service, not an
 * attribute-driven listener.
 */
class SystemEventListener
{
    public function __construct(
        protected SystemEventProcessorInterface $writer
    ) {
    }

    /**
     * Catch all system events and log them.
     *
     * @param string $eventName
     * @param mixed ...$args
     * @return void
     */
    public function onAnyEvent(
        string $eventName,
        mixed ...$args
    ): void {
        $this->writer->processEvent($eventName, ...$args);
    }
}
