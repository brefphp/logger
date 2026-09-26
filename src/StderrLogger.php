<?php declare(strict_types=1);

namespace Bref\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\InvalidArgumentException;
use Psr\Log\LogLevel;
use Stringable;
use Throwable;

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

    /** @var resource|string The stream, or its URL until it is opened */
    private $stream;

    /**
     * @param string $logLevel The log level above which messages will be logged. Messages under this log level will be ignored.
     * @param resource|string $stream If unsure leave the default value.
     */
    public function __construct(
        private string $logLevel = LogLevel::INFO,
        $stream = 'php://stderr',
    ) {
        if (! is_resource($stream) && ! is_string($stream)) {
            throw new \InvalidArgumentException('A stream must either be a resource or a string.');
        }
        $this->stream = $stream;
    }

    /**
     * @param mixed $level
     * @param string|Stringable $message
     * @param array<mixed> $context
     * @throws InvalidArgumentException If the log level is not one of the PSR-3 levels.
     */
    public function log($level, $message, array $context = []): void
    {
        if (! is_string($level) || ! isset(self::LOG_LEVEL_MAP[$level])) {
            throw new InvalidArgumentException('Unsupported log level: ' . var_export($level, true));
        }
        if (self::LOG_LEVEL_MAP[$level] < self::LOG_LEVEL_MAP[$this->logLevel]) {
            return;
        }

        $message = $this->interpolate((string) $message, $context);

        // Make sure everything is kept on one line to count as one record
        $displayMessage = str_replace(["\r\n", "\r", "\n"], ' ', $message);

        // Prepare data for JSON
        $data = [
            'message' => $message,
            'level' => strtoupper($level),
        ];

        // Move any exception to the root
        if (isset($context['exception']) && $context['exception'] instanceof Throwable) {
            $data['exception'] = $context['exception'];
            unset($context['exception']);
        }

        if (! empty($context)) {
            $data['context'] = $context;
        }

        $formattedMessage = sprintf("%s\t%s\t%s\n", strtoupper($level), $displayMessage, $this->toJson($this->normalize($data)));

        // Bref sets the ID of the current Lambda invocation. Lambda's own runtimes start their lines with it:
        // CloudWatch Logs Insights reads it as `@requestId`, like in Lambda's START, END and REPORT lines.
        $requestId = $_SERVER['LAMBDA_REQUEST_ID'] ?? null;
        if (is_string($requestId) && $requestId !== '') {
            $formattedMessage = "$requestId\t$formattedMessage";
        }

        fwrite($this->stream(), $formattedMessage);
    }

    /**
     * @return resource
     */
    private function stream()
    {
        if (is_string($this->stream)) {
            $stream = fopen($this->stream, 'a');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open stream ' . $this->stream);
            }
            $this->stream = $stream;
        }

        return $this->stream;
    }

    /**
     * Interpolates context values into the message placeholders.
     *
     * @param array<mixed> $context
     */
    private function interpolate(string $message, array $context): string
    {
        if (! str_contains($message, '{')) {
            return $message;
        }

        $replacements = [];
        foreach ($context as $key => $val) {
            if ($val === null || is_scalar($val) || $val instanceof Stringable) {
                $replacements["{{$key}}"] = (string) $val;
            } elseif ($val instanceof \DateTimeInterface) {
                $replacements["{{$key}}"] = $val->format(\DateTime::RFC3339);
            } elseif (\is_object($val)) {
                $replacements["{{$key}}"] = '{object ' . $val::class . '}';
            } elseif (\is_resource($val)) {
                $replacements["{{$key}}"] = '{resource}';
            } else {
                $replacements["{{$key}}"] = (string) json_encode($val);
            }
        }

        return strtr($message, $replacements);
    }

    /**
     * Normalizes data for JSON serialization.
     *
     * @param int $depth Current recursion depth
     */
    private function normalize(mixed $data, int $depth = 0): mixed
    {
        $maxDepth = 9; // Similar to NormalizerFormatter's default
        $maxItems = 1000; // Similar to NormalizerFormatter's default

        if ($depth > $maxDepth) {
            return 'Over ' . $maxDepth . ' levels deep, aborting normalization';
        }

        if (is_array($data)) {
            $normalized = [];

            $count = 1;
            foreach ($data as $key => $value) {
                if ($count++ > $maxItems) {
                    $normalized['...'] = 'Over ' . $maxItems . ' items (' . count($data) . ' total), aborting normalization';
                    break;
                }

                $normalized[$key] = $this->normalize($value, $depth + 1);
            }

            return $normalized;
        }

        if (is_object($data)) {
            if ($data instanceof \DateTimeInterface) {
                return $data->format(\DateTime::RFC3339);
            }

            if ($data instanceof Throwable) {
                return $this->normalizeException($data, $depth);
            }

            if ($data instanceof \JsonSerializable) {
                return $data;
            }

            if ($data instanceof Stringable) {
                return $data->__toString();
            }

            if ($data instanceof \__PHP_Incomplete_Class) {
                return new \ArrayObject($data);
            }

            return $data;
        }

        if (is_resource($data)) {
            return '{resource}';
        }

        return $data;
    }

    /**
     * Normalizes an exception for JSON serialization.
     *
     * @return array<string, mixed>
     */
    private function normalizeException(Throwable $e, int $depth = 0): array
    {
        $maxDepth = 9;

        if ($depth > $maxDepth) {
            return ['class' => $e::class, 'message' => 'Over ' . $maxDepth . ' levels deep, aborting normalization'];
        }

        $data = [
            'class' => $e::class,
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile() . ':' . $e->getLine(),
        ];

        if ($e->getPrevious() instanceof Throwable) {
            $data['previous'] = $this->normalizeException($e->getPrevious(), $depth + 1);
        }

        return $data;
    }

    private function toJson(mixed $data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }
}
