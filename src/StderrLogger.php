<?php declare(strict_types=1);

namespace Bref\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

/**
 * PSR-3 logger that logs into stderr.
 */
class StderrLogger extends AbstractLogger
{
    private const LOG_LEVEL_MAP = [
        LogLevel::EMERGENCY => 8,
        LogLevel::ALERT => 7,
        LogLevel::CRITICAL => 6,
        LogLevel::ERROR => 5,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 3,
        LogLevel::INFO => 2,
        LogLevel::DEBUG => 1,
    ];

    /** @var string */
    private $logLevel;

    /** @var string|null */
    private $url;

    /** @var resource|null */
    private $stream;

    /**
     * @param string $logLevel The log level above which messages will be logged. Messages under this log level will be ignored.
     * @param resource|string $stream If unsure leave the default value.
     */
    public function __construct(string $logLevel = LogLevel::WARNING, $stream = 'php://stderr')
    {
        $this->logLevel = $logLevel;

        if (is_resource($stream)) {
            $this->stream = $stream;
        } elseif (is_string($stream)) {
            $this->url = $stream;
        } else {
            throw new \InvalidArgumentException('A stream must either be a resource or a string.');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = []): void
    {
        if (self::LOG_LEVEL_MAP[$level] < self::LOG_LEVEL_MAP[$this->logLevel]) {
            return;
        }

        $this->openStderr();

        if (is_string($message)) {
            $message = $this->interpolate($message, $context);
        } else {
            $message = json_encode($message);
        }

        $message = sprintf("[%s] %s\n", strtoupper($level), $message);

        fwrite($this->stream, $message);

        /**
         * If an Exception object is passed in the context data, it MUST be in the 'exception' key.
         * Logging exceptions is a common pattern and this allows implementors to extract a stack trace
         * from the exception when the log backend supports it. Implementors MUST still verify that
         * the 'exception' key is actually an Exception before using it as such, as it MAY contain anything.
         */
        if (isset($context['exception']) && $context['exception'] instanceof \Throwable) {
            $this->logException($context['exception']);
        }
    }

    private function openStderr(): void
    {
        if ($this->stream !== null) {
            return;
        }
        $this->stream = fopen($this->url, 'a');
        if (! $this->stream) {
            throw new \RuntimeException('Unable to open stream ' . $this->url);
        }
    }

    /**
     * Interpolates context values into the message placeholders.
     */
    private function interpolate(string $message, array $context): string
    {
        if (strpos($message, '{') === false) {
            return $message;
        }

        $replacements = [];
        foreach ($context as $key => $val) {
            if ($val === null || is_scalar($val) || (\is_object($val) && method_exists($val, '__toString'))) {
                $replacements["{{$key}}"] = $val;
            } elseif ($val instanceof \DateTimeInterface) {
                $replacements["{{$key}}"] = $val->format(\DateTime::RFC3339);
            } elseif (\is_object($val)) {
                $replacements["{{$key}}"] = '{object ' . \get_class($val) . '}';
            } elseif (\is_resource($val)) {
                $replacements["{{$key}}"] = '{resource}';
            } else {
                $replacements["{{$key}}"] = json_encode($val);
            }
        }

        return strtr($message, $replacements);
    }

    private function logException(\Throwable $exception): void
    {
        fwrite($this->stream, sprintf(
            "%s: %s in %s:%d\nStack trace:\n%s\n",
            get_class($exception),
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        ));
    }
}
