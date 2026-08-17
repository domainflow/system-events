<?php

declare(strict_types=1);

namespace DomainFlow\SystemEvents\Interface;

/**
 * Interface SystemEventFilterInterface
 *
 * A contract for deciding which event names actually reach a SystemEventProcessorInterface.
 * SystemEventsServiceProvider applies it identically to both replayInMemoryEvents() and the
 * live wildcard listener.
 */
interface SystemEventFilterInterface
{
    /**
     * Decide whether an event should be forwarded to the configured processor.
     *
     * @param string $eventName
     * @return bool
     */
    public function shouldProcess(string $eventName): bool;
}
