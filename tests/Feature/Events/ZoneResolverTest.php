<?php

use App\Services\ZoneResolver;
use Clickbar\Magellan\Data\Geometries\Point;

it('attribue la zone selon la distance (RG-06)', function (float $km, int $zone) {
    expect(app(ZoneResolver::class)->zoneFor($km))->toBe($zone);
})->with([
    [0.5, 1], [14.99, 1], [15.0, 2], [20.0, 2], [25.0, 2], [25.01, 3], [60.0, 3],
]);

it('calcule la distance géodésique entre deux points', function () {
    $km = app(ZoneResolver::class)->distanceKm(Point::makeGeodetic(5.0, -4.0), Point::makeGeodetic(5.1, -4.0));

    expect($km)->toEqualWithDelta(11.06, 0.05);
});

it('décrit chaque zone', function () {
    expect(ZoneResolver::zoneLabel(1))->toBe('moins de 15 km')
        ->and(ZoneResolver::zoneLabel(2))->toBe('15 à 25 km')
        ->and(ZoneResolver::zoneLabel(3))->toBe('plus de 25 km');
});
