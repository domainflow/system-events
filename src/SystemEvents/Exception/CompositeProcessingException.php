<?php

declare(strict_types=1);

namespace DomainFlow\SystemEvents\Exception;

use RuntimeException;
use Throwable;

/**
 * Class CompositeProcessingException
 *
 * Thrown by CompositeSystemEventProcessor when one or more of its configured processors
 * fail to process an event. Every configured processor still receives the event
 * regardless of an earlier processor's failure; this exception aggregates every failure
 * that occurred while processing a single event.
 */
final class CompositeProcessingException extends RuntimeException
{
    /**
     * @var list<Throwable>
     */
    public readonly array $failures;

    /**
     * @param string $eventName
     * @param list<Throwable> $failures
     */
    public function __construct(
        public readonly string $eventName,
        array $failures
    ) {
        $this->failures = $failures;

        $messages = array_map(
            static fn (Throwable $e): string => $e->getMessage(),
            $failures
        );

        parent::__construct(
            sprintf(
                '%d processor(s) failed to process event "%s": %s',
                count($failures),
                $eventName,
                implode('; ', $messages)
            ),
            0,
            $failures[0] ?? null
        );
    }
}
