<?php

namespace webhubworks\verifiedentries\helpers;

use Craft;
use craft\log\Dispatcher;
use craft\log\MonologTarget;
use Monolog\Formatter\LineFormatter;
use Psr\Log\LogLevel;
use Throwable;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Use these logging functions to isolate plugin-related info/error messages into its own file.
 *
 * Craft will still include the messages in its own logs, but also create a file named after the
 * plugin's handle so you don't have to sift through the verbose logs.
 *
 * @see VerifiedEntries::HANDLE
 * @see VerifiedEntries::init()
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
            VerifiedEntries::HANDLE
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
            VerifiedEntries::HANDLE
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

        Craft::error($message, VerifiedEntries::HANDLE);
    }

    /**
     * Registers this plugin's own custom logger.
     *
     * @return void
     * @see VerifiedEntries::init()
     */
    public static function registerLogger(): void
    {
        Craft::getLogger()->dispatcher->targets[] = new MonologTarget([
            'name' => VerifiedEntries::HANDLE,
            'categories' => [VerifiedEntries::HANDLE],
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
}
