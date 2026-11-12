<?php

namespace Strukt;

use InvalidArgumentException;

/**
 * Immutable monetary value object stored in integer minor units.
 *
 * Money keeps amounts as whole cents (or the currency's smallest unit), so
 * repeated addition and subtraction never accumulates binary floating-point
 * drift. Integer minor units give money stronger precision guarantees than a
 * float wrapper.
 */
final class Money
{
    /** Currency used when none is supplied. */
    public const DEFAULT_CURRENCY = 'USD';

    /** Default number of decimal places for currencies without an override. */
    private const DEFAULT_SCALE = 2;

    /** @var array<string, int> Currencies whose minor unit is not two digits. */
    private const SCALES = [
        'BHD' => 3,
        'CLF' => 4,
        'JPY' => 0,
        'JOD' => 3,
        'KWD' => 3,
        'OMR' => 3,
        'TND' => 3,
    ];

    /** Amount in minor units. */
    private int $value;

    /** ISO-4217 currency code. */
    private string $currency;

    /** Number of decimal places in one major unit. */
    private int $scale;

    /**
     * Wraps an amount expressed in major units.
     *
     * @param mixed $amount Amount in major units.
     * @param string $currency ISO-4217 currency code.
     * @param int|null $scale Optional minor-unit scale override.
     * @throws InvalidArgumentException When the amount, currency, or scale is invalid.
     */
    public function __construct(mixed $amount = 0, string $currency = self::DEFAULT_CURRENCY, ?int $scale = null)
    {
        $this->currency = self::normalizeCurrency($currency);
        $this->scale = self::normalizeScale($scale ?? self::scaleFor($this->currency));
        $this->value = self::majorToMinor($amount, $this->scale);
    }

    /**
     * Creates money from an amount in major units.
     *
     * @param mixed $amount Amount in major units.
     * @param string $currency ISO-4217 currency code.
     * @param int|null $scale Optional minor-unit scale override.
     * @return static New money value.
     */
    public static function create(
        mixed $amount = 0,
        string $currency = self::DEFAULT_CURRENCY,
        ?int $scale = null,
    ): static {
        return new static($amount, $currency, $scale);
    }

    /**
     * Creates money from an amount already expressed in minor units.
     *
     * @param int $minor Amount in minor units.
     * @param string $currency ISO-4217 currency code.
     * @param int|null $scale Optional minor-unit scale override.
     * @return static New money value.
     */
    public static function fromMinor(
        int $minor,
        string $currency = self::DEFAULT_CURRENCY,
        ?int $scale = null,
    ): static {
        $money = new static(0, $currency, $scale);
        $money->value = $minor;

        return $money;
    }

    /**
     * Creates zero money in the requested currency.
     *
     * @param string $currency ISO-4217 currency code.
     * @param int|null $scale Optional minor-unit scale override.
     * @return static Zero money.
     */
    public static function zero(
        string $currency = self::DEFAULT_CURRENCY,
        ?int $scale = null,
    ): static {
        return new static(0, $currency, $scale);
    }

    /**
     * Returns an independent copy.
     *
     * @return static Cloned money value.
     */
    public function clone(): static
    {
        return clone $this;
    }

    /**
     * Returns the amount in minor units.
     *
     * @return int Amount in minor units.
     */
    public function yield(): int
    {
        return $this->value;
    }

    /**
     * Returns the amount in minor units.
     *
     * @return int Amount in minor units.
     */
    public function toMinor(): int
    {
        return $this->value;
    }

    /**
     * Returns the amount in major units.
     *
     * @return float Amount in major units.
     */
    public function toFloat(): float
    {
        return $this->value / self::factor($this->scale);
    }

    /**
     * Returns the ISO-4217 currency code.
     *
     * @return string Currency code.
     */
    public function currency(): string
    {
        return $this->currency;
    }

    /**
     * Returns the number of minor-unit decimal places.
     *
     * @return int Currency scale.
     */
    public function scale(): int
    {
        return $this->scale;
    }

    /**
     * Adds another money value.
     *
     * @param Money $money Amount to add.
     * @return static New money value.
     * @throws InvalidArgumentException When currencies or scales differ.
     */
    public function add(Money $money): static
    {
        $this->guard($money);

        return self::fromMinor($this->value + $money->value, $this->currency, $this->scale);
    }

