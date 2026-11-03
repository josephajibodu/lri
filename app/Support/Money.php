<?php

namespace App\Support;

use Illuminate\Support\Number;
use InvalidArgumentException;

/**
 * An amount of money in a currency's smallest unit (kobo for naira), so sums never pick up rounding errors.
 * Providers that take decimal strings get one at the edge through toDecimal().
 */
final readonly class Money
{
    /**
     * Currencies with no minor unit, so their amounts are whole numbers.
     *
     * @var list<string>
     */
    private const ZeroDecimalCurrencies = ['RWF', 'UGX', 'XAF', 'XOF'];

    public string $currency;

    public function __construct(public int $amount, string $currency)
    {
        if (! preg_match('/^[A-Za-z]{3}$/', $currency)) {
            throw new InvalidArgumentException("[{$currency}] is not a three-letter currency code.");
        }

        $this->currency = strtoupper($currency);
    }

    /**
     * Money from an amount in the currency's smallest unit, such as 1000000 kobo for ₦10,000.
     */
    public static function of(int $amount, string $currency): self
    {
        return new self($amount, $currency);
    }

    /**
     * Money from a decimal string such as "10000.00", the way providers send amounts.
     */
    public static function fromDecimal(string $amount, string $currency): self
    {
        $decimals = self::decimalsFor($currency);

        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', trim($amount), $parts)) {
            throw new InvalidArgumentException("[{$amount}] is not a decimal amount.");
        }

        [, $sign, $whole, $fraction] = $parts + [3 => ''];
        $fraction = rtrim($fraction, '0');

        if (strlen($fraction) > $decimals) {
            throw new InvalidArgumentException("[{$amount}] has more decimal places than {$currency} allows.");
        }

        $minor = (int) ($whole.str_pad($fraction, $decimals, '0'));

        return new self($sign === '-' ? -$minor : $minor, $currency);
    }

    /**
     * The number of decimal places the currency uses.
     */
    public static function decimalsFor(string $currency): int
    {
        return in_array(strtoupper($currency), self::ZeroDecimalCurrencies, true) ? 0 : 2;
    }

    /**
     * The amount as a decimal string at the currency's precision, such as "10000.00".
     */
    public function toDecimal(): string
    {
        $decimals = self::decimalsFor($this->currency);
        $digits = str_pad((string) abs($this->amount), $decimals + 1, '0', STR_PAD_LEFT);
        $sign = $this->amount < 0 ? '-' : '';

        if ($decimals === 0) {
            return $sign.$digits;
        }

        return $sign.substr($digits, 0, -$decimals).'.'.substr($digits, -$decimals);
    }

    /**
     * The amount for people to read, such as "₦10,000", showing kobo only when there are some.
     */
    public function format(): string
    {
        $decimals = self::decimalsFor($this->currency);
        $value = $this->amount / (10 ** $decimals);
        $precision = $this->amount % (10 ** $decimals) === 0 ? 0 : $decimals;

        return Number::currency($value, in: $this->currency, locale: 'en_NG', precision: $precision);
    }

    /**
     * This amount multiplied by a whole number, such as a ticket price by the quantity.
     */
    public function times(int $multiplier): self
    {
        return new self($this->amount * $multiplier, $this->currency);
    }

    /**
     * Whether both amounts are the same sum in the same currency.
     */
    public function equals(self $other): bool
    {
        return $this->amount === $other->amount && $this->currency === $other->currency;
    }
}
