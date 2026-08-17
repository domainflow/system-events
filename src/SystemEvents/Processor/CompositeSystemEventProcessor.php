<?php

declare(strict_types=1);

namespace DomainFlow\SystemEvents\Processor;

use DomainFlow\SystemEvents\Exception\CompositeProcessingException;
use DomainFlow\SystemEvents\Interface\SystemEventProcessorInterface;
use Throwable;

/**
 * Class CompositeSystemEventProcessor
 *
 * Fans a single event out to N configured SystemEventProcessorInterface destinations,
 * so an application can log to more than one destination at once (e.g. file + a future
 * Redis/DB processor) without SystemEventsServiceProvider knowing about fan-out.
 *
 * Every configured processor always receives the event, in construction order, even if
 * an earlier processor fails: one destination's outage must not silently prevent another,
 * healthy destination from receiving the event. If any processor fails, every failure is
 * aggregated into a single CompositeProcessingException thrown after all processors have
 * run — the caller (typically SystemEventsServiceProvider, see its own processor-failure
 * isolation) decides how to handle it.
 */
final class CompositeSystemEventProcessor implements SystemEventProcessorInterface
{
    /**
     * @var list<SystemEventProcessorInterface>
     */
    private readonly array $processors;

    public function __construct(
        SystemEventProcessorInterface ...$processors
    ) {
        $this->processors = array_values($processors);
    }

    /**
     * @param string $eventName
     * @param mixed ...$args
     * @throws CompositeProcessingException
     * @return void
     */
    public function processEvent(
        string $eventName,
        mixed ...$args
    ): void {
        $failures = [];
        foreach ($this->processors as $processor) {
            try {
                $processor->processEvent($eventName, ...$args);
            } catch (Throwable $e) {
                $failures[] = $e;
            }
        }

        if ($failures !== []) {
            throw new CompositeProcessingException($eventName, $failures);
        }
    }
}
