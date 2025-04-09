<?php

declare(strict_types=1);

namespace DomainFlow\SystemEvents\Processor;

use DomainFlow\SystemEvents\Interface\SystemEventProcessorInterface;
use RuntimeException;

/**
 * Class FileSystemEventWriter
 *
 * Writes events to a specified file on disk with a customizable template.
 * If no log file path is provided, it uses an environment variable or a default directory.
 */
class FileSystemEventProcessor implements SystemEventProcessorInterface
{
    protected string $filePath;

    /**
     * Template used for log entries.
     */
    protected string $template;

    /**
     * Optional custom placeholders that can be injected externally.
     *
     * @var array<string, string>
     */
    protected array $customPlaceholders = [];

    /**
     * Set the custom file path, custom placeholders, and custom template.
     */
    public function __construct()
    {
        $this->setCustomFilePath();
        $this->createDirectoryIfNotExists(dirname($this->filePath));
        $this->setCustomPlaceholderTemplate();
        $this->getCustomPlaceholders();
    }

    /**
     * Get custom placeholders from the environment.
     *
     * This method expects a JSON string in the environment variable CUSTOM_LOG_PLACEHOLDERS.
     *
     * @return void
     */
    public function getCustomPlaceholders(): void
    {
        $placeholdersJson = getenv('CUSTOM_LOG_PLACEHOLDERS');
        if ($placeholdersJson) {
            /** @var array<string, string> $decoded */
            $decoded = json_decode(
                $placeholdersJson,
                true
            );
            if (is_array($decoded)) {
                $this->setCustomPlaceholders(
                    $decoded
                );
            }
        }
    }

    /**
     * Set the custom placeholder template.
     *
     * @return void
     */
    public function setCustomPlaceholderTemplate(): void
    {
        $template = getenv('CUSTOM_LOG_TEMPLATE')
            ?: "[{{timestamp}}] Event: {{eventName}}; Args: {{args}}\n";
        $this->setTemplate($template);
    }

    /**
     * Set the file path for the log file.
     *
     * @return void
     */
    public function setCustomFilePath(): void
    {
        $filePath = getenv('LOG_FILE_PATH')
            ?: getcwd() . '/logs/' . date('Y-m-d') . '-system-events.log';
        $this->filePath = $filePath;
    }

    /**
     * Set custom placeholders that will be merged with default ones.
     *
     * @param array<string, string> $placeholders
     * @return void
     */
    public function setCustomPlaceholders(
        array $placeholders
    ): void {
        $this->customPlaceholders = $placeholders;
    }

    /**
     * Write an event to the file using the user-defined template.
     *
     * @param string $eventName
     * @param mixed ...$args
     * @return void
     */
    public function processEvent(
        string $eventName,
        mixed ...$args
    ): void {
        $line = $this->formatEvent(
            $eventName,
            $args
        );
        $this->writeToFile($line);
    }

    /**
     * Format an event into a log line.
     *
     * @param string $eventName
     * @param array<int|string, mixed> $args
     * @return string
     */
    private function formatEvent(
        string $eventName,
        array $args
    ): string {
        $placeholders = [
            '{{timestamp}}' => date('Y-m-d H:i:s'),
            '{{eventName}}' => $eventName,
            '{{args}}' => json_encode($args, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        $allPlaceholders = array_merge($placeholders, $this->customPlaceholders);

        return str_replace(
            array_keys($allPlaceholders),
            array_map('strval', array_values($allPlaceholders)),
            $this->template
        );
    }

    /**
     * Write a line to the log file.
     *
     * @param string $line
     * @throws RuntimeException
     * @return void
     */
    private function writeToFile(
        string $line
    ): void {
        $result = file_put_contents(
            $this->filePath,
            $line,
            FILE_APPEND | LOCK_EX
        );
        if ($result === false) {
            throw new RuntimeException(
                "Unable to write to log file: {$this->filePath}"
            );
        }
    }

    /**
     * Optionally expose a setter so devs can update the template at runtime.
     *
     * @param string $template
     * @return void
     */
    public function setTemplate(
        string $template
    ): void {
        $this->template = str_replace(
            '\n',
            "\n",
            $template
        );
    }

    /**
     * Create the directory if it does not exist.
     *
     * @param string $directory
     * @return void
     */
    protected function createDirectoryIfNotExists(
        string $directory
    ): void {
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
    }
}
