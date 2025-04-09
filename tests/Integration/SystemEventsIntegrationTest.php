<?php

declare(strict_types=1);

namespace DomainFlow\Tests\Integration;

use DomainFlow\Application;
use DomainFlow\Application\Exception\EventManagerException;
use DomainFlow\Application\Exception\TerminationException;
use DomainFlow\Service\AbstractServiceProvider;
use DomainFlow\SystemEvents\Provider\SystemEventsServiceProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;

#[CoversNothing()]
final class SystemEventsIntegrationTest extends TestCase
{
    private string $tempLogFile;

    protected function setUp(): void
    {
        $this->tempLogFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'events_test_' . uniqid() . '.log';
        putenv('LOG_FILE_PATH=' . $this->tempLogFile);

        putenv('CUSTOM_LOG_PLACEHOLDERS={"{{appVersion}}":"1.2.3","{{env}}":"testing"}');
        putenv("CUSTOM_LOG_TEMPLATE=[{{timestamp}}] System Event: {{eventName}} | App Version: {{appVersion}}; | Environment: {{env}}  \n");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempLogFile)) {
            unlink($this->tempLogFile);
        }

        putenv('LOG_FILE_PATH');
        putenv('CUSTOM_LOG_PLACEHOLDERS');
        putenv('CUSTOM_LOG_TEMPLATE');
    }

    /**
     * @throws TerminationException|Throwable| NotFoundExceptionInterface|EventManagerException|ContainerExceptionInterface
     */
    public function test_applicationBootAndConsoleServiceWorks(): void
    {
        $app = new Application();

        $app->registerProvider(new SystemEventsServiceProvider());
        $app->registerProvider(new ConsoleServiceProvider());

        $app->boot();

        $service = $app->get(ConsoleService::class);
        $this->assertSame('Hello from ConsoleService!', $service->sayHello());

        $app->terminate();

        $events = $app->getEvents();
        $this->assertNotEmpty($events, 'No events were stored during boot.');

        if (file_exists($this->tempLogFile)) {
            $logContents = file_get_contents($this->tempLogFile);
            $this->assertStringContainsString('System Event:', $logContents, 'Log file does not contain expected output.');
        }
    }

    /**
     * @throws TerminationException|Throwable| NotFoundExceptionInterface|EventManagerException|ContainerExceptionInterface
     */
    public function test_applicationBootWorksWithDefaults(): void
    {
        chdir(sys_get_temp_dir());
        putenv('LOG_FILE_PATH');
        putenv('CUSTOM_LOG_TEMPLATE');
        putenv('CUSTOM_LOG_PLACEHOLDERS');

        $app = new Application();
        $app->setBasePath(sys_get_temp_dir());
        $app->registerProvider(new SystemEventsServiceProvider());
        $app->registerProvider(new ConsoleServiceProvider());
        $app->boot();

        $service = $app->get(ConsoleService::class);
        $this->assertSame('Hello from ConsoleService!', $service->sayHello());

        $app->terminate();

        $defaultLogFile = sys_get_temp_dir() . '/logs/' . date('Y-m-d') . '-system-events.log';
        $this->assertFileExists($defaultLogFile);
        unlink($defaultLogFile);
    }

    /**
     * @throws TerminationException|Throwable| NotFoundExceptionInterface|EventManagerException|ContainerExceptionInterface
     */
    public function test_applicationBootWorksWithCustomTemplateOnly(): void
    {
        chdir(sys_get_temp_dir());
        putenv('LOG_FILE_PATH');
        putenv('CUSTOM_LOG_PLACEHOLDERS');
        putenv('CUSTOM_LOG_TEMPLATE=[CustomTemplate] Event: {{eventName}}');

        $app = new Application();
        $app->setBasePath(sys_get_temp_dir());
        $app->registerProvider(new SystemEventsServiceProvider());
        $app->registerProvider(new ConsoleServiceProvider());
        $app->boot();
        $app->terminate();

        $defaultLogFile = sys_get_temp_dir() . '/logs/' . date('Y-m-d') . '-system-events.log';
        $this->assertFileExists($defaultLogFile);
        $contents = file_get_contents($defaultLogFile);
        $this->assertStringContainsString('[CustomTemplate]', $contents);
        unlink($defaultLogFile);
    }

    /**
     * @throws TerminationException|Throwable| NotFoundExceptionInterface|EventManagerException|ContainerExceptionInterface
     */
    public function test_applicationBootWorksWithCustomPlaceholdersOnly(): void
    {
        chdir(sys_get_temp_dir());
        putenv('LOG_FILE_PATH');

        putenv('CUSTOM_LOG_TEMPLATE=Env: {{env}}, Version: {{appVersion}}');
        putenv('CUSTOM_LOG_PLACEHOLDERS={"{{env}}":"customEnv","{{appVersion}}":"9.9.9"}');

        $app = new Application();
        $app->setBasePath(sys_get_temp_dir());
        $app->registerProvider(new SystemEventsServiceProvider());
        $app->registerProvider(new ConsoleServiceProvider());
        $app->boot();
        $app->terminate();

        $defaultLogFile = sys_get_temp_dir() . '/logs/' . date('Y-m-d') . '-system-events.log';
        $this->assertFileExists($defaultLogFile);
        $contents = file_get_contents($defaultLogFile);
        $this->assertStringContainsString('customEnv', $contents);
        $this->assertStringContainsString('9.9.9', $contents);
        unlink($defaultLogFile);
    }

    /**
     * @throws TerminationException|Throwable|EventManagerException
     */
    public function test_customEventIsLoggedAndStored(): void
    {
        chdir(sys_get_temp_dir());
        putenv('LOG_FILE_PATH=' . $this->tempLogFile);
        putenv('CUSTOM_LOG_PLACEHOLDERS={"{{appVersion}}":"1.0.0","{{env}}":"integration"}');
        putenv("CUSTOM_LOG_TEMPLATE=[{{timestamp}}] {{eventName}}: {{args}}\n");

        $app = new Application();
        $app->registerProvider(new SystemEventsServiceProvider());
        $app->boot();

        $app->fireEvent('custom.event', 'value1', 42);

        $app->terminate();

        $events = $app->getEvents();
        $this->assertArrayHasKey('custom.event', $events);
        $this->assertNotEmpty($events['custom.event']);

        $logContents = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('custom.event', $logContents);
        $this->assertStringContainsString('value1', $logContents);
        $this->assertStringContainsString('42', $logContents);
    }

    /**
     * @throws TerminationException|Throwable|EventManagerException
     */
    public function test_customEventsAreLoggedAndStored(): void
    {
        chdir(sys_get_temp_dir());
        putenv('LOG_FILE_PATH=' . $this->tempLogFile);
        putenv('CUSTOM_LOG_PLACEHOLDERS={"{{appVersion}}":"1.0.0","{{env}}":"integration"}');
        putenv("CUSTOM_LOG_TEMPLATE=[{{timestamp}}] {{eventName}}: {{args}}\n");

        $app = new Application();
        $app->registerProvider(new SystemEventsServiceProvider());
        $app->boot();

        $app->fireEvent('custom.event1', 'value1', 34);
        $app->fireEvent('custom.event2', 'value2', 11);
        $app->fireEvent('custom.event3', 'value3', 53);

        $app->terminate();

        $events = $app->getEvents();
        $this->assertArrayHasKey('custom.event1', $events);
        $this->assertNotEmpty($events['custom.event1']);

        $this->assertArrayHasKey('custom.event2', $events);
        $this->assertNotEmpty($events['custom.event2']);

        $this->assertArrayHasKey('custom.event3', $events);
        $this->assertNotEmpty($events['custom.event3']);

        $logContents = file_get_contents($this->tempLogFile);
        $this->assertStringContainsString('custom.event1', $logContents);
        $this->assertStringContainsString('value1', $logContents);
        $this->assertStringContainsString('34', $logContents);

        $this->assertStringContainsString('custom.event2', $logContents);
        $this->assertStringContainsString('value2', $logContents);
        $this->assertStringContainsString('11', $logContents);

        $this->assertStringContainsString('custom.event3', $logContents);
        $this->assertStringContainsString('value3', $logContents);
        $this->assertStringContainsString('53', $logContents);
    }

}

# dummy classes
class ConsoleService
{
    public function sayHello(): string
    {
        return 'Hello from ConsoleService!';
    }
}

class ConsoleServiceProvider extends AbstractServiceProvider
{
    protected array $providedServices = [ConsoleService::class];
    public bool $defer = false;

    public function register(
        Application $app
    ): void {
        $app->bind(ConsoleService::class, function () {
            return new ConsoleService();
        }, true);
    }

    /**
     * @throws EventManagerException
     */
    public function boot(
        Application $app
    ): void {
        $app->fireEvent("console.service.booted", "ConsoleService booted.");
    }

    public function isDeferred(): bool
    {
        return $this->defer;
    }
}
