<?php

declare(strict_types=1);

namespace DomainFlow\Tests\Unit\Writer;

use DomainFlow\SystemEvents\Processor\FileSystemEventProcessor;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use RuntimeException;

/**
 * Test class for FileSystemEventProcessor.
 */
#[CoversClass(FileSystemEventProcessor::class)]
final class FileSystemEventProcessorTest extends TestCase
{
    private string $originalLogFilePath;
    private string $originalCustomTemplate;
    private string $originalCustomPlaceholders;

    protected function setUp(): void
    {
        date_default_timezone_set('UTC');

        $this->originalLogFilePath = getenv('LOG_FILE_PATH') ?: '';
        $this->originalCustomTemplate = getenv('CUSTOM_LOG_TEMPLATE') ?: '';
        $this->originalCustomPlaceholders = getenv('CUSTOM_LOG_PLACEHOLDERS') ?: '';

        putenv('LOG_FILE_PATH');
        putenv('CUSTOM_LOG_TEMPLATE');
        putenv('CUSTOM_LOG_PLACEHOLDERS');
    }

    protected function tearDown(): void
    {
        putenv('LOG_FILE_PATH=' . $this->originalLogFilePath);
        putenv('CUSTOM_LOG_TEMPLATE=' . $this->originalCustomTemplate);
        putenv('CUSTOM_LOG_PLACEHOLDERS=' . $this->originalCustomPlaceholders);
    }

    public function test_defaultInitialization(): void
    {
        $processor = new FileSystemEventProcessor();
        $ref = new ReflectionClass($processor);

        $propFilePath = $ref->getProperty('filePath');

        $expectedPath = getcwd() . '/logs/' . date('Y-m-d') . '-system-events.log';
        $this->assertSame($expectedPath, $propFilePath->getValue($processor));

        $propTemplate = $ref->getProperty('template');

        $defaultTemplate = "[{{timestamp}}] Event: {{eventName}}; Args: {{args}}\n";
        $this->assertSame($defaultTemplate, $propTemplate->getValue($processor));

        $propPlaceholders = $ref->getProperty('customPlaceholders');

        $this->assertSame([], $propPlaceholders->getValue($processor));
    }

    public function test_customTemplate(): void
    {
        $customTemplate = "Custom: {{eventName}}\n";
        putenv('CUSTOM_LOG_TEMPLATE=' . $customTemplate);

        $processor = new FileSystemEventProcessor();
        $ref = new ReflectionClass($processor);
        $propTemplate = $ref->getProperty('template');

        $this->assertSame($customTemplate, $propTemplate->getValue($processor));
    }

    public function test_customPlaceholders(): void
    {
        $customPlaceholdersJson = '{"{{key}}":"value"}';
        putenv('CUSTOM_LOG_PLACEHOLDERS=' . $customPlaceholdersJson);

        $processor = new FileSystemEventProcessor();
        $ref = new ReflectionClass($processor);
        $propPlaceholders = $ref->getProperty('customPlaceholders');

        $this->assertSame(['{{key}}' => 'value'], $propPlaceholders->getValue($processor));
    }

    public function test_setTemplateConvertsLiteralNewline(): void
    {
        $processor = new FileSystemEventProcessor();
        $processor->setTemplate('Line1\nLine2');
        $ref = new ReflectionClass($processor);
        $propTemplate = $ref->getProperty('template');

        $expected = "Line1\nLine2";
        $this->assertSame($expected, $propTemplate->getValue($processor));
    }

    /**
     * @throws ReflectionException
     */
    public function test_createDirectoryIfNotExistsCreatesDirectory(): void
    {
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_dir_' . uniqid();
        $this->assertFalse(is_dir($tempDir));

        $processor = new FileSystemEventProcessor();
        $ref = new ReflectionClass($processor);
        $method = $ref->getMethod('createDirectoryIfNotExists');

        $method->invoke($processor, $tempDir);

        $this->assertTrue(is_dir($tempDir));

        rmdir($tempDir);
    }

    public function test_processEventWritesFile(): void
    {
        $tempFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'dummy_' . uniqid() . '.log';

        $processor = new FileSystemEventProcessor();
        $ref = new ReflectionClass($processor);
        $propFilePath = $ref->getProperty('filePath');

        $propFilePath->setValue($processor, $tempFile);

        $processor->processEvent('test.event', 123, ['a' => 'b']);

        $contents = file_get_contents($tempFile) ?: '';

        unlink($tempFile);

        $this->assertStringContainsString('test.event', $contents);
        $this->assertStringContainsString('123', $contents);
        $this->assertStringContainsString('"a":"b"', $contents);
    }

    public function test_processEventThrowsOnWriteFailure(): void
    {
        $processor = new FileSystemEventProcessor();
        $ref = new ReflectionClass($processor);
        $propFilePath = $ref->getProperty('filePath');

        $directoryPath = sys_get_temp_dir();
        $propFilePath->setValue($processor, $directoryPath);

        set_error_handler(function () {

        });

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage("Unable to write to log file: " . $directoryPath);
            $processor->processEvent('fail.event', 'data');
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @throws ReflectionException
     */
    public function testFormatEventOutput(): void
    {
        $processor = new FileSystemEventProcessor();

        $processor->setTemplate("Event: {{timestamp}}, {{eventName}}, Args: {{args}}, Custom: {{custom}}\n");
        $processor->setCustomPlaceholders(['{{custom}}' => 'XYZ']);

        $ref = new ReflectionClass($processor);
        $method = $ref->getMethod('formatEvent');

        $eventName = 'format.test';
        $args = ['foo' => 'bar'];
        $formatted = $method->invoke($processor, $eventName, $args);

        $this->assertStringNotContainsString('{{timestamp}}', $formatted);

        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/', $formatted);
        $this->assertStringContainsString('format.test', $formatted);
        $this->assertStringContainsString('"foo":"bar"', $formatted);
        $this->assertStringContainsString("Custom: XYZ", $formatted);
    }
}

# Dummy class
class TestableFileSystemEventProcessor extends FileSystemEventProcessor
{
    public string $lastWrittenLine = '';

    protected function writeToFile(
        string $line
    ): void {
        $this->lastWrittenLine = $line;
    }

    public function testCreateDirectoryIfNotExistsPublic(
        string $directory
    ): void {
        $this->createDirectoryIfNotExists($directory);
    }
}
