<?php

declare(strict_types=1);

namespace ZendTechTest\ZendHQ\MonologHandler;

use DateTimeImmutable;
use InvalidArgumentException;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use ZendTech\ZendHQ\MonologHandler\ZendHQHandler;

use function class_exists;
use function interface_exists;

class ZendHQHandlerTest extends TestCase
{
    /** @var callable(string, string, null|array=, int=) */
    private $monitorCallback;

    /** @var callable(string, string, string, null|array=) */
    private $customMonitorCallback;

    /** @var object */
    private $logRecord;

    public function setUp(): void
    {
        $this->logRecord = (object) [
            'type'         => null,
            'text'         => null,
            'userData'     => null,
            'userSeverity' => null,
            'ruleName'     => null,
        ];

        $this->monitorCallback = function (
            string $type,
            string $text,
            $userData = null,
            int $userSeverity = ZendHQHandler::SEVERITY_NOTICE
        ): void {
            $this->logRecord->type         = $type;
            $this->logRecord->text         = $text;
            $this->logRecord->userData     = $userData;
            $this->logRecord->userSeverity = $userSeverity;
        };

        $this->customMonitorCallback = function (
            string $type,
            string $text,
            string $ruleName,
            $userData = null
        ): void {
            $this->logRecord->type     = $type;
            $this->logRecord->text     = $text;
            $this->logRecord->ruleName = $ruleName;
            $this->logRecord->userData = $userData;
        };
    }

    private function injectHandlerWithCallbacks(ZendHQHandler $handler): void
    {
        $r = new ReflectionProperty($handler, 'monitorCallback');
        $r->setAccessible(true);
        $r->setValue($handler, $this->monitorCallback);

        $r = new ReflectionProperty($handler, 'customMonitorCallback');
        $r->setAccessible(true);
        $r->setValue($handler, $this->customMonitorCallback);
    }

    private function createRecord(
        string $channel,
        int $level,
        string $message,
        array $context = [],
        array $extra = []
    ): array|LogRecord {
        if (class_exists(LogRecord::class) && ! interface_exists(LogRecord::class)) {
            return new LogRecord(
                new DateTimeImmutable(),
                $channel,
                Level::fromValue($level),
                $message,
                $context,
                $extra
            );
        }

        return [
            'message' => $message,
            'level'   => $level,
            'context' => $context,
            'channel' => $channel,
            'extra'   => $extra,
        ];
    }

    public static function invalidRuleNames(): iterable
    {
        yield 'empty' => [''];
        yield 'space-only' => [' '];
        yield 'leads-with-tab' => ["\tfoo"];
        yield 'leads-with-special-char' => ['&foo'];
    }

    /**
     * @dataProvider invalidRuleNames
     */
    public function testConstructRaisesExceptionForInvalidRuleName(string $ruleName): void
    {
        $this->expectException(InvalidArgumentException::class);
        new ZendHQHandler($ruleName);
    }

    public static function expectedSeverityMap(): iterable
    {
        yield 'debug'     => [Logger::DEBUG, ZendHQHandler::SEVERITY_NOTICE];
        yield 'info'      => [Logger::INFO, ZendHQHandler::SEVERITY_NOTICE];
        yield 'notice'    => [Logger::NOTICE, ZendHQHandler::SEVERITY_NOTICE];
        yield 'warning'   => [Logger::WARNING, ZendHQHandler::SEVERITY_WARNING];
        yield 'error'     => [Logger::ERROR, ZendHQHandler::SEVERITY_WARNING];
        yield 'critical'  => [Logger::CRITICAL, ZendHQHandler::SEVERITY_CRITICAL];
        yield 'alert'     => [Logger::ALERT, ZendHQHandler::SEVERITY_CRITICAL];
        yield 'emergency' => [Logger::EMERGENCY, ZendHQHandler::SEVERITY_CRITICAL];
    }

    /**
     * @dataProvider expectedSeverityMap
     */
    public function testHandlerCreatedWithoutRuleUsesDefaultMonitorCallback(int $level, int $expectedSeverity): void
    {
        $handler = new ZendHQHandler();
        $this->injectHandlerWithCallbacks($handler);

        $record = $this->createRecord(
            'test',
            $level,
            'log message',
            ['context' => 'value', 'another' => 'value'],
            ['another' => 'override', 'extra' => 'value']
        );
        $handler->handle($record);

        $this->assertSame('test', $this->logRecord->type);
        $this->assertSame('log message', $this->logRecord->text);
        $this->assertEquals(
            ['context' => 'value', 'another' => 'value', 'extra' => 'value'],
            $this->logRecord->userData
        );
        $this->assertSame($expectedSeverity, $this->logRecord->userSeverity);
        $this->assertNull($this->logRecord->ruleName);
    }

    /**
     * @dataProvider expectedSeverityMap
     */
    public function testHandlerCreatedWithRuleUsesCustomMonitorCallback(int $level): void
    {
        $handler = new ZendHQHandler('rule_name');
        $this->injectHandlerWithCallbacks($handler);

        $record = $this->createRecord(
            'test',
            $level,
            'log message',
            ['context' => 'value', 'another' => 'value'],
            ['another' => 'override', 'extra' => 'value']
        );
        $handler->handle($record);

        $this->assertSame('test', $this->logRecord->type);
        $this->assertSame('log message', $this->logRecord->text);
        $this->assertEquals(
            ['context' => 'value', 'another' => 'value', 'extra' => 'value'],
            $this->logRecord->userData
        );
        $this->assertSame('rule_name', $this->logRecord->ruleName);
        $this->assertNull($this->logRecord->userSeverity);
    }
}
