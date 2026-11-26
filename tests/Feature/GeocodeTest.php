<?php

use App\Enums\Role;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

it('cherche un lieu dans le grand Abidjan et met la réponse en cache', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response([
        ['name' => 'Gare Siporex', 'display_name' => 'Gare Siporex, Yopougon, Abidjan, Côte d\'Ivoire', 'lat' => '5.3412345', 'lon' => '-4.0812345'],
        ['display_name' => 'Siporex, Yopougon, Abidjan', 'lat' => '5.34', 'lon' => '-4.08'],
    ])]);
    $this->actingAs(userWithRole(Role::Operator));

    $this->getJson(route('geocode', ['q' => 'Siporex']))
        ->assertOk()
        ->assertJsonPath('data.0', ['name' => 'Gare Siporex', 'label' => 'Gare Siporex, Yopougon, Abidjan, Côte d\'Ivoire', 'lat' => 5.341235, 'lng' => -4.081235])
        ->assertJsonPath('data.1.name', 'Siporex');

    $this->getJson(route('geocode', ['q' => 'siporex']))->assertOk();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request) => $request['countrycodes'] === 'ci' && $request['bounded'] === 1 && $request['q'] === 'Siporex');
});

it('répond 503 quand le service de recherche est indisponible', function () {
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response('', 500)]);
    $this->actingAs(userWithRole(Role::Operator));

    $this->getJson(route('geocode', ['q' => 'Gare de Bassam']))->assertStatus(503);
});

it('réserve la recherche aux utilisateurs connectés et valide la saisie', function () {
    $this->getJson(route('geocode', ['q' => 'Siporex']))->assertUnauthorized();

    $this->actingAs(userWithRole(Role::Operator));
    $this->getJson(route('geocode', ['q' => 'ab']))->assertUnprocessable();
});
