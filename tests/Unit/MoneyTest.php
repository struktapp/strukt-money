<?php

/** Behavioral coverage for the integer-backed money value object. */

use Strukt\Money;

test('money stores exact minor units and formats major amounts', function (): void {
    $money = Money::create('1234.56');

    expect($money->toMinor())->toBe(123456)
        ->and($money->toFloat())->toEqualWithDelta(1234.56, 0.000001)
        ->and($money->format())->toBe('1,234.56')
        ->and((string) $money)->toBe('USD 1,234.56')
        ->and(Money::create('10.005')->toMinor())->toBe(1001)
        ->and(Money::create('-0.005')->toMinor())->toBe(-1)
        ->and(Money::create(10.25)->toMinor())->toBe(1025);
});

test('money arithmetic stays in integer minor units', function (): void {
    $price = Money::create('19.99');
    $tax = $price->multiply('0.075');

    expect($tax->toMinor())->toBe(150)
        ->and($price->add($tax)->toMinor())->toBe(2149)
        ->and($price->subtract(Money::create('0.99'))->toMinor())->toBe(1900)
        ->and($price->divide(2)->toMinor())->toBe(1000)
        ->and(Money::fromMinor(1025)->toFloat())->toEqualWithDelta(10.25, 0.000001);
});

test('money compares values and preserves signs', function (): void {
    $ten = Money::create('10.00');

    expect($ten->greaterThan(Money::create('9.99')))->toBeTrue()
        ->and($ten->greaterThanOrEqual(Money::create('10.00')))->toBeTrue()
        ->and($ten->lessThan(Money::create('10.01')))->toBeTrue()
        ->and($ten->lessThanOrEqual(Money::create('10.00')))->toBeTrue()
        ->and($ten->equals(Money::create('10')))->toBeTrue()
        ->and($ten->equals(Money::create('10.00', 'EUR')))->toBeFalse()
        ->and(Money::zero()->isZero())->toBeTrue()
        ->and(Money::create('0.01')->isPositive())->toBeTrue()
        ->and(Money::create('-0.01')->isNegative())->toBeTrue()
        ->and(Money::create('-1.25')->abs()->toMinor())->toBe(125)
        ->and(Money::create('1.25')->negate()->toMinor())->toBe(-125);
});

test('money honours currency-specific scales', function (): void {
    expect(Money::create('100', 'JPY')->toMinor())->toBe(100)
        ->and(Money::create('1.234', 'KWD')->toMinor())->toBe(1234)
        ->and(Money::create('1.23456', 'USD', 2)->toMinor())->toBe(123)
        ->and(Money::fromMinor(1000, 'USD')->format())->toBe('10.00');
});

test('money rejects incompatible currencies and invalid input', function (): void {
    expect(fn () => Money::create('1.00')->add(Money::create('1.00', 'EUR')))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => Money::fromMinor(100, 'USD', 2)->subtract(Money::fromMinor(100, 'USD', 3)))
        ->toThrow(InvalidArgumentException::class);

    expect(fn () => Money::create('not-money'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Money::create('1.00', 'US'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Money::create('1.00', 'USD', -1))->toThrow(InvalidArgumentException::class);
    expect(fn () => Money::create(INF))->toThrow(InvalidArgumentException::class);
    expect(fn () => Money::create('1.00')->divide(0))->toThrow(InvalidArgumentException::class);
});
