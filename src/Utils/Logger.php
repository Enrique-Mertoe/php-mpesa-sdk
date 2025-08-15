<?php

namespace MpesaSDK\Utils;

class Logger
{
    const LEVEL_DEBUG = 'debug';
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';

    private $logLevel;
    private $logFile;
    private $enableConsole;

    public function __construct(
        string $logLevel = self::LEVEL_INFO,
        ?string $logFile = null,
        bool $enableConsole = true
    ) {
        $this->logLevel = $logLevel;
        $this->logFile = $logFile;
        $this->enableConsole = $enableConsole;
    }

    /**
     * Log debug message
     */
    public function debug(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_DEBUG, $message, $context);
    }

    /**
     * Log info message
     */
    public function info(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Log warning message
     */
    public function warning(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log error message
     */
    public function error(string $message, array $context = []): void
    {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log message with level
     */
    public function log(string $level, string $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';

        $logMessage = "[{$timestamp}] {$level}: {$message}{$contextStr}";

        // Log to file
        if ($this->logFile) {
            error_log($logMessage . PHP_EOL, 3, $this->logFile);
        }

        // Log to console/error log
        if ($this->enableConsole) {
            error_log($logMessage);
        }
    }

    /**
     * Check if level should be logged
     */
    private function shouldLog(string $level): bool
    {
        $levels = [
            self::LEVEL_DEBUG => 0,
            self::LEVEL_INFO => 1,
            self::LEVEL_WARNING => 2,
            self::LEVEL_ERROR => 3
        ];

        $currentLevel = $levels[$this->logLevel] ?? 1;
        $messageLevel = $levels[$level] ?? 1;

        return $messageLevel >= $currentLevel;
    }

    /**
     * Set log level
     */
    public function setLogLevel(string $level): void
    {
        $this->logLevel = $level;
    }

    /**
     * Set log file
     */
    public function setLogFile(?string $logFile): void
    {
        $this->logFile = $logFile;
    }

    /**
     * Enable/disable console logging
     */
    public function setConsoleLogging(bool $enable): void
    {
        $this->enableConsole = $enable;
    }

    /**
     * Create logger from environment variables
     */
    public static function fromEnv(): self
    {
        return new self(
            $_ENV['MPESA_LOG_LEVEL'] ?? self::LEVEL_INFO,
            $_ENV['MPESA_LOG_FILE'] ?? null,
            filter_var($_ENV['MPESA_LOG_CONSOLE'] ?? 'true', FILTER_VALIDATE_BOOLEAN)
        );
    }
}