    /**
     * Subtracts another money value.
     *
     * @param Money $money Amount to subtract.
     * @return static New money value.
     * @throws InvalidArgumentException When currencies or scales differ.
     */
    public function subtract(Money $money): static
    {
        $this->guard($money);

        return self::fromMinor($this->value - $money->value, $this->currency, $this->scale);
    }

    /**
     * Multiplies the amount by a scalar factor.
     *
     * @param int|float|string $factor Multiplier.
     * @return static New money value rounded to the currency scale.
     * @throws InvalidArgumentException When the factor is not numeric.
     */
    public function multiply(int|float|string $factor): static
    {
        if (!is_numeric($factor)) {
            throw new InvalidArgumentException('Money factors must be numeric.');
        }

        return self::fromMinor(
            (int) round($this->value * (float) $factor, 0, PHP_ROUND_HALF_UP),
            $this->currency,
            $this->scale,
        );
    }

    /**
     * Divides the amount by a scalar divisor.
     *
     * @param int|float|string $divisor Divisor.
     * @return static New money value rounded to the currency scale.
     * @throws InvalidArgumentException When the divisor is not numeric or is zero.
     */
    public function divide(int|float|string $divisor): static
    {
        if (!is_numeric($divisor)) {
            throw new InvalidArgumentException('Money divisors must be numeric.');
        }

        if ((float) $divisor === 0.0) {
            throw new InvalidArgumentException('Money cannot be divided by zero.');
        }

        return self::fromMinor(
            (int) round($this->value / (float) $divisor, 0, PHP_ROUND_HALF_UP),
            $this->currency,
            $this->scale,
        );
    }

    /**
     * Returns the absolute amount.
     *
     * @return static New non-negative money value.
     */
    public function abs(): static
    {
        return self::fromMinor(abs($this->value), $this->currency, $this->scale);
    }

    /**
     * Returns the amount with its sign reversed.
     *
     * @return static New money value.
     */
    public function negate(): static
    {
        return self::fromMinor(-$this->value, $this->currency, $this->scale);
    }

    /**
     * Reports whether the amount is exactly zero.
     *
     * @return bool True when the amount is zero.
     */
    public function isZero(): bool
    {
        return $this->value === 0;
    }

    /**
     * Reports whether the amount is greater than zero.
     *
     * @return bool True when the amount is positive.
     */
    public function isPositive(): bool
    {
        return $this->value > 0;
    }

    /**
     * Reports whether the amount is less than zero.
     *
     * @return bool True when the amount is negative.
     */
    public function isNegative(): bool
    {
        return $this->value < 0;
    }

    /**
     * Compares this amount with another money value.
     *
     * @param Money $money Value to compare against.
     * @return int Negative, zero, or positive when this amount is smaller,
     *             equal, or larger.
     * @throws InvalidArgumentException When currencies or scales differ.
     */
    public function compare(Money $money): int
    {
        $this->guard($money);

        return $this->value <=> $money->value;
    }

    /**
     * Compares money by amount and currency.
     *
     * @param mixed $value Money value to compare against.
     * @return bool True when both amounts and currencies match.
     */
    public function equals(mixed $value): bool
    {
        return $value instanceof self
            && $this->currency === $value->currency
            && $this->scale === $value->scale
            && $this->value === $value->value;
    }

    /**
     * Reports whether this amount is greater than another money value.
     *
     * @param Money $money Value to compare against.
     * @return bool True when this amount is greater.
     */
    public function greaterThan(Money $money): bool
    {
        return $this->compare($money) > 0;
    }

    /**
     * Reports whether this amount is greater than or equal to another value.
     *
     * @param Money $money Value to compare against.
     * @return bool True when this amount is greater than or equal.
     */
    public function greaterThanOrEqual(Money $money): bool
    {
        return $this->compare($money) >= 0;
    }

    /**
     * Reports whether this amount is less than another money value.
     *
     * @param Money $money Value to compare against.
     * @return bool True when this amount is smaller.
     */
    public function lessThan(Money $money): bool
    {
        return $this->compare($money) < 0;
    }

    /**
     * Reports whether this amount is less than or equal to another value.
     *
     * @param Money $money Value to compare against.
     * @return bool True when this amount is less than or equal.
     */
    public function lessThanOrEqual(Money $money): bool
    {
        return $this->compare($money) <= 0;
    }

