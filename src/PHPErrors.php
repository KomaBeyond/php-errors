<?php

/**
 * php-error for catch all php running time errors.
 *
 * @author Koma <komazhang@foxmail.com>
 * @date 2017-05-31
 *
 * @copyright Koma
 *
 */
namespace PHPErrors;

use Psr\Log\LogLevel;

class PHPErrors
{
    public $logger;
    public static $reportLevel = E_ALL;

    public static function enable($errorReportLevel = E_ALL, $devMode = true)
    {
        if ($errorReportLevel != null) {
            error_reporting($errorReportLevel);
            self::$reportLevel = $errorReportLevel;
        } else {
            error_reporting(E_ALL);
        }

        if ($devMode) {
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
        } else {
            ini_set('display_errors', 0);
            ini_set('display_startup_errors', 0);
        }

        ini_set('log_errors', 1);

        $handler = new static();
        return $handler->register();
    }

    public function register()
    {
        register_shutdown_function(array($this, 'handleFatalError'));
        set_error_handler(array($this, 'handleError'));
        set_exception_handler(array($this, 'handleException'));

        return $this;
    }

    public function log($type, $message)
    {
        $level = $this->getErrorLevel($type);

        if ( $this->logger != null ) {
            return $this->logger->log($level, $message);
        }

        error_log("{$level}: {$message}");
    }

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    public function handleFatalError()
    {
        $error = error_get_last();

        if (!empty($error)) {
            $type = $error['type'];

            if ($type & self::$reportLevel) {
                $message = $this->formatMessage($error['message'], $error['file'], $error['line']);

                return $this->log($type, $message);
            }
        }
    }

    public function handleError($type, $message, $file, $line)
    {
        if ($type & self::$reportLevel) {
            $message = $this->formatMessage($message, $file, $line);

            return $this->log($type, $message);
        }
    }

    public function handleException(\Throwable $exception)
    {
        //Error code 需要转换成 Exception 的错误级别
        //这里把所有的 Error 都转换成 E_ERROR 级别异常, 用来做捕获
        if ($exception instanceof \Error) {
            $exception = new \ErrorException($exception->getMessage(), 0, E_ERROR, $exception->getFile(), $exception->getLine());
        }

        if ($exception instanceof \ErrorException || $exception instanceof \Exception) {
            $type = $exception instanceof \ErrorException ? $exception->getSeverity() : E_ERROR;

            if ($type & self::$reportLevel) {
                $message = $this->formatMessage(
                    $exception->getTraceAsString(),
                    $exception->getFile(),
                    $exception->getLine()
                );

                return $this->log($type, $message);
            }
        }
    }

    public function formatMessage($message, $file, $line)
    {
        return $message = "{$file}#{$line}: {$message}";
    }

    public function getErrorLevel($type)
    {
        $level = LogLevel::EMERGENCY;

        switch ($type) {
            case E_ERROR:
            case E_CORE_ERROR:
                $level = LogLevel::CRITICAL;
                break;
            case E_WARNING:
            case E_CORE_WARNING:
            case E_CORE_WARNING:
            case E_USER_WARNING:
                $level = LogLevel::WARNING;
                break;
            case E_PARSE:
            case E_COMPILE_ERROR:
                $level = LogLevel::ALERT;
                break;
            case E_NOTICE:
            case E_STRICT:
            case E_DEPRECATED:
            case E_USER_NOTICE:
            case E_USER_DEPRECATED:
                $level = LogLevel::NOTICE;
                break;
            case E_USER_ERROR:
            case E_RECOVERABLE_ERROR:
                $level = LogLevel::ERROR;
                break;
        }

        return $level;
    }
}
