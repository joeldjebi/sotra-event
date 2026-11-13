<?php

use App\Enums\Role;
use PragmaRX\Google2FA\Google2FA;

it('oblige à activer la 2FA avant d\'accéder à un espace (NF-03)', function () {
    $user = userWithRole(Role::Commercial, withTwoFactor: false);

    $this->actingAs($user)->get(route('company.dashboard'))
        ->assertRedirect(route('security.two-factor'));

    $this->get(route('space'))->assertRedirect(route('security.two-factor'));
});

it('active puis confirme la 2FA avec un code TOTP valide', function () {
    $user = userWithRole(Role::Commercial, withTwoFactor: false);
    $this->actingAs($user);

    $this->get(route('security.two-factor'))->assertOk()->assertSee('Protégez votre compte');

    $this->post(route('two-factor.enable'))->assertRedirect();
    $this->get(route('security.two-factor'))->assertOk()->assertSee('Scannez ce code');

    $code = app(Google2FA::class)->getCurrentOtp(decrypt($user->fresh()->two_factor_secret));

    $this->post(route('two-factor.confirm'), ['code' => $code])->assertRedirect();

    expect($user->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

    $this->get(route('company.dashboard'))->assertOk();
});

it('refuse un code de confirmation incorrect', function () {
    $user = userWithRole(Role::Commercial, withTwoFactor: false);
    $this->actingAs($user)->post(route('two-factor.enable'));

    $this->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertSessionHasErrorsIn('confirmTwoFactorAuthentication', 'code');

    expect($user->fresh()->two_factor_confirmed_at)->toBeNull();
});
