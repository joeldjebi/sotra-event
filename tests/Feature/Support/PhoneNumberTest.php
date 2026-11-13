<?php

use App\Support\PhoneNumber;

it('normalise un numéro ivoirien à 10 chiffres en E.164', function (string $input) {
    expect(PhoneNumber::parse($input)->e164)->toBe('+2250707080839');
})->with([
    '07 07 08 08 39',
    '0707080839',
    '+225 07 07 08 08 39',
    '00225 0707080839',
    '2250707080839',
    '07.07.08.08.39',
]);

it('accepte un numéro international (diaspora)', function () {
    expect(PhoneNumber::parse('+33 6 12 34 56 78')->e164)->toBe('+33612345678');
});

it('refuse un numéro invalide', function (string $input) {
    expect(PhoneNumber::isValid($input))->toBeFalse();
})->with(['', 'abc', '12345', '+0123456789']);
