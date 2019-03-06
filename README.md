All you need to log with [Bref](https://bref.sh) on AWS Lambda.

[![Build Status](https://img.shields.io/travis/brefphp/logger/master.svg?style=flat-square)](https://travis-ci.org/brefphp/logger)
[![Latest Version](https://img.shields.io/github/release/bref/logger.svg?style=flat-square)](https://packagist.org/packages/bref/logger)

Bref/Logger is a lightweight [PSR-3](https://www.php-fig.org/psr/psr-3/) logger for AWS Lambda. Messages are sent to stderr so that they end up in [CloudWatch](https://bref.sh/docs/environment/logs.html).

## Why?

As explained in [the Bref documentation](https://bref.sh/docs/environment/logs.html), logging in AWS Lambda means logging to stderr. Logs written to stderr are automatically sent to CloudWatch, AWS' solution to collect and view logs.

While classic loggers like [Monolog](https://github.com/Seldaek/monolog) work fine, this logger comes as a simpler and lighter alternative. It does not require any configuration and currently contains a single class.

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

It is possible to log using any [PSR-3 log level](https://www.php-fig.org/psr/psr-3/#5-psrlogloglevel), the most common ones being:

```php
$logger->debug('This is a debug message');
$logger->info('This is an info');
$logger->warning('This is a warning');
$logger->error('This is an error');
```

```
[DEBUG] This is a debug message
[INFO] This is an info
[WARNING] This is a warning
[ERROR] This is an error
```

[PSR-3 placeholders](https://www.php-fig.org/psr/psr-3/#12-message) can be used to insert information into a message without having to concatenate strings manually:

```php
$logger->warning('Invalid login attempt for email {email}', [
    'email' => $email,
]);
// [WARNING] Invalid login attempt for email johndoe@example.com
```

```
[WARNING] Invalid login attempt for email johndoe@example.com
```

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
[ERROR] Impossible to complete the action
InvalidArgumentException: Impossible to complete the action in /var/task/index.php:12
Stack trace:
#0 /var/task/index.php(86): main()
...
```
