<?php

declare(strict_types=1);

namespace DomainFlow\Tests\Unit\Filter;

use DomainFlow\SystemEvents\Filter\EventNamePatternFilter;
use DomainFlow\SystemEvents\Interface\SystemEventFilterInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EventNamePatternFilter::class)]
final class EventNamePatternFilterTest extends TestCase
{
    public function testImplementsSystemEventFilterInterface(): void
    {
        $filter = new EventNamePatternFilter('payment.*');
        $this->assertInstanceOf(SystemEventFilterInterface::class, $filter);
    }

    public function testMatchesEventAgainstASinglePattern(): void
    {
        $filter = new EventNamePatternFilter('payment.*');

        $this->assertTrue($filter->shouldProcess('payment.completed'));
        $this->assertFalse($filter->shouldProcess('auth.login'));
    }

    public function testMatchesEventAgainstAnyOfMultiplePatterns(): void
    {
        $filter = new EventNamePatternFilter('payment.*', 'auth.*');

        $this->assertTrue($filter->shouldProcess('payment.completed'));
        $this->assertTrue($filter->shouldProcess('auth.login'));
        $this->assertFalse($filter->shouldProcess('order.placed'));
    }

    public function testExactEventNameWithoutWildcardOnlyMatchesItself(): void
    {
        $filter = new EventNamePatternFilter('order.placed');

        $this->assertTrue($filter->shouldProcess('order.placed'));
        $this->assertFalse($filter->shouldProcess('order.placed.again'));
    }

    public function testNoPatternsConfiguredRejectsEveryEvent(): void
    {
        $filter = new EventNamePatternFilter();

        $this->assertFalse($filter->shouldProcess('anything'));
    }
}
