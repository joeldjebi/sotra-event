<?php

use App\Enums\Role;

it('enregistre le thème choisi et l\'applique au chargement des pages', function (string $theme) {
    $user = userWithRole(Role::SuperAdmin);

    $this->actingAs($user)->putJson(route('preferences.theme'), ['theme' => $theme])->assertOk()->assertJsonPath('theme', $theme);

    expect($user->fresh()->theme)->toBe($theme);

    $this->get('/admin')->assertOk()->assertSee('apply("'.$theme.'"', false)->assertSee('Apparence');
})->with(['system', 'light', 'dark']);

it('refuse un thème inconnu et les visiteurs', function () {
    $this->putJson(route('preferences.theme'), ['theme' => 'dark'])->assertUnauthorized();

    $this->actingAs(userWithRole(Role::Commercial))->putJson(route('preferences.theme'), ['theme' => 'violet'])->assertUnprocessable();
});
