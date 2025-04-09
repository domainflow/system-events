<?php

declare(strict_types=1);

namespace DomainFlow\Tests\Unit\Listener;

use DomainFlow\SystemEvents\Interface\SystemEventProcessorInterface;
use DomainFlow\SystemEvents\Listener\SystemEventListener;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;

#[CoversClass(SystemEventListener::class)]
final class SystemEventListenerTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function test_onAnyEventCallsProcessEvent(): void
    {
        $expectedEventName = 'some.event';
        $expectedArgs = [1, 'two', ['key' => 'value']];

        $writerMock = $this->createMock(SystemEventProcessorInterface::class);
        $writerMock->expects($this->once())
            ->method('processEvent')
            ->with(
                $this->equalTo($expectedEventName),
                ...$expectedArgs
            );

        $listener = new SystemEventListener($writerMock);
        $listener->onAnyEvent($expectedEventName, ...$expectedArgs);
    }

    /**
     * @throws Exception
     */
    public function test_onAnyEventWithNoAdditionalArgs(): void
    {
        $expectedEventName = 'no.args.event';

        $writerMock = $this->createMock(SystemEventProcessorInterface::class);
        $writerMock->expects($this->once())
            ->method('processEvent')
            ->with($this->equalTo($expectedEventName));

        $listener = new SystemEventListener($writerMock);
        $listener->onAnyEvent($expectedEventName);
    }

    /**
     * @throws Exception
     */
    public function test_onAnyEventMultipleCalls(): void
    {
        $calls = [];
        $writerMock = $this->createMock(SystemEventProcessorInterface::class);
        $writerMock->expects($this->exactly(2))
            ->method('processEvent')
            ->willReturnCallback(function (...$args) use (&$calls) {
                $calls[] = $args;
            });

        $listener = new SystemEventListener($writerMock);
        $listener->onAnyEvent('event.one', 1, 2);
        $listener->onAnyEvent('event.two', 'a', 'b');

        $expectedCalls = [
            ['event.one', 1, 2],
            ['event.two', 'a', 'b'],
        ];
        $this->assertEquals($expectedCalls, $calls);
    }
}
