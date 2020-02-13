Function Overloading [![Build Status](https://api.travis-ci.org/upscalesoftware/stdlib-overloading.svg?branch=master)](https://travis-ci.org/upscalesoftware/stdlib-overloading)
====================

This library introduces function/method [overloading](https://en.wikipedia.org/wiki/Function_overloading) – varying implementation depending on input arguments.

**Features:**
- Overloading by argument types
- Overloading by number of arguments
- Efficient native type checks of PHP7
- Informative native error messages of [`TypeError`](https://www.php.net/manual/en/class.typeerror.php)
- Lightweight: no OOP, no [Reflection](https://www.php.net/manual/en/book.reflection.php)

## Installation

The library is to be installed via [Composer](https://getcomposer.org/) as a dependency:
```bash
composer require upscale/stdlib-overloading
```

## Usage

### Syntax

Overload a custom function/method:
```php
<?php
declare(strict_types=1);

use function Upscale\Stdlib\Overloading\overload;

function func(...$args)
{
    return overload(
        function (int $num1, int $num2) {
            // ...
        },
        function (string $str1, string $str2) {
            // ...
        }
        // ...
    )(...$args);
}
```

Any number of valid [`callable`](https://www.php.net/callable) implementations can be declared. Order defines evaluation priority.

Call the overloaded function:
```php
func(1, 2);
func('a', 'b');
```   

### Example

Arithmetic calculator that works with both numbers and [Money](https://www.martinfowler.com/eaaCatalog/money.html) objects.

```php
<?php
declare(strict_types=1);

use function Upscale\Stdlib\Overloading\overload;

class Money
{
    private $amount;
    private $currency;

    public function __construct(int $amount, string $currency)
    {
        $this->amount = $amount;
        $this->currency = $currency;
    }

    public function add(Money $sum): Money
    {
        if ($sum->currency != $this->currency) {
            throw new \InvalidArgumentException('Money currency mismatch');
        }
        return new self($this->amount + $sum->amount, $this->currency);
    }
}

class Calculator
{
    public function add(...$args)
    {
        return overload(
            function (int $num1, int $num2): int {
                return $num1 + $num2;
            },
            function (Money $sum1, Money $sum2): Money {
                return $sum1->add($sum2);
            }
        )(...$args);
    }
}


$calc = new Calculator();
$one = new Money(1, 'USD');
$two = new Money(2, 'USD');

print_r($calc->add(1, 2));
// 3

print_r($calc->add($one, $two));
// Money Object([amount:Money:private] => 3 [currency:Money:private] => USD)

print_r($calc->add(1.25, 2));
// TypeError: Argument 1 passed to Calculator::{closure}() must be an instance of Money, float given
```

## Architecture

The overloading mechanism leverages the native type system of PHP7 and relies on declaration of strict type annotations.
It traverses implementation callbacks in the declared order and attempts to invoke each of them with provided arguments.
Result of the first compatible invocation is returned and the subsequent callbacks discarded.

### Limitations

PHP engine allows to pass more runtime arguments to a function/method than accounted for in its signature declaration.
Excess arguments are being silently discarded without triggering any catchable errors, not even [`ArgumentCountError`](https://www.php.net/manual/en/class.argumentcounterror.php).
The solution is to declare more specific longer signatures before less specific ones with matching subset of arguments.

Optional arguments are problematic as they are call-time compatible with a shorter signature of required arguments only.
Consider the following ambiguous declaration that cannot be resolved by reordering: 
```php
overload(
    function (int $num1, int $num2) {
        // ... 
    },
    function (int $num1) {
        // ... 
    },
    function (int $num1, string $str2 = 'default') {
        // Unreachable because preceding declaration matches first and swallows excess arguments
        // ...
    }
)
```

The workaround is to validate the number of arguments to not exceed the declaration:
```php
overload(
    function (int $num1, int $num2) {
        // ... 
    },
    function (int $num1) {
        if (func_num_args() > 1) {
            throw new \ArgumentCountError('Too many arguments provided');
        }
        // ... 
    },
    function (int $num1, string $str2 = 'default') {
        // Reachable with the optional argument passed, but still unreacable without it
        // ... 
    }
)
``` 

## Contributing

Pull Requests with fixes and improvements are welcome!

## License

Copyright © Upscale Software. All rights reserved.

Licensed under the [Apache License, Version 2.0](http://www.apache.org/licenses/LICENSE-2.0).