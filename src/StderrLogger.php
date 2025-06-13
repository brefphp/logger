<?php declare(strict_types=1);

namespace Bref\Logger;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
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

        $message = $this->interpolate($message, $context);

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

        if (!empty($context)) {
            $data['context'] = $context;
        }

        // Format the log entry
        $formattedMessage = sprintf("%s\t%s\t%s\n", 
            strtoupper($level), 
            $displayMessage, 
            $this->toJson($this->normalize($data))
        );

        fwrite($this->stream, $formattedMessage);
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


    /**
     * Normalizes data for JSON serialization.
     *
     * @param mixed $data
     * @param int $depth Current recursion depth
     * @return mixed
     */
    private function normalize($data, int $depth = 0)
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

            if (method_exists($data, '__toString')) {
                return $data->__toString();
            }

            if (get_class($data) === '__PHP_Incomplete_Class') {
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
     */
    private function normalizeException(Throwable $e, int $depth = 0): array
    {
        $maxDepth = 9;

        if ($depth > $maxDepth) {
            return ['class' => get_class($e), 'message' => 'Over ' . $maxDepth . ' levels deep, aborting normalization'];
        }

        $data = [
            'class' => get_class($e),
            'message' => $e->getMessage(),
            'code' => $e->getCode(),
            'file' => $e->getFile() . ':' . $e->getLine(),
        ];

        if ($e->getPrevious() instanceof Throwable) {
            $data['previous'] = $this->normalizeException($e->getPrevious(), $depth + 1);
        }

        return $data;
    }

    /**
     * @param mixed $data
     */
    private function toJson($data): string
    {
        return json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    }
}