    /**
     * Formats the amount without a currency symbol.
     *
     * @param int|null $decimals Decimal places, defaulting to the currency scale.
     * @param string $decimalSeparator Decimal separator.
     * @param string $thousandsSeparator Thousands separator.
     * @return string Formatted amount.
     */
    public function format(
        ?int $decimals = null,
        string $decimalSeparator = '.',
        string $thousandsSeparator = ',',
    ): string {
        $decimals ??= $this->scale;

        return number_format(
            $this->toFloat(),
            $decimals,
            $decimalSeparator,
            $thousandsSeparator,
        );
    }

    /**
     * Formats the amount with its currency code.
     *
     * @return string Currency code followed by the formatted amount.
     */
    public function __toString(): string
    {
        return sprintf('%s %s', $this->currency, $this->format());
    }

    /**
     * Ensures two money values can be combined.
     *
     * @param Money $money Value to validate.
     * @return void
     * @throws InvalidArgumentException When currencies or scales differ.
     */
    private function guard(Money $money): void
    {
        if ($this->currency !== $money->currency) {
            throw new InvalidArgumentException(sprintf(
                'Cannot combine money in [%s] with money in [%s].',
                $this->currency,
                $money->currency,
            ));
        }

        if ($this->scale !== $money->scale) {
            throw new InvalidArgumentException(sprintf(
                'Cannot combine [%s] money with different scales.',
                $this->currency,
            ));
        }
    }

    /**
     * Converts a major-unit amount to minor units.
     *
     * @param mixed $amount Amount in major units.
     * @param int $scale Currency scale.
     * @return int Amount in minor units.
     * @throws InvalidArgumentException When the amount is not numeric.
     */
    private static function majorToMinor(mixed $amount, int $scale): int
    {
        if (is_int($amount)) {
            return $amount * self::factor($scale);
        }

        if (is_float($amount)) {
            if (!is_finite($amount)) {
                throw new InvalidArgumentException('Money amounts must be finite.');
            }

            return (int) round($amount * self::factor($scale), 0, PHP_ROUND_HALF_UP);
        }

        if (is_string($amount)) {
            return self::parseMinor($amount, $scale);
        }

        throw new InvalidArgumentException('Money amounts must be integers, floats, or numeric strings.');
    }

    /**
     * Parses a decimal string into minor units without using binary floats.
     *
     * @param string $amount Decimal amount.
     * @param int $scale Currency scale.
     * @return int Amount in minor units.
     * @throws InvalidArgumentException When the string is not a decimal amount.
     */
    private static function parseMinor(string $amount, int $scale): int
    {
        $amount = trim($amount);
        if (!preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $amount, $matches)) {
            throw new InvalidArgumentException('Money amounts must be decimal numbers.');
        }

        $sign = ($matches[1] ?? '') === '-' ? -1 : 1;
        $whole = (int) ($matches[2] ?? '0');
        $fraction = str_pad((string) ($matches[3] ?? ''), $scale + 1, '0');
        $kept = (int) substr($fraction, 0, $scale);
        $roundUp = (int) substr($fraction, $scale, 1) >= 5;
        $minor = $whole * self::factor($scale) + $kept + ($roundUp ? 1 : 0);

        return $sign * $minor;
    }

    /**
     * Normalizes and validates a currency code.
     *
     * @param string $currency Currency code.
     * @return string Uppercase ISO-4217 code.
     * @throws InvalidArgumentException When the code is not three letters.
     */
    private static function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));
        if (!preg_match('/^[A-Z]{3}$/', $currency)) {
            throw new InvalidArgumentException('Money currency must be a three-letter ISO-4217 code.');
        }

        return $currency;
    }

    /**
     * Normalizes and validates a currency scale.
     *
     * @param int $scale Currency scale.
     * @return int Valid scale.
     * @throws InvalidArgumentException When the scale is outside 0 through 6.
     */
    private static function normalizeScale(int $scale): int
    {
        if ($scale < 0 || $scale > 6) {
            throw new InvalidArgumentException('Money scale must be between 0 and 6.');
        }

        return $scale;
    }

    /**
     * Returns the default scale for a currency.
     *
     * @param string $currency Currency code.
     * @return int Currency scale.
     */
    private static function scaleFor(string $currency): int
    {
        return self::SCALES[$currency] ?? self::DEFAULT_SCALE;
    }

    /**
     * Raises ten to the requested scale.
     *
     * @param int $scale Currency scale.
     * @return int Power of ten.
     */
    private static function factor(int $scale): int
    {
        return 10 ** $scale;
    }
}
