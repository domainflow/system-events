<?php

declare(strict_types=1);

namespace DomainFlow\SystemEvents\Provider;

use Closure;
use DomainFlow\Application;
use DomainFlow\Container\Exception\ContainerException;
use DomainFlow\Service\AbstractServiceProvider;
use DomainFlow\SystemEvents\Interface\SystemEventFilterInterface;
use DomainFlow\SystemEvents\Interface\SystemEventProcessorInterface;
use DomainFlow\SystemEvents\Listener\SystemEventListener;
use DomainFlow\SystemEvents\Processor\FileSystemEventProcessor;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;

/**
 * Class SystemEventsServiceProvider
 *
 * Registers the file-system writer and the listener so it can pick up and log all events.
 */
class SystemEventsServiceProvider extends AbstractServiceProvider
{
    /**
     * @var list<string>
     */
    protected array $providedServices = [
        SystemEventProcessorInterface::class,
        SystemEventListener::class,
    ];

    public bool $defer = false;

    /**
     * @param Closure(Throwable, string): void|null $onProcessingFailure Optional hook
     *        invoked whenever a processor fails to process an event, instead of letting
     *        the failure propagate into the code that fired the original event. Defaults
     *        to logging via PHP's error_log().
     * @param SystemEventFilterInterface|null $filter Optional filter deciding which event
     *        names reach the configured processor. Defaults to null, meaning every event
     *        is processed — identical to the pre-filter behaviour. Applied identically to
     *        replayInMemoryEvents() and the live wildcard listener.
     */
    public function __construct(
        private readonly ?Closure $onProcessingFailure = null,
        private readonly ?SystemEventFilterInterface $filter = null
    ) {
    }

    /**
     * Register the system events logging services.
     *
     * @param Application $app
     * @throws ContainerException
     * @return void
     */
    public function register(
        Application $app
    ): void {
        $app->bind(
            SystemEventProcessorInterface::class,
            function () {
                return new FileSystemEventProcessor();
            },
            true
        );

        $app->bind(
            SystemEventListener::class,
            function () use ($app) {
                /** @var SystemEventProcessorInterface $writer */
                $writer = $app->get(SystemEventProcessorInterface::class);

                return new SystemEventListener($writer);
            },
            true
        );
    }

    /**
     * Boot the service provider.
     *
     * @param Application $app
     * @throws NotFoundExceptionInterface|ContainerExceptionInterface|Throwable
     * @return void
     */
    public function boot(
        Application $app
    ): void {
        /** @var SystemEventProcessorInterface $writer */
        $writer = $app->get(SystemEventProcessorInterface::class);

        // Log all events that were fired before the provider was registered.
        $this->replayInMemoryEvents($app, $writer, $this->filter);

        // Log all events that are fired from now on.
        $filter = $this->filter;
        $app->on('*', function (string $eventName, mixed ...$args) use ($writer, $filter) {
            if ($filter !== null && !$filter->shouldProcess($eventName)) {
                return;
            }
            try {
                $writer->processEvent($eventName, ...$args);
            } catch (Throwable $e) {
                $this->reportProcessingFailure($e, $eventName);
            }
        });
    }

    /**
     * Report a processor failure without letting it propagate into the code that fired
     * the original, unrelated event.
     *
     * @param Throwable $e
     * @param string $eventName
     * @return void
     */
    private function reportProcessingFailure(
        Throwable $e,
        string $eventName
    ): void {
        if ($this->onProcessingFailure !== null) {
            ($this->onProcessingFailure)($e, $eventName);

            return;
        }

        error_log(sprintf(
            '[system-events] Failed to process event "%s": %s',
            $eventName,
            $e->getMessage()
        ));
    }

    /**
     * Replays all events captured in $app->events prior to the provider being registered.
     *
     * @param Application $app
     * @param SystemEventProcessorInterface $writer
     * @param SystemEventFilterInterface|null $filter
     * @return void
     */
    public function replayInMemoryEvents(
        Application $app,
        SystemEventProcessorInterface $writer,
        ?SystemEventFilterInterface $filter = null
    ): void {
        $all = [];
        foreach ($app->getEvents() as $eventName => $firings) {
            foreach ($firings as $firing) {
                $all[] = [
                    'eventName' => $eventName,
                    'order' => $firing['order'],
                    'timestamp' => $firing['timestamp'],
                    'args' => $firing['args'],
                ];
            }
        }

        usort($all, fn ($a, $b) => $a['order'] <=> $b['order']);

        foreach ($all as $evt) {
            if ($filter !== null && !$filter->shouldProcess($evt['eventName'])) {
                continue;
            }
            try {
                $writer->processEvent($evt['eventName'], ...$evt['args']);
            } catch (Throwable $e) {
                $this->reportProcessingFailure($e, $evt['eventName']);
            }
        }
    }

    /**
     * Indicate if this provider is deferred or not.
     *
     * @return bool
     */
    public function isDeferred(): bool
    {
        return $this->defer;
    }
}
