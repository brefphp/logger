All you need to log with [Bref](https://bref.sh) on AWS Lambda.

Bref/Logger is a lightweight [PSR-3](https://www.php-fig.org/psr/psr-3/) logger for AWS Lambda. Messages are sent to `stderr` so that they end up in [CloudWatch](https://bref.sh/docs/environment/logs.html).

## Why?

As explained in [the Bref documentation](https://bref.sh/docs/environment/logs.html), logging in AWS Lambda means logging to `stderr`. Logs written to `stderr` are automatically sent to [CloudWatch](https://aws.amazon.com/cloudwatch/), AWS' solution to collect and view logs.

While classic loggers like [Monolog](https://github.com/Seldaek/monolog) work fine, this logger comes as a simpler and lighter alternative optimized for AWS Lambda. It does not require any configuration and currently contains a single class.

Since it is [PSR-3](https://www.php-fig.org/psr/psr-3/) compliant, Bref/Logger is also compatible with any framework or library consuming a PSR-3 logger.

## Installation

```
composer require bref/logger
```

## Usage

The logger does not require any configuration:

```php
$logger = new \Bref\Logger\StderrLogger();
```

By default, messages **above the `info` level** will be logged, the rest will be discarded.

It is possible to log using any [PSR-3 log level](https://www.php-fig.org/psr/psr-3/#5-psrlogloglevel), the most common ones being:

```php
$logger->debug('This is a debug message');
$logger->info('This is an info');
$logger->warning('This is a warning');
$logger->error('This is an error');
```

```
INFO	This is an info	{"message":"This is an info","level":"INFO"}
WARNING	This is a warning	{"message":"This is a warning","level":"WARNING"}
ERROR	This is an error	{"message":"This is an error","level":"ERROR"}
```

Messages under `info` are not logged.

### Message placeholders

[PSR-3 placeholders](https://www.php-fig.org/psr/psr-3/#12-message) can be used to insert information from the `$context` array into the message without having to concatenate strings manually:

```php
$logger->warning('Invalid login attempt for email {email}', [
    'email' => $email,
]);
```

```
WARNING	Invalid login attempt for email johndoe@example.com	{"message":"Invalid login attempt for email johndoe@example.com","level":"WARNING","context":{"email":"johndoe@example.com"}}
```

### Logging exceptions

Exceptions [can be logged](https://www.php-fig.org/psr/psr-3/#13-context) under the `exception` key:

```php
try {
   // ...
} catch (\Exception $e) {
    $logger->error('Impossible to complete the action', [
        'exception' => $e,
    ]);
}
```

```
ERROR	Impossible to complete the action	{"message":"Impossible to complete the action","level":"ERROR","exception":{"class":"InvalidArgumentException","message":"Impossible to complete the action","code":0,"file":"/var/task/index.php","line":12,"trace":[{"file":"/var/task/index.php","line":86,"function":"main"}]}
```

### Log level

It is possible to change the level above which messages are logged.

For example to log all messages:

```php
$logger = new \Bref\Logger\StderrLogger(\Psr\Log\LogLevel::DEBUG);
```
