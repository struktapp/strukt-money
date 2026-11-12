# Strukt Money

Immutable monetary value object backed by integer minor units. This is the
`strukt4/money` package extracted from `strukt/commons`.

## Install

```bash
composer require strukt/money
```

## Usage

`Strukt\Money` stores amounts as integer minor units, avoiding binary
floating-point drift during repeated arithmetic.

```php
use Strukt\Money;

$price = Money::create('19.99');
$tax = $price->multiply('0.075');

echo $price->add($tax);
// USD 21.49

echo $price->add($tax)->format();
// 21.49
```

Money supports `add()`, `subtract()`, `multiply()`, `divide()`, `abs()`,
`negate()`, comparisons, and currency-aware formatting. Currencies with
non-default minor-unit scales such as `JPY` and `KWD` are supported.

## Representation

- Amounts are stored as `int` minor units.
- `yield()` and `toMinor()` return the exact integer amount.
- `toFloat()` and `format()` are convenience methods for display.
- `create()` accepts major units; `fromMinor()` accepts minor units.
- Currency and scale mismatches are rejected during arithmetic.

```php
$money = Money::create('1234.56');

$money->toMinor();  // 123456
$money->toFloat();  // 1234.56
$money->format();   // "1,234.56"
```

The package has no runtime dependencies beyond PHP 8.2+.

## Development

```bash
composer install
composer test
```

## License

MIT
