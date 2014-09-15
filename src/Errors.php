<?php
/**
 * Fol\Errors
 *
 * A simple class to handle all php errors.
 */
namespace Fol;

class Errors
{
    protected static $handlers = array();
    protected static $isRegistered = false;
    protected static $displayErrors = false;

    /**
     * Enable or disable the error displaying
     *
     * @param boolean $display True to display, false to not
     */
    public static function displayErrors($display = true)
    {
        static::$displayErrors = $display;
    }

    /**
     * Register the php error log file.
     */
    public static function setPhpLogFile($file)
    {
        ini_set('error_log', $file);
    }

    /**
     * Pushes a handler to the end of the stack.
     *
     * @param callable $handler The callback to execute
     */
    public static function pushHandler(callable $handler)
    {
        static::$handlers[] = $handler;
    }

    /**
     * Removes the last handler and returns it
     *
     * @return callable|null
     */
    public static function popHandler()
    {
        return array_pop(static::$handlers);
    }

    /**
     * Register the error handler.
     */
    public static function register()
    {
        if (!static::$isRegistered) {
            set_error_handler(__NAMESPACE__.'\\Errors::handleError');
            set_exception_handler(__NAMESPACE__.'\\Errors::handleException');
            register_shutdown_function(__NAMESPACE__.'\\Errors::handleShutdown');

            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');

            static::$isRegistered = true;
        }
    }

    /**
     * Unregister the error handler. Restore the error handler to previous status.
     */
    public static function unregister()
    {
        if (static::$isRegistered) {
            restore_error_handler();
            restore_exception_handler();

            ini_set('display_errors', get_cfg_var('display_errors'));
            ini_set('display_startup_errors', get_cfg_var('display_startup_errors'));

            static::$isRegistered = false;
        }
    }

    /**
     * Converts a php error to an exception and handle it
     *
     * @param int    $level   The error level
     * @param string $message The error message
     * @param string $file    The file when the error is
     * @param int    $file    The number of the line when the error is
     */
    public static function handleError($level, $message, $file = null, $line = null)
    {
        if (error_reporting() & $level) {
            static::handleException(new \ErrorException($message, $level, 0, $file, $line));
        }
    }

    /**
     * Converts a php shutdown error to an exception and handle it
     */
    public static function handleShutdown()
    {
        if (static::$isRegistered && ($error = error_get_last())) {
            static::handleError($error['type'], $error['message'], $error['file'], $error['line']);
        }
    }

    /**
     * Execute all registered callbacks
     *
     * @param \Exception $exception exception passed to the callbacks
     */
    public static function handleException(\Exception $exception)
    {
        foreach (static::$handlers as $handler) {
            $handler($exception);
        }

        if (static::$displayErrors) {
            echo (php_sapi_name() === 'cli') ? self::getTextException($exception) : self::getHtmlException($exception);
        }
    }

    /**
     * Returns an exception info as HTML
     *
     * @param \Exception $exception
     *
     * @return string
     */
    public static function getHtmlException(\Exception $exception, $deep = 0)
    {
        $previous = ($previousException = $exception->getPrevious()) ? self::getHtmlException($previousException, $deep + 1) : '';
        $class = get_class($exception);
        $date = ($deep === 0) ? '<time>'.date('r').'</time><br>' : '';

        return <<<EOT
<section id="ErrorException">
    {$date}
    <h1>{$exception->getMessage()} ({$exception->getCode()})</h1>
    <p>
        <em>{$class}</em><br>
        {$exception->getFile()}:{$exception->getLine()}<br>
    </p>
    <pre>{$exception->getTraceAsString()}</pre>
    {$previous}
</section>
EOT;
    }

    /**
     * Returns an exception info as plain text
     *
     * @param \Exception $exception
     * @param integer    $deep
     *
     * @return string
     */
    public static function getTextException(\Exception $exception, $deep = 0)
    {
        $previous = ($previousException = $exception->getPrevious()) ? self::getTextException($previousException, $deep + 1) : '';
        $class = get_class($exception);

        return (($deep === 0) ? "\n=======================\n".date('r')."\n\n" : "\n----------\n")
            ."{$exception->getMessage()} ({$exception->getCode()})\n"
            ."{$class} | {$exception->getFile()}:{$exception->getLine()}\n\n"
            .$exception->getTraceAsString()
            .$previous
            .(($deep === 0) ? "\n=======================\n" : "\n");
    }

    /**
     * Returns an exception info as array
     *
     * @param \Exception $exception
     * @param integer    $deep
     *
     * @return array
     */
    public static function getArrayException(\Exception $exception, $deep = 0)
    {
        $previous = ($previousException = $exception->getPrevious()) ? self::getArrayException($previousException, $deep + 1) : '';
        $class = get_class($exception);

        $trace = [];

        foreach ($exception->getTrace() as $v) {
            $trace[] = [
                'file' => isset($v['file']) ? $v['file'] : null,
                'line' => isset($v['line']) ? $v['line'] : null
            ];
        }

        return [
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'exception' => $class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $trace,
            'previous' => $previous
        ];
    }

    /**
     * Returns an exception info as json
     *
     * @param \Exception $exception
     * @param integer    $deep
     *
     * @return string
     */
    public static function getJsonException(\Exception $exception, $deep = 0)
    {
        return json_encode(self::getArrayException($exception, $deep), JSON_PRETTY_PRINT|JSON_NUMERIC_CHECK|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
    }
}