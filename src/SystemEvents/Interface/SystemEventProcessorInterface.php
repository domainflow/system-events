<?php

declare(strict_types=1);

namespace DomainFlow\SystemEvents\Interface;

/**
 * Interface SystemEventWriterInterface
 *
 * A contract for writing system events to any destination (file, external service, etc.).
 */
interface SystemEventProcessorInterface
{
    /**
     * Write an event, with optional arguments, to the underlying destination.
     *
     * @param string $eventName
     * @param mixed  ...$args
     * @return void
     */
    public function processEvent(string $eventName, mixed ...$args): void;
}
