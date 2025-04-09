<?php

declare(strict_types=1);

namespace DomainFlow\SystemEvents\Listener;

use DomainFlow\Application\Attributes\EventListener;
use DomainFlow\SystemEvents\Interface\SystemEventProcessorInterface;

/**
 * Class SystemEventListener
 *
 * Uses an attribute-based listener that can catch either specific events or all events (via wildcard).
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
    #[EventListener('*')]
    public function onAnyEvent(
        string $eventName,
        mixed ...$args
    ): void {
        $this->writer->processEvent($eventName, ...$args);
    }
}
