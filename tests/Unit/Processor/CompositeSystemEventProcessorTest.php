<?php

declare(strict_types=1);

namespace DomainFlow\Tests\Unit\Processor;

use DomainFlow\SystemEvents\Exception\CompositeProcessingException;
use DomainFlow\SystemEvents\Interface\SystemEventProcessorInterface;
use DomainFlow\SystemEvents\Processor\CompositeSystemEventProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(CompositeSystemEventProcessor::class)]
#[CoversClass(CompositeProcessingException::class)]
final class CompositeSystemEventProcessorTest extends TestCase
{
    public function testEveryConfiguredProcessorReceivesEveryEventInConstructionOrder(): void
    {
        $first = new RecordingProcessor();
        $second = new RecordingProcessor();

        $composite = new CompositeSystemEventProcessor($first, $second);
        $composite->processEvent('order.placed', 'arg1', 42);

        $this->assertSame([['order.placed', 'arg1', 42]], $first->calls);
        $this->assertSame([['order.placed', 'arg1', 42]], $second->calls);
    }

    public function testConstructionOrderIsPreservedAcrossMultipleEvents(): void
    {
        $order = [];
        $first = new RecordingProcessor($order, 'first');
        $second = new RecordingProcessor($order, 'second');

        $composite = new CompositeSystemEventProcessor($first, $second);
        $composite->processEvent('evt', 'x');

        $this->assertSame(['first', 'second'], $order);
    }

    public function testFailingProcessorDoesNotPreventLaterProcessorsFromReceivingTheEvent(): void
    {
        $failing = new ThrowingProcessor();
        $second = new RecordingProcessor();

        $composite = new CompositeSystemEventProcessor($failing, $second);

        try {
            $composite->processEvent('order.placed', 'arg1');
        } catch (CompositeProcessingException) {
            // Expected: still isolated below.
        }

        $this->assertSame([['order.placed', 'arg1']], $second->calls);
    }

    public function testAggregatesFailuresIntoACompositeProcessingException(): void
    {
        $failingOne = new ThrowingProcessor('boom1');
        $failingTwo = new ThrowingProcessor('boom2');
        $ok = new RecordingProcessor();

        $composite = new CompositeSystemEventProcessor($failingOne, $ok, $failingTwo);

        try {
            $composite->processEvent('order.placed', 'arg1');
            $this->fail('Expected CompositeProcessingException to be thrown.');
        } catch (CompositeProcessingException $e) {
            $this->assertSame('order.placed', $e->eventName);
            $this->assertCount(2, $e->failures);
            $this->assertSame('boom1', $e->failures[0]->getMessage());
            $this->assertSame('boom2', $e->failures[1]->getMessage());
            $this->assertSame($e->failures[0], $e->getPrevious());
        }

        $this->assertSame([['order.placed', 'arg1']], $ok->calls);
    }

    public function testNoExceptionIsThrownWhenNoProcessorFails(): void
    {
        $composite = new CompositeSystemEventProcessor(new RecordingProcessor(), new RecordingProcessor());

        $composite->processEvent('evt', 'x');

        $this->addToAssertionCount(1);
    }
}

# Dummy classes
class RecordingProcessor implements SystemEventProcessorInterface
{
    public array $calls = [];

    public function __construct(
        private array &$sharedOrder = [],
        private readonly string $label = ''
    ) {
    }

    public function processEvent(
        string $eventName,
        mixed ...$args
    ): void {
        $this->calls[] = array_merge([$eventName], $args);
        if ($this->label !== '') {
            $this->sharedOrder[] = $this->label;
        }
    }
}

class ThrowingProcessor implements SystemEventProcessorInterface
{
    public function __construct(
        private readonly string $message = 'boom'
    ) {
    }

    public function processEvent(
        string $eventName,
        mixed ...$args
    ): void {
        throw new RuntimeException($this->message);
    }
}
