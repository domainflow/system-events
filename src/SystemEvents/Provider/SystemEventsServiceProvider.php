<?php

declare(strict_types=1);

namespace DomainFlow\SystemEvents\Provider;

use DomainFlow\Application;
use DomainFlow\Container\Exception\ContainerException;
use DomainFlow\Service\AbstractServiceProvider;
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
        $this->replayInMemoryEvents($app, $writer);

        // Log all events that are fired from now on.
        $app->on('*', static function (...$args) use ($writer) {
            $writer->processEvent(...$args);
        });
    }

    /**
     * Replays all events captured in $app->events prior to the provider being registered.
     *
     * @param Application $app
     * @param SystemEventProcessorInterface $writer
     * @return void
     */
    public function replayInMemoryEvents(
        Application $app,
        SystemEventProcessorInterface $writer
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
            $writer->processEvent($evt['eventName'], ...$evt['args']);
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
