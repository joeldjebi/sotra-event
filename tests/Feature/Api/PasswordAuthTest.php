<?php

use App\Enums\StaffRole;
use App\Models\Customer;
use App\Models\Staff;
use Illuminate\Testing\TestResponse;

/**
 * Connexion des applications mobiles par téléphone + mot de passe (lastOtpSentTo et requestOtp : OtpAuthTest).
 */
function login(string $phone, string $password, string $audience = 'customer'): TestResponse
{
    return test()->postJson('/api/v1/auth/login', [
        'phone' => $phone, 'audience' => $audience, 'password' => $password, 'device_name' => 'Pixel 8',
    ]);
}

it('inscrit un spectateur après le code SMS puis le connecte par mot de passe', function () {
    requestOtp('0707080839')->assertStatus(202);

    $this->postJson('/api/v1/auth/register', [
        'phone' => '0707080839',
        'code' => lastOtpSentTo('+2250707080839'),
        'password' => 'Abidjan2026',
        'password_confirmation' => 'Abidjan2026',
        'first_name' => 'Awa',
        'device_name' => 'Pixel 8',
    ])
        ->assertOk()
        ->assertJsonPath('data.abilities', ['customer'])
        ->assertJsonPath('data.user.first_name', 'Awa')
        ->assertJsonPath('data.must_change_password', false);

    login('07 07 08 08 39', 'Abidjan2026')
        ->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.phone', '+2250707080839');
});

it('refuse l\'inscription sans code valide ou avec un mot de passe trop faible', function () {
    requestOtp('0707080839');

    $this->postJson('/api/v1/auth/register', [
        'phone' => '0707080839', 'code' => '000000', 'password' => 'Abidjan2026', 'password_confirmation' => 'Abidjan2026', 'device_name' => 'Pixel 8',
    ])->assertStatus(422)->assertJsonPath('code', 'OTP_INVALID');

    $this->postJson('/api/v1/auth/register', [
        'phone' => '0707080839', 'code' => lastOtpSentTo('+2250707080839'), 'password' => 'abc', 'password_confirmation' => 'abc', 'device_name' => 'Pixel 8',
    ])->assertStatus(422)->assertJsonValidationErrors('password');

    expect(Customer::query()->whereNotNull('password')->exists())->toBeFalse();
});

it('refuse une seconde inscription sur un numéro qui a déjà un mot de passe', function () {
    Customer::factory()->create(['phone' => '+2250707080839', 'password' => 'Abidjan2026']);
    requestOtp('0707080839');

    $this->postJson('/api/v1/auth/register', [
        'phone' => '0707080839', 'code' => lastOtpSentTo('+2250707080839'), 'password' => 'Autre2026x', 'password_confirmation' => 'Autre2026x', 'device_name' => 'Pixel 8',
    ])->assertStatus(409)->assertJsonPath('code', 'ACCOUNT_EXISTS');

    login('0707080839', 'Abidjan2026')->assertOk();
});

it('répond pareil pour un numéro inconnu, un mauvais mot de passe ou un compte sans mot de passe', function (string $phone) {
    Customer::factory()->create(['phone' => '+2250707080839', 'password' => 'Abidjan2026']);
    Customer::factory()->create(['phone' => '+2250707080840', 'password' => null]);

    login($phone, $phone === '0707080839' ? 'Mauvais2026' : 'Abidjan2026')
        ->assertStatus(401)
        ->assertJsonPath('code', 'INVALID_CREDENTIALS');
})->with(['mauvais mot de passe' => '0707080839', 'sans mot de passe' => '0707080840', 'inconnu' => '0707080841']);

it('remplace le mot de passe oublié après un code SMS et déconnecte les autres appareils', function () {
    $customer = Customer::factory()->create(['phone' => '+2250707080839', 'password' => 'Ancien2026']);
    $customer->createToken('Ancien téléphone', ['customer']);
    requestOtp('0707080839');

    $this->postJson('/api/v1/auth/password/reset', [
        'phone' => '0707080839', 'audience' => 'customer', 'code' => lastOtpSentTo('+2250707080839'),
        'password' => 'Nouveau2026', 'password_confirmation' => 'Nouveau2026', 'device_name' => 'Pixel 8',
    ])->assertOk();

    expect($customer->tokens()->pluck('name')->all())->toBe(['Pixel 8']);
    login('0707080839', 'Ancien2026')->assertStatus(401);
    login('0707080839', 'Nouveau2026')->assertOk();
});

it('connecte un convoyeur avec le mot de passe de l\'exploitation et l\'oblige à le changer', function () {
    $staff = Staff::factory()->role(StaffRole::Conveyor)->create([
        'phone' => '+2250700000001', 'password' => 'Initial2026', 'must_change_password' => true,
    ]);

    $token = login('0700000001', 'Initial2026', 'staff')
        ->assertOk()
        ->assertJsonPath('data.abilities', ['conveyor'])
        ->assertJsonPath('data.must_change_password', true)
        ->assertJsonPath('data.user.company.id', $staff->company_id)
        ->json('data.token');

    $headers = ['Authorization' => 'Bearer '.$token];

    $this->getJson('/api/v1/me/missions', $headers)->assertStatus(403)->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');

    $this->postJson('/api/v1/auth/password', ['current_password' => 'Faux2026xx', 'password' => 'Perso2026', 'password_confirmation' => 'Perso2026'], $headers)
        ->assertStatus(422)->assertJsonPath('code', 'INVALID_CURRENT_PASSWORD');

    $this->postJson('/api/v1/auth/password', ['current_password' => 'Initial2026', 'password' => 'Perso2026', 'password_confirmation' => 'Perso2026'], $headers)
        ->assertOk();

    expect($staff->refresh()->must_change_password)->toBeFalse();
    $this->getJson('/api/v1/me/missions', $headers)->assertOk();
    login('0700000001', 'Perso2026', 'staff')->assertOk()->assertJsonPath('data.must_change_password', false);
});

it('refuse le personnel désactivé ou chauffeur, même avec le bon mot de passe', function (Closure $makeStaff) {
    $makeStaff();

    login('0700000009', 'Initial2026', 'staff')->assertStatus(403)->assertJsonPath('code', 'ACCOUNT_NOT_ALLOWED');
})->with([
    'inactif' => [fn () => Staff::factory()->inactive()->create(['phone' => '+2250700000009', 'password' => 'Initial2026'])],
    'chauffeur' => [fn () => Staff::factory()->role(StaffRole::Driver)->create(['phone' => '+2250700000009', 'password' => 'Initial2026'])],
]);

it('limite les essais de mot de passe par numéro', function () {
    Customer::factory()->create(['phone' => '+2250707080839', 'password' => 'Abidjan2026']);

    foreach (range(1, 5) as $attempt) {
        login('0707080839', 'Mauvais2026')->assertStatus(401);
    }

    login('0707080839', 'Abidjan2026')->assertStatus(429);
});
