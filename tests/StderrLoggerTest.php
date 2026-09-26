<?php declare(strict_types=1);

namespace Bref\Logger\Test;

use Bref\Logger\StderrLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use RuntimeException;
use Stringable;

class StderrLoggerTest extends TestCase
{
    /** @var resource */
    private $stream;
    private StderrLogger $logger;

    public function setUp(): void
    {
        parent::setUp();

        $stream = fopen('php://memory', 'a+');
        if ($stream === false) {
            throw new RuntimeException('Unable to open a memory stream');
        }
        $this->stream = $stream;
        $this->logger = new StderrLogger(LogLevel::DEBUG, $this->stream);
    }

    public function test_log_messages_format(): void
    {
        $this->logger->debug('Debug');
        $this->logger->info('Info');
        $this->logger->notice('Notice');
        $this->logger->warning('Alert');
        $this->logger->error('Error');
        $this->logger->critical('Critical');
        $this->logger->alert('Alert');
        $this->logger->emergency('Emergency');

        $this->assertLogsMatch(<<<'LOGS'
DEBUG	Debug	{"message":"Debug","level":"DEBUG"}
INFO	Info	{"message":"Info","level":"INFO"}
NOTICE	Notice	{"message":"Notice","level":"NOTICE"}
WARNING	Alert	{"message":"Alert","level":"WARNING"}
ERROR	Error	{"message":"Error","level":"ERROR"}
CRITICAL	Critical	{"message":"Critical","level":"CRITICAL"}
ALERT	Alert	{"message":"Alert","level":"ALERT"}
EMERGENCY	Emergency	{"message":"Emergency","level":"EMERGENCY"}

LOGS
        );
    }

    public function test_logs_above_the_configured_log_level(): void
    {
        $this->logger = new StderrLogger(LogLevel::WARNING, $this->stream);
        $this->logger->debug('Debug');
        $this->logger->info('Info');
        $this->logger->notice('Notice');
        $this->logger->warning('Alert');
        $this->logger->error('Error');
        $this->logger->critical('Critical');
        $this->logger->alert('Alert');
        $this->logger->emergency('Emergency');

        $this->assertLogsMatch(<<<'LOGS'
WARNING	Alert	{"message":"Alert","level":"WARNING"}
ERROR	Error	{"message":"Error","level":"ERROR"}
CRITICAL	Critical	{"message":"Critical","level":"CRITICAL"}
ALERT	Alert	{"message":"Alert","level":"ALERT"}
EMERGENCY	Emergency	{"message":"Emergency","level":"EMERGENCY"}

LOGS
        );
    }

    /**
     * @param mixed $contextValue
     */
    #[DataProvider('provideInterpolationExamples')]
    public function test_log_messages_are_interpolated($contextValue, string $expectedMessage): void
    {
        $this->logger->info('{foo}', [
            'foo' => $contextValue,
        ]);

        $logs = $this->getLogs();
        $this->assertStringStartsWith('INFO	' . $expectedMessage . '	', $logs);
    }

    /**
     * @return list<array{mixed, string}>
     */
    public static function provideInterpolationExamples(): array
    {
        $date = new \DateTime;
        return [
            ['foo', 'foo'],
            ['3', '3'],
            [3, '3'],
            [null, ''],
            [true, '1'],
            [false, ''],
            [$date, $date->format(\DateTime::RFC3339)],
            [new \stdClass, '{object stdClass}'],
            [[], '[]'],
            [[1, 2, 3], '[1,2,3]'],
            [['foo' => 'bar'], '{"foo":"bar"}'],
            [stream_context_create(), '{resource}'],
        ];
    }

    public function test_logs_with_context(): void
    {
        $this->logger->info('Test message', ['key' => 'value']);

        $this->assertLogsMatch(<<<'LOGS'
INFO	Test message	{"message":"Test message","level":"INFO","context":{"key":"value"}}

LOGS
        );
    }

    public function test_multiline_message(): void
    {
        $this->logger->error("Test\nmessage");

        $this->assertLogsMatch(<<<'LOGS'
ERROR	Test message	{"message":"Test\nmessage","level":"ERROR"}

LOGS
        );
    }

    public function test_with_exception(): void
    {
        $e = new \Exception('Test error');
        $this->logger->info('Test message', ['exception' => $e]);

        $logs = $this->getLogs();
        $this->assertStringStartsWith('INFO	Test message	{"message":"Test message","level":"INFO","exception":', $logs);
        $this->assertStringContainsString('"class":"Exception"', $logs);
        $this->assertStringContainsString('"message":"Test error"', $logs);
    }

    public function test_lines_start_with_the_lambda_request_id(): void
    {
        $_SERVER['LAMBDA_REQUEST_ID'] = '8f507cfc-8b35-4e7e-9f26-f2a3a6e7e1a2';
        try {
            $this->logger->info('Test message');
        } finally {
            unset($_SERVER['LAMBDA_REQUEST_ID']);
        }

        $this->assertLogsMatch(<<<'LOGS'
8f507cfc-8b35-4e7e-9f26-f2a3a6e7e1a2	INFO	Test message	{"message":"Test message","level":"INFO"}

LOGS
        );
    }

    public function test_unsupported_log_levels_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->logger->log('warn', 'Test message');
    }

    public function test_stringable_messages_are_logged(): void
    {
        $this->logger->info(new class implements Stringable {
            public function __toString(): string
            {
                return 'Test message';
            }
        });

        $this->assertLogsMatch(<<<'LOGS'
INFO	Test message	{"message":"Test message","level":"INFO"}

LOGS
        );
    }

    private function assertLogsMatch(string $expectedLog): void
    {
        self::assertStringMatchesFormat($expectedLog, $this->getLogs());
    }

    private function getLogs(): string
    {
        rewind($this->stream);
        $logs = stream_get_contents($this->stream);
        if ($logs === false) {
            throw new RuntimeException('Unable to read the logs');
        }

        return $logs;
    }
}