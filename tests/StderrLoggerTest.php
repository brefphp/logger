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

    public function test log messages to stderr()
    {
        $this->logger->debug('Debug');
        $this->logger->info('Info');
        $this->logger->notice('Notice');
        $this->logger->warning('Alert');
        $this->logger->error('Error');
        $this->logger->critical('Critical');
        $this->logger->alert('Alert');
        $this->logger->emergency('Emergency');

        $this->assertLogs(<<<'LOGS'
[DEBUG] Debug
[INFO] Info
[NOTICE] Notice
[WARNING] Alert
[ERROR] Error
[CRITICAL] Critical
[ALERT] Alert
[EMERGENCY] Emergency

LOGS
        );
    }

    public function test logs above the configured log level()
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

        $this->assertLogs(<<<'LOGS'
[WARNING] Alert
[ERROR] Error
[CRITICAL] Critical
[ALERT] Alert
[EMERGENCY] Emergency

LOGS
        );
    }

    /**
     * @param mixed $contextValue
     *
     * @dataProvider provideInterpolationExamples
     */
    #[DataProvider('provideInterpolationExamples')]
    public function test log messages are interpolated($contextValue, string $expectedMessage)
    {
        $this->logger->info('{foo}', [
            'foo' => $contextValue,
        ]);

        $this->assertLogs(<<<LOGS
[INFO] $expectedMessage

LOGS
        );
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

    public function test exceptions are logged()
    {
        $this->logger->info('Exception', [
            'exception' => $this->createTestException(),
        ]);

        $currentFile = __FILE__;
        $this->assertLogsMatch(<<<LOGS
[INFO] Exception
InvalidArgumentException: This is an exception message in $currentFile:%d
Stack trace:
#0 $currentFile(%d): Bref\Logger\Test\StderrLoggerTest->createTestException()
#1 %a
LOGS
        );
    }

    private function assertLogs(string $expectedLog): void
    {
        rewind($this->stream);
        self::assertSame($expectedLog, fread($this->stream, fstat($this->stream)['size']));
    }

    private function assertLogsMatch(string $expectedLog): void
    {
        rewind($this->stream);
        self::assertStringMatchesFormat($expectedLog, fread($this->stream, fstat($this->stream)['size']));
    }

    private function createTestException(): \InvalidArgumentException
    {
        return new \InvalidArgumentException('This is an exception message');
    }
}
