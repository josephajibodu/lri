<?php

use App\Support\Money;

test('a decimal amount from a provider becomes kobo and back without rounding', function (string $decimal, int $kobo) {
    $money = Money::fromDecimal($decimal, 'NGN');

    expect($money->amount)->toBe($kobo)
        ->and($money->toDecimal())->toBe(number_format($kobo / 100, 2, '.', ''));
})->with([
    'whole naira' => ['10000', 1_000_000],
    'two decimal places' => ['10000.00', 1_000_000],
    'one decimal place' => ['0.5', 50],
    'kobo only' => ['0.07', 7],
    'trailing zeros past the precision' => ['29.9900', 2_999],
]);

test('a decimal amount with more places than the currency allows is rejected', function () {
    Money::fromDecimal('10.005', 'NGN');
})->throws(InvalidArgumentException::class);

test('a value that is not a decimal amount is rejected', function () {
    Money::fromDecimal('10,000', 'NGN');
})->throws(InvalidArgumentException::class);

test('currencies with no minor unit use whole numbers', function () {
    $money = Money::fromDecimal('5000', 'xof');

    expect($money->amount)->toBe(5000)
        ->and($money->currency)->toBe('XOF')
        ->and($money->toDecimal())->toBe('5000');
});

test('amounts read the way the site shows prices', function () {
    expect(Money::of(1_000_000, 'NGN')->format())->toBe('₦10,000')
        ->and(Money::of(1_000_050, 'NGN')->format())->toBe('₦10,000.50');
});

test('a ticket price times the quantity gives the order total', function () {
    $total = Money::of(1_000_000, 'NGN')->times(3);

    expect($total->equals(Money::of(3_000_000, 'NGN')))->toBeTrue()
        ->and($total->equals(Money::of(3_000_000, 'USD')))->toBeFalse();
});
