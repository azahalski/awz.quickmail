<?php
namespace Awz\Quickmail;

use Bitrix\Main\Diag\Logger as DiagLogger;

/**
 * Класс логгера для модуля awz.quickmail
 * Использует PSR-совместимый логгер Bitrix
 */
class Logger
{
    public static $requestId;
    const MODULE_ID = 'awz.quickmail';

    /**
     * Запись информационного сообщения
     *
     * @param string $message Сообщение
     * @param array $context Дополнительный контекст
     * @return void
     */
    public static function info(string $message, array $context = []): void
    {
        self::log('info', $message, $context);
    }

    /**
     * Запись предупреждения
     *
     * @param string $message Сообщение
     * @param array $context Дополнительный контекст
     * @return void
     */
    public static function warning(string $message, array $context = []): void
    {
        self::log('warning', $message, $context);
    }

    /**
     * Запись ошибки
     *
     * @param string $message Сообщение
     * @param array $context Дополнительный контекст
     * @return void
     */
    public static function error(string $message, array $context = []): void
    {
        self::log('error', $message, $context);
    }

    /**
     * Запись отладочного сообщения
     *
     * @param string $message Сообщение
     * @param array $context Дополнительный контекст
     * @return void
     */
    public static function debug(string $message, array $context = []): void
    {
        self::log('debug', $message, $context);
    }

    /**
     * Запись сообщения в лог с указанным уровнем
     *
     * @param string $level Уровень логирования (info, warning, error, debug)
     * @param string $message Сообщение
     * @param array $context Дополнительный контекст
     * @return void
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        if(!self::$requestId) self::$requestId = \Bitrix\Main\Security\Random::getString(16);
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        try {
            $logger = DiagLogger::create(static::MODULE_ID, [null]);
            $logger->log($level, self::$requestId.' | '.date("c").' | '.$message.$contextStr."\n", array_merge(['module' => self::MODULE_ID], $context));
        } catch (\Exception $e) {
            // Фолбэк: если PSR-логгер недоступен, используем CEventLog
            self::fallbackLog($level, $message, $context);
        }
    }

    /**
     * Фолбэк для логирования через CEventLog
     *
     * @param string $level Уровень логирования
     * @param string $message Сообщение
     * @param array $context Дополнительный контекст
     * @return void
     */
    private static function fallbackLog(string $level, string $message, array $context = []): void
    {
        $severityMap = [
            'info' => 'INFO',
            'warning' => 'WARNING',
            'error' => 'ERROR',
            'debug' => 'DEBUG'
        ];

        $auditTypeMap = [
            'info' => 'AWZ_QUICKMAIL_INFO',
            'warning' => 'AWZ_QUICKMAIL_WARNING',
            'error' => 'AWZ_QUICKMAIL_ERROR',
            'debug' => 'AWZ_QUICKMAIL_DEBUG'
        ];

        $severity = $severityMap[$level] ?? 'INFO';
        $auditType = $auditTypeMap[$level] ?? 'AWZ_QUICKMAIL_INFO';

        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';

        \CEventLog::Add([
            'SEVERITY' => $severity,
            'AUDIT_TYPE_ID' => $auditType,
            'MODULE_ID' => self::MODULE_ID,
            'DESCRIPTION' => $message . $contextStr
        ]);
    }
}