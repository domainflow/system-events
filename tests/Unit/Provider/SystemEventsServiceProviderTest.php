<?php

declare(strict_types=1);

namespace DomainFlow\Tests\Unit\Provider;

use Closure;
use DomainFlow\Application;
use DomainFlow\Container\Exception\ContainerException;
use DomainFlow\SystemEvents\Interface\SystemEventProcessorInterface;
use DomainFlow\SystemEvents\Listener\SystemEventListener;
use DomainFlow\SystemEvents\Processor\FileSystemEventProcessor;
use DomainFlow\SystemEvents\Provider\SystemEventsServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionClass;
use Throwable;

#[CoversClass(SystemEventsServiceProvider::class)]
#[CoversClass(SystemEventListener::class)]
#[CoversClass(FileSystemEventProcessor::class)]
final class SystemEventsServiceProviderTest extends TestCase
{
    /**
     * @throws Throwable|NotFoundExceptionInterface|ContainerException|ContainerExceptionInterface
     */
    public function testRegisterCreatesBindingsUsingMocks(): void
    {
        $dummyApp = new DummyApplication();

        $provider = new SystemEventsServiceProvider();
        $provider->register($dummyApp);

        $self = $this;
        $dummyApp->bindings[SystemEventProcessorInterface::class]['closure'] = function () use ($self) {
            return $self->createMock(SystemEventProcessorInterface::class);
        };
        $dummyApp->bindings[SystemEventListener::class]['closure'] = function () use ($self) {
            return $self->createMock(SystemEventListener::class);
        };

        $this->assertArrayHasKey(SystemEventProcessorInterface::class, $dummyApp->bindings);
        $this->assertTrue($dummyApp->bindings[SystemEventProcessorInterface::class]['shared']);
        $this->assertArrayHasKey(SystemEventListener::class, $dummyApp->bindings);
        $this->assertTrue($dummyApp->bindings[SystemEventListener::class]['shared']);

        $processor = $dummyApp->get(SystemEventProcessorInterface::class);
        $listener = $dummyApp->get(SystemEventListener::class);

        $this->assertInstanceOf(SystemEventProcessorInterface::class, $processor);
        $this->assertInstanceOf(SystemEventListener::class, $listener);
    }

    /**
     * @throws Throwable|NotFoundExceptionInterface|ContainerException|ContainerExceptionInterface
     */
    public function testRegisterRealBindings(): void
    {
        $dummyApp = new DummyApplication();

        $provider = new SystemEventsServiceProvider();
        $provider->register($dummyApp);

        $processor = $dummyApp->get(SystemEventProcessorInterface::class);
        $this->assertInstanceOf(FileSystemEventProcessor::class, $processor);

        $listener = $dummyApp->get(SystemEventListener::class);
        $this->assertInstanceOf(SystemEventListener::class, $listener);

        $ref = new ReflectionClass($listener);
        $prop = $ref->getProperty('writer');

        $listenerWriter = $prop->getValue($listener);
        $this->assertSame($processor, $listenerWriter);
    }

    public function testReplayInMemoryEvents(): void
    {
        $dummyEvents = [
            'e1' => [
                ['order' => 2, 'timestamp' => 1000, 'args' => ['arg2']],
                ['order' => 1, 'timestamp' => 999, 'args' => ['arg1']],
            ],
            'e2' => [
                ['order' => 3, 'timestamp' => 1001, 'args' => ['arg3']],
            ],
        ];

        $dummyApp = new DummyApplication();
        $dummyApp->events = $dummyEvents;

        $dummyWriter = new DummyWriter();

        $provider = new SystemEventsServiceProvider();
        $provider->replayInMemoryEvents($dummyApp, $dummyWriter);

        $expected = [
            ['e1', 'arg1'],
            ['e1', 'arg2'],
            ['e2', 'arg3'],
        ];
        $this->assertSame($expected, $dummyWriter->calls);
    }

    /**
     * @throws Throwable|NotFoundExceptionInterface|ContainerException|ContainerExceptionInterface
     */
    public function testBootRegistersWildcardListenerAndReplaysEvents(): void
    {
        $dummyEvents = [
            'pre.event' => [
                ['order' => 1, 'timestamp' => 100, 'args' => ['preArg']],
            ],
        ];

        $dummyApp = new DummyApplication();
        $dummyApp->events = $dummyEvents;

        $dummyWriter = new DummyWriter();
        $dummyApp->instances[SystemEventProcessorInterface::class] = $dummyWriter;

        $provider = new SystemEventsServiceProvider();
        $provider->boot($dummyApp);

        $expectedReplay = [['pre.event', 'preArg']];
        $this->assertSame($expectedReplay, $dummyWriter->calls);

        $dummyApp->trigger('*', 'new.event', 'newArg');

        $expectedAll = [
            ['pre.event', 'preArg'],
            ['new.event', 'newArg'],
        ];
        $this->assertSame($expectedAll, $dummyWriter->calls);
    }

    public function testIsDeferredReturnsFalse(): void
    {
        $provider = new SystemEventsServiceProvider();
        $this->assertFalse($provider->isDeferred());
    }
}

# dummy classes
class DummyApplication extends Application
{
    public array $bindings = [];
    public array $instances = [];
    public array $listeners = [];
    public array $events = [];

    public function bind(
        string $abstract,
        Closure|string|null $concrete = null,
        bool $shared = false
    ): void {
        $this->bindings[$abstract] = ['closure' => $concrete, 'shared' => $shared];
    }

    public function get(
        string $id
    ): mixed {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (isset($this->bindings[$id])) {
            $instance = call_user_func($this->bindings[$id]['closure']);
            if ($this->bindings[$id]['shared']) {
                $this->instances[$id] = $instance;
            }

            return $instance;
        }

        return null;
    }

    public function on(
        string $event,
        callable $listener
    ): void {
        $this->listeners[$event][] = $listener;
    }

    public function getEvents(): array
    {
        return $this->events;
    }

    public function trigger(
        string $event,
        ...$args
    ): void {
        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $listener) {
                $listener(...$args);
            }
        }
    }
}

class DummyWriter implements SystemEventProcessorInterface
{
    public array $calls = [];

    public function processEvent(
        string $eventName,
        mixed ...$args
    ): void {
        $this->calls[] = array_merge([$eventName], $args);
    }
}
