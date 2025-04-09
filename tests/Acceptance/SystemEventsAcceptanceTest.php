<?php

declare(strict_types=1);

namespace DomainFlow\Tests\Acceptance;

use DomainFlow\Application;
use DomainFlow\Application\Exception\EventManagerException;
use DomainFlow\Application\Exception\TerminationException;
use DomainFlow\Service\AbstractServiceProvider;
use DomainFlow\SystemEvents\Provider\SystemEventsServiceProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversNothing()]
final class SystemEventsAcceptanceTest extends TestCase
{
    private string $tempLogFile;

    protected function setUp(): void
    {
        $this->tempLogFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'acceptance_events_' . uniqid() . '.log';
        putenv("LOG_FILE_PATH=" . $this->tempLogFile);
        putenv('CUSTOM_LOG_PLACEHOLDERS={"{{appVersion}}":"1.2.3","{{env}}":"acceptance"}');
        putenv("CUSTOM_LOG_TEMPLATE=[{{timestamp}}] System Event: {{eventName}}; Args: {{args}}\n");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempLogFile)) {
            unlink($this->tempLogFile);
        }
        putenv("LOG_FILE_PATH");
        putenv("CUSTOM_LOG_PLACEHOLDERS");
        putenv("CUSTOM_LOG_TEMPLATE");
    }

    /**
     * @throws TerminationException
     * @throws Throwable
     * @throws EventManagerException
     */
    public function test_fullApplicationLifecycleWithSystemEvents(): void
    {
        $app = new Application();

        $app->registerProvider(new SystemEventsServiceProvider());
        $app->registerProvider(new DummyCustomEventsProvider());

        $app->boot();

        $app->fireEvent("dummy.custom", "foo", 123);

        $app->terminate();

        $this->assertNotEmpty($app->getEvents());

        $this->assertFileExists($this->tempLogFile);
        $contents = file_get_contents($this->tempLogFile) ?: '';
        $this->assertStringContainsString("System Event:", $contents);
        $this->assertStringContainsString("dummy.boot", $contents);
        $this->assertStringContainsString("dummy.custom", $contents);
        $this->assertStringContainsString("dummy.termination", $contents);
    }
}

// dummy classes
class DummyCustomEventsProvider extends AbstractServiceProvider
{
    protected array $providedServices = ['dummy.service'];
    public bool $defer = false;

    public function register(
        Application $app
    ): void {
        $app->bind('dummy.service', function () {
            return new DummyService();
        }, true);
    }

    /**
     * @throws EventManagerException
     */
    public function boot(
        Application $app
    ): void {
        $app->fireEvent("dummy.boot", "boot data");

        $app->registerTerminationCallback(function (Application $app) {
            $app->fireEvent("dummy.termination", "termination data");
        });
    }

    public function isDeferred(): bool
    {
        return $this->defer;
    }
}

class DummyService
{
    public function doSomething(): string
    {
        return "done";
    }
}
