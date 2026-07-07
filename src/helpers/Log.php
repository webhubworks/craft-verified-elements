<?php

namespace webhubworks\verifiedelements\helpers;

use Craft;
use craft\base\Element;
use craft\log\Dispatcher;
use craft\log\MonologTarget;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use Throwable;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\Plugin;

/**
 * Use these logging functions to isolate plugin-related info/error messages into its own file.
 *
 * Craft will still include the messages in its own logs, but also create a file named after the
 * plugin's handle so you don't have to sift through the verbose logs.
 *
 * @see Plugin::HANDLE
 * @see Plugin::init()
 */
class Log
{
    /**
     * Logs an informative message.
     *
     * An informative message is typically logged by an application to keep record of
     * something important (e.g. an administrator logs in).
     *
     * @param string $message
     * @param string $target
     * @return void
     */
    public static function info(string $message, string $target = Dispatcher::TARGET_WEB): void
    {
        Craft::info(
            sprintf("[%s] [info] %s.", $target, $message),
            Plugin::HANDLE
        );
    }

    /**
     * Logs a warning message.
     *
     * A warning message is typically logged when an error occurs while the execution
     * can still continue.
     *
     * @param string $message
     * @param string $target
     * @return void
     */
    public static function warning(string $message, string $target = Dispatcher::TARGET_WEB): void
    {
        Craft::warning(
            sprintf("[%s] [warning] %s.", $target, $message),
            Plugin::HANDLE
        );
    }

    /**
     * Logs an error message.
     *
     * An error message is typically logged when an unrecoverable error occurs
     * during the execution of an application.
     *
     * @param string $message
     * @param Throwable|null $exception
     * @param string $target
     * @return void
     */
    public static function error(string $message, ?Throwable $exception = null, string $target = Dispatcher::TARGET_WEB): void
    {
        $message = sprintf("[%s] [error] %s", $target, $message);

        if ($exception) {
            $message = sprintf(
                "%s: '%s'. \n[file] %s [line] %s.\n%s",
                $message,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
                $exception->getTraceAsString()
            );
        }

        Craft::error($message, Plugin::HANDLE);
    }

    /**
     * Registers this plugin's own custom logger.
     *
     * @return void
     * @see Plugin::init()
     */
    public static function registerLogger(): void
    {
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => Plugin::HANDLE,
            'categories' => [Plugin::HANDLE],
            'level' => LogLevel::INFO,
            'logContext' => false,
            'allowLineBreaks' => false,
            'formatter' => new LineFormatter(
                format: "%datetime% %message%\n_____\n",
                dateFormat: 'Y-m-d H:i:s',
                includeStacktraces: true
            ),
        ]);
    }

    /**
     * Returns a human-readable label for an element type (or instance) in log messages.
     *
     * Unknown element types stay identifiable by their class name; non-string garbage
     * becomes an empty string. This method must never throw - it runs on logging paths.
     *
     * @param mixed $type An element instance or FQCN
     * @param bool $plural
     * @param bool $capitalize
     * @return string
     * @see ElementType::label()
     */
    public static function element(mixed $type, bool $plural = false, bool $capitalize = true): string
    {
        $elementType = match (true) {
            $type instanceof Element => ElementType::tryFromElement($type),
            is_string($type) => ElementType::tryFrom($type),
            default => null,
        };

        if ($elementType !== null) {
            return $elementType->label($plural, $capitalize);
        }

        return is_string($type) ? $type : '';
    }
}
