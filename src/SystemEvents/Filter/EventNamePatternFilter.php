<?php

declare(strict_types=1);

namespace DomainFlow\SystemEvents\Filter;

use DomainFlow\SystemEvents\Interface\SystemEventFilterInterface;

/**
 * Class EventNamePatternFilter
 *
 * Built-in SystemEventFilterInterface implementation matching event names against a list of
 * fnmatch()-style glob patterns (e.g. "payment.*", "auth.*"). An event is processed if it
 * matches at least one configured pattern. With no patterns configured, every event is
 * rejected.
 */
final class EventNamePatternFilter implements SystemEventFilterInterface
{
    /**
     * @var list<string>
     */
    private readonly array $patterns;

    public function __construct(
        string ...$patterns
    ) {
        $this->patterns = array_values($patterns);
    }

    public function shouldProcess(
        string $eventName
    ): bool {
        foreach ($this->patterns as $pattern) {
            if (fnmatch($pattern, $eventName)) {
                return true;
            }
        }

        return false;
    }
}
