<?php

use App\Support\Money;

it('formats kobo as naira', function (int $kobo, string $expected) {
    expect(Money::format($kobo))->toBe($expected);
})->with([
    [0, '₦0'],
    [2_500_000, '₦25,000'],
    [2_500_050, '₦25,000.50'],
    [-150_000, '-₦1,500'],
]);

it('parses what people type into kobo', function (string $input, ?int $expected) {
    expect(Money::toKobo($input))->toBe($expected);
})->with([
    ['25000', 2_500_000],
    ['25,000', 2_500_000],
    ['₦ 25,000.5', 2_500_050],
    ['25000.05', 2_500_005],
    ['0', 0],
    ['-5', null],
    ['abc', null],
    ['1.234', null],
    ['', null],
]);

it('writes kobo back as a form value', function () {
    expect(Money::toNaira(2_500_000))->toBe('25000')
        ->and(Money::toNaira(2_500_050))->toBe('25000.50')
        ->and(Money::toNaira(null))->toBe('');
});
