<?php

declare(strict_types=1);

namespace ZendTech\ZendHQ\MonologHandler;

use InvalidArgumentException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;

use function array_merge;
use function class_exists;
use function is_string;
use function monitor_custom_event;
use function monitor_custom_event_ex;
use function preg_match;
use function sprintf;

class ZendHQHandler extends AbstractProcessingHandler
{
    public const SEVERITY_NOTICE   = -1;
    public const SEVERITY_WARNING  = 0;
    public const SEVERITY_CRITICAL = 1;

    /** @var null|string */
    private $ruleName;

    /**
     * @internal
     *
     * @var callable
     */
    private $monitorCallback;

    /**
     * @internal
     *
     * @var callable
     */
    private $customMonitorCallback;

    public function __construct(?string $ruleName = null, int $level = Logger::DEBUG, bool $bubble = true)
    {
        if (is_string($ruleName) && ! preg_match('/^[a-z0-9]/i', $ruleName)) {
            throw new InvalidArgumentException(sprintf(
                'Rule name must be non-empty and start with an alphanumeric; received "%s"',
                $ruleName
            ));
        }

        $this->ruleName        = $ruleName;
        $this->monitorCallback = function (
            string $type,
            string $text,
            $userData = null,
            int $userSeverity = self::SEVERITY_NOTICE
        ): void {
            monitor_custom_event($type, $text, $userData, $userSeverity);
        };

        $this->customMonitorCallback = function (
            string $type,
            string $text,
            string $ruleName,
            $userData = null
        ): void {
            monitor_custom_event_ex($type, $text, $this->ruleName, $userData);
        };

        parent::__construct($level, $bubble);
    }

    protected function write(array|LogRecord $record): void
    {
        $userData = array_merge($record['extra'], $record['context']);

        if (null !== $this->ruleName) {
            $callback = $this->customMonitorCallback;
            $callback($record['channel'], $record['message'], $this->ruleName, $userData);
            return;
        }

        $callback = $this->monitorCallback;
        $callback($record['channel'], $record['message'], $userData, $this->getSeverityFromLogRecord($record));
    }

    private function getSeverityFromLogRecord(array|LogRecord $record): int
    {
        $logLevel = $this->getLogLevelFromLogRecord($record);
        switch (true) {
            case $logLevel === Logger::CRITICAL:
            case $logLevel === Logger::ALERT:
            case $logLevel === Logger::EMERGENCY:
                return self::SEVERITY_CRITICAL;

            case $logLevel === Logger::WARNING:
            case $logLevel === Logger::ERROR:
                return self::SEVERITY_WARNING;

            case $logLevel === Logger::DEBUG:
            case $logLevel === Logger::INFO:
            case $logLevel === Logger::NOTICE:
            default:
                return self::SEVERITY_NOTICE;
        }
    }

    private function getLogLevelFromLogRecord(array|LogRecord $record): int
    {
        if (class_exists(Level::class)) {
            return $record->level->value;
        }

        if (! isset($record['level'])) {
            return Logger::DEBUG;
        }

        return (int) $record['level'];
    }
}
