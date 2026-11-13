<?php

use Illuminate\Support\Facades\DB;

it('active l\'extension PostGIS', function () {
    $version = DB::scalar('SELECT postgis_version()');

    expect($version)->toStartWith('3.');
});

it('calcule une distance géographique en mètres (base du calcul des zones tarifaires RG-06)', function () {
    $meters = DB::scalar(
        'SELECT ST_Distance(ST_MakePoint(0, 0)::geography, ST_MakePoint(1, 0)::geography)'
    );

    expect((float) $meters)->toBeBetween(111_000, 111_400);
});
