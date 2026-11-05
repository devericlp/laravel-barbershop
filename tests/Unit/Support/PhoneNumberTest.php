<?php

use App\Support\PhoneNumber;

test('normalizes to digits and drops the country code', function (string $input) {
    expect(PhoneNumber::normalize($input))->toBe('11912345678');
})->with(['(11) 91234-5678', '11 91234 5678', '+55 (11) 91234-5678', '5511912345678']);

test('accepts only brazilian mobile numbers with area code', function (string $input, bool $valid) {
    expect(PhoneNumber::isValidMobile($input))->toBe($valid);
})->with([
    ['(11) 91234-5678', true],
    ['(11) 3123-4567', false],
    ['91234-5678', false],
    ['(01) 91234-5678', false],
]);

test('formats a normalized mobile number', function () {
    expect(PhoneNumber::format('11912345678'))->toBe('(11) 91234-5678');
});
