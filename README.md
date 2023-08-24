# zendtech/zendhq-monolog-handler

This project provides a handler for [Monolog](https://seldaek.github.io/monolog/) that pushes to [ZendHQ](https://www.zend.com/products/zendphp-enterprise/zendhq) monitoring.

## Installation

```bash
composer require zendtech/zendhq-monolog-handler
```

### Requirements

- A ZendHQ node
- ZendPHP >= 7.2
- The ZendHQ extension
- Monolog 2.4+ or 3.4+

## Usage

The below examples demonstrate how to create a `ZendHQHandler` instance for use in writing logs with Monolog.

### Default instantiation

Default usage is to use Monolog/PSR-3 log levels to indicate severity.
You can instantiate the provided `ZendTech\ZendHQ\MonologHandler\ZendHQHandler` class without any arguments, or with the `$level` and/or `$bubble` arguments:

```php
use Monolog\Logger;
use ZendTech\ZendHQ\MonologHandler\ZendHQHandler;

// PHP 7:
// - Default level (DEBUG) and allowing bubbling:
$handler = new ZendHQHandler();

// - Setting a level mask of warnings or greater only:
$handler = new ZendHQHandler(null, Logger::WARNING);

// - Default level (DEBUG), but disallowing bubbling
$handler = new ZendHQHandler(null, Logger::DEBUG, false);

// PHP 8:
// - Default level (DEBUG) and allowing bubbling:
$handler = new ZendHQHandler();

// - Setting a level mask of warnings or greater only:
$handler = new ZendHQHandler(level: Logger::WARNING);

// - Default level (DEBUG), but disallowing bubbling
$handler = new ZendHQHandler(bubble: false);
```

### Instantiation for usage with named rules

ZendHQ custom monitoring rules will specify severity in the rule definition, so severity is ignored.
To use such custom rules, provide the custom rule name when instantiating `ZendHQHandler`.
The following examples target a "my_custom_rule" rule.
While you _can_ provide a default level to handle, the value will not be sent to ZendHQ, and only used to determine if a message will get logged.

```php
use Monolog\Logger;
use ZendTech\ZendHQ\MonologHandler\ZendHQHandler;

// PHP 7:
// - Default level (DEBUG) and allowing bubbling:
$handler = new ZendHQHandler('my_custom_rule');

// - Setting a level mask of warnings or greater only:
$handler = new ZendHQHandler('my_custom_rule', Logger::WARNING);

// - Default level (DEBUG), but disallowing bubbling
$handler = new ZendHQHandler('my_custom_rule', Logger::DEBUG, false);

// PHP 8:
// - Default level (DEBUG) and allowing bubbling:
$handler = new ZendHQHandler('my_custom_rule');

// - Setting a level mask of warnings or greater only:
$handler = new ZendHQHandler('my_custom_rule', level: Logger::WARNING);

// - Default level (DEBUG), but disallowing bubbling
$handler = new ZendHQHandler('my_custom_rule', bubble: false);
```

### Formatters and Processors

The `ZendHQHandler` implements each of `Monolog\Handler\ProcessableHandlerInterface` and `Monolog\Handler\FormattableHandlerInterface`.
As such, you can attach processors and formatters to your handler in order to manipulate the information logged.
See the [Monolog documentation on formatters and processors](http://seldaek.github.io/monolog/doc/02-handlers-formatters-processors.html) for more details.

As examples:

```php
$handler->setFormatter(new \Monolog\Formatter\LineFormatter());
$handler->pushProcessor(new \Monolog\Processor\PsrLogMessageProcessor());
```

### Adding the handler to Monolog

Monolog writes to _channels_, which are essentially just a way of partitioning different logs from each other.

```php
use Monolog\Logger;

$logger = new Logger('channel_name');
```

From here, you need to add the handler to the logger:

```php
// Where $handler is the instance created via one of the examples in previous sections
$logger->pushHandler($handler);
```

To log, use one of the various logging methods of the `$logger` instance:

```php
$logger->warning('This is a warning!');
```

## Notes

- The channel name is sent to ZendHQ monitoring events as the _type_; you will see this in the event drawer.
