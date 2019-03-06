<?php declare(strict_types=1);

namespace Bref\Logger\Test;

use Bref\Logger\StderrLogger;
use PHPUnit\Framework\TestCase;

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
        $this->logger = new StderrLogger($this->stream);
    }

    public function test log messages to stderr()
    {
        $this->logger->debug('Debug');
        $this->logger->info('Info');
        $this->logger->notice('Notice');
        $this->logger->alert('Alert');
        $this->logger->error('Error');
        $this->logger->critical('Critical');
        $this->logger->emergency('Emergency');

        $this->assertLogs(<<<'LOGS'
[DEBUG] Debug
[INFO] Info
[NOTICE] Notice
[ALERT] Alert
[ERROR] Error
[CRITICAL] Critical
[EMERGENCY] Emergency

LOGS
);
    }

    /**
     * @param mixed $contextValue
     *
     * @dataProvider provideInterpolationExamples
     */
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

    public function provideInterpolationExamples(): array
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
        $this->assertLogsStartWith(<<<LOGS
[INFO] Exception
InvalidArgumentException: This is an exception message in $currentFile:113
Stack trace:
#0 $currentFile(86): Bref\Logger\Test\StderrLoggerTest->createTestException()
LOGS
        );
    }

    private function assertLogs(string $expectedLog): void
    {
        rewind($this->stream);
        self::assertSame($expectedLog, fread($this->stream, fstat($this->stream)['size']));
    }

    private function assertLogsStartWith(string $expectedLog): void
    {
        rewind($this->stream);
        self::assertStringStartsWith($expectedLog, fread($this->stream, fstat($this->stream)['size']));
    }

    private function createTestException(): \InvalidArgumentException
    {
        return new \InvalidArgumentException('This is an exception message');
    }
}
