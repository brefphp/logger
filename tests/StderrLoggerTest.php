<?php declare(strict_types=1);

namespace Bref\Logger\Test;

use Bref\Logger\StderrLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

class StderrLoggerTest extends TestCase
{
    /** @var resource */
    private $stream;
    /** @var StderrLogger */
    private $logger;

    public function setUp(): void
    {
        parent::setUp();

        $this->stream = fopen('php://memory', 'a+');
        $this->logger = new StderrLogger(LogLevel::DEBUG, $this->stream);
    }

    public function test_log_messages_format()
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

    public function test_logs_above_the_configured_log_level()
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
    public function test_log_messages_are_interpolated($contextValue, string $expectedMessage)
    {
        $this->logger->info('{foo}', [
            'foo' => $contextValue,
        ]);

        $logs = $this->getLogs();
        $this->assertStringStartsWith('INFO	' . $expectedMessage . '	', $logs);
    }

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

    public function test_logs_with_context()
    {
        $this->logger->info('Test message', ['key' => 'value']);

        $this->assertLogsMatch(<<<'LOGS'
INFO	Test message	{"message":"Test message","level":"INFO","context":{"key":"value"}}

LOGS
        );
    }

    public function test_multiline_message()
    {
        $this->logger->error("Test\nmessage");

        $this->assertLogsMatch(<<<'LOGS'
ERROR	Test message	{"message":"Test\nmessage","level":"ERROR"}

LOGS
        );
    }

    public function test_with_exception()
    {
        $e = new \Exception('Test error');
        $this->logger->info('Test message', ['exception' => $e]);

        $logs = $this->getLogs();
        $this->assertStringStartsWith('INFO	Test message	{"message":"Test message","level":"INFO","exception":', $logs);
        $this->assertStringContainsString('"class":"Exception"', $logs);
        $this->assertStringContainsString('"message":"Test error"', $logs);
    }

    private function assertLogsMatch(string $expectedLog): void
    {
        rewind($this->stream);
        self::assertStringMatchesFormat($expectedLog, fread($this->stream, fstat($this->stream)['size']));
    }

    private function getLogs(): string
    {
        rewind($this->stream);
        return stream_get_contents($this->stream);
    }
}