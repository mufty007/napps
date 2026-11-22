<?php

use App\Support\PhoneNumber;

it('normalises the ways people type Nigerian numbers', function (string $input) {
    expect(PhoneNumber::normalize($input))->toBe('+2348031234567');
})->with([
    '08031234567',
    '0803 123 4567',
    '0803-123-4567',
    '8031234567',
    '2348031234567',
    '+234 803 123 4567',
    '+234 (0)803 123 4567' => ['+234 803 123 4567'],
]);

it('rejects numbers that are not Nigerian', function (?string $input) {
    expect(PhoneNumber::normalize($input))->toBeNull();
})->with([
    null,
    '',
    '12345',
    '+447911123456',
    '0803123456',
    '080312345678',
    '0003123456',
]);

it('formats and masks for display', function () {
    expect(PhoneNumber::format('+2348031234567'))->toBe('+234 803 123 4567')
        ->and(PhoneNumber::mask('+2348031234567'))->toBe('+234 803 *** 4567');
});
