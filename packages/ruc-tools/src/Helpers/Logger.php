<?php

namespace RucTool\Helpers;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;

class Logger
{
    private static ?MonologLogger $instance = null;
    private MonologLogger $logger;

    private function __construct(string $logDir)
    {
        $this->ensureLogDir($logDir);

        $this->logger = new MonologLogger('ruc-tool');

        $fileHandler = new RotatingFileHandler(
            "$logDir/ruc-tool.log",
            30,
            MonologLogger::DEBUG
        );

        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message%\n",
            "Y-m-d H:i:s"
        );

        $fileHandler->setFormatter($formatter);
        $this->logger->pushHandler($fileHandler);
    }

    private function ensureLogDir(string $logDir): void
    {
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
    }

    public static function getInstance(string $logDir = null): MonologLogger
    {
        if (self::$instance === null) {
            $logDir = $logDir ?? getenv('HOME') . '/.ruc-tool/logs';
            $logger = new self($logDir);
            self::$instance = $logger->logger;
        }

        return self::$instance;
    }

    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }
}
