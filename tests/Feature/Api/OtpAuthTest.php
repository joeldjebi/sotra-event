<?php

use App\Enums\StaffRole;
use App\Models\Customer;
use App\Models\OtpCode;
use App\Models\SmsMessage;
use App\Models\Staff;
use Illuminate\Testing\TestResponse;

/**
 * Récupère le dernier code envoyé par SMS (pilote « log »).
 */
function lastOtpSentTo(string $phone): string
{
    $body = SmsMessage::query()->where('to', $phone)->latest('id')->value('body');
    preg_match('/\b(\d{6})\b/', (string) $body, $matches);

    return $matches[1];
}

function requestOtp(string $phone, string $audience = 'customer'): TestResponse
{
    return test()->postJson('/api/v1/auth/otp', ['phone' => $phone, 'audience' => $audience]);
}

function verifyOtp(string $phone, string $code, string $audience = 'customer'): TestResponse
{
    return test()->postJson('/api/v1/auth/verify', [
        'phone' => $phone, 'code' => $code, 'audience' => $audience, 'device_name' => 'Pixel 8',
    ]);
}

it('envoie un code par SMS et le stocke haché', function () {
    requestOtp('07 07 08 08 39')
        ->assertStatus(202)
        ->assertJsonPath('data.expires_in', 300);

    $code = lastOtpSentTo('+2250707080839');
    $otp = OtpCode::query()->sole();

    expect($code)->toHaveLength(6)
        ->and($otp->code_hash)->not->toBe($code)
        ->and($otp->phone)->toBe('+2250707080839');
});

it('connecte directement un spectateur déjà inscrit', function () {
    Customer::factory()->create(['phone' => '+2250707080839', 'first_name' => 'Awa', 'last_name' => 'Koné']);
    requestOtp('0707080839');

    verifyOtp('0707080839', lastOtpSentTo('+2250707080839'))
        ->assertOk()
        ->assertJsonPath('data.status', 'logged_in')
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.abilities', ['customer'])
        ->assertJsonPath('data.is_new_account', false)
        ->assertJsonPath('data.user.first_name', 'Awa')
        ->assertJsonPath('data.user.phone', '+2250707080839');

    expect(Customer::query()->count())->toBe(1);
});

it('donne accès à /me avec le jeton puis le révoque à la déconnexion', function () {
    Customer::factory()->create(['phone' => '+2250707080839', 'first_name' => 'Awa', 'last_name' => 'Koné']);
    requestOtp('0707080839');
    $token = verifyOtp('0707080839', lastOtpSentTo('+2250707080839'))->json('data.token');

    expect($token)->toBeString();
    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.type', 'customer');
    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
});

it('refuse un code incorrect puis bloque après 5 essais', function () {
    requestOtp('0707080839');
    $good = lastOtpSentTo('+2250707080839');
    $bad = $good === '000000' ? '111111' : '000000';

    foreach (range(1, 5) as $attempt) {
        verifyOtp('0707080839', $bad)->assertStatus(422)->assertJsonPath('code', 'OTP_INVALID');
    }

    verifyOtp('0707080839', $good)->assertStatus(429)->assertJsonPath('code', 'OTP_TOO_MANY_ATTEMPTS');
});

it('refuse un code expiré', function () {
    requestOtp('0707080839');
    $code = lastOtpSentTo('+2250707080839');

    $this->travel(6)->minutes();

    verifyOtp('0707080839', $code)->assertStatus(422)->assertJsonPath('code', 'OTP_EXPIRED');
});

it('n\'accepte un code qu\'une seule fois', function () {
    requestOtp('0707080839');
    $code = lastOtpSentTo('+2250707080839');

    verifyOtp('0707080839', $code)->assertOk();
    verifyOtp('0707080839', $code)->assertStatus(422)->assertJsonPath('code', 'OTP_NOT_FOUND');
});

it('impose un délai avant de renvoyer un code', function () {
    requestOtp('0707080839')->assertStatus(202);
    requestOtp('0707080839')->assertStatus(429)->assertJsonPath('code', 'OTP_RESEND_TOO_SOON');

    $this->travel(61)->seconds();
    requestOtp('0707080839')->assertStatus(202);
});

it('réserve la connexion par code au spectateur : le personnel passe par son mot de passe', function () {
    Staff::factory()->role(StaffRole::Conveyor)->create(['phone' => '+2250700000001']);

    requestOtp('0700000001', 'staff')->assertStatus(202);
    $code = lastOtpSentTo('+2250700000001');

    verifyOtp('0700000001', $code, 'staff')
        ->assertStatus(403)
        ->assertJsonPath('code', 'PASSWORD_LOGIN_REQUIRED');

    expect(OtpCode::query()->whereNull('consumed_at')->count())->toBe(1);
});

it('inscrit un nouveau spectateur : code vérifié sans compte, puis informations et connexion automatique', function () {
    requestOtp('0707080839');

    $registration = verifyOtp('0707080839', lastOtpSentTo('+2250707080839'))
        ->assertOk()
        ->assertJsonPath('data.status', 'registration_required')
        ->assertJsonPath('data.phone', '+2250707080839')
        ->assertJsonPath('data.registration_expires_in', 900)
        ->assertJsonMissingPath('data.token')
        ->json('data.registration_token');

    expect(Customer::query()->exists())->toBeFalse();

    $token = $this->postJson('/api/v1/auth/register/complete', [
        'registration_token' => $registration, 'first_name' => ' Awa ', 'last_name' => 'Koné',
        'email' => 'Awa.Kone@Exemple.ci', 'device_name' => 'Pixel 8',
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'logged_in')
        ->assertJsonPath('data.is_new_account', true)
        ->assertJsonPath('data.next_step', 'home')
        ->assertJsonPath('data.user.first_name', 'Awa')
        ->assertJsonPath('data.user.email', 'awa.kone@exemple.ci')
        ->assertJsonPath('data.user.profile_completed', true)
        ->json('data.token');

    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.phone', '+2250707080839');
});

it('n\'accepte le jeton d\'inscription qu\'une fois et pendant 15 minutes', function () {
    requestOtp('0707080839');
    $registration = verifyOtp('0707080839', lastOtpSentTo('+2250707080839'))->json('data.registration_token');
    $payload = ['registration_token' => $registration, 'first_name' => 'Awa', 'last_name' => 'Koné', 'device_name' => 'Pixel 8'];

    $this->postJson('/api/v1/auth/register/complete', $payload)->assertOk();
    $this->postJson('/api/v1/auth/register/complete', $payload)->assertStatus(422)->assertJsonPath('code', 'REGISTRATION_EXPIRED');

    $this->travel(61)->seconds();
    requestOtp('0707080840');
    $expired = verifyOtp('0707080840', lastOtpSentTo('+2250707080840'))->json('data.registration_token');
    $this->travel(16)->minutes();

    $this->postJson('/api/v1/auth/register/complete', [...$payload, 'registration_token' => $expired])
        ->assertStatus(422)
        ->assertJsonPath('code', 'REGISTRATION_EXPIRED');

    expect(Customer::query()->count())->toBe(1);
});

it('complète un compte existant sans nom au lieu d\'en créer un autre', function () {
    $customer = Customer::factory()->create(['phone' => '+2250707080839', 'first_name' => null, 'last_name' => null]);
    requestOtp('0707080839');

    $registration = verifyOtp('0707080839', lastOtpSentTo('+2250707080839'))
        ->assertJsonPath('data.status', 'registration_required')
        ->json('data.registration_token');

    $this->postJson('/api/v1/auth/register/complete', [
        'registration_token' => $registration, 'first_name' => 'Awa', 'last_name' => 'Koné', 'device_name' => 'Pixel 8',
    ])
        ->assertOk()
        ->assertJsonPath('data.is_new_account', false)
        ->assertJsonPath('data.user.id', $customer->id);

    expect(Customer::query()->count())->toBe(1);
});

it('valide les informations de l\'inscription', function () {
    $this->postJson('/api/v1/auth/register/complete', ['registration_token' => 'court', 'email' => 'pas-un-email'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['registration_token', 'first_name', 'last_name', 'email', 'device_name']);
});

it('modifie les informations du spectateur connecté', function () {
    $customer = Customer::factory()->create(['first_name' => 'Awa', 'last_name' => 'Koné']);

    $this->withToken($customer->createToken('Pixel 8', ['customer'])->plainTextToken)
        ->putJson('/api/v1/me/profile', ['first_name' => 'Awa', 'last_name' => 'Kouassi', 'email' => null])
        ->assertOk()
        ->assertJsonPath('data.last_name', 'Kouassi');
});

it('valide les informations personnelles', function () {
    $customer = Customer::factory()->create();

    $this->withToken($customer->createToken('Pixel 8', ['customer'])->plainTextToken)
        ->putJson('/api/v1/me/profile', ['first_name' => '', 'email' => 'pas-un-email'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['first_name', 'last_name', 'email']);
});

it('réserve les informations personnelles au spectateur', function () {
    $staff = Staff::factory()->create();
    $this->withToken($staff->createToken('PDA', ['conveyor'])->plainTextToken)
        ->putJson('/api/v1/me/profile', ['first_name' => 'A', 'last_name' => 'B'])
        ->assertForbidden();
});

it('n\'envoie pas de code au personnel inconnu, inactif ou chauffeur, sans le révéler', function (?Closure $makeStaff) {
    $makeStaff?->__invoke();

    requestOtp('0700000009', 'staff')->assertStatus(202);

    expect(SmsMessage::query()->count())->toBe(0);
})->with([
    'inconnu' => [null],
    'inactif' => [fn () => Staff::factory()->inactive()->create(['phone' => '+2250700000009'])],
    'chauffeur' => [fn () => Staff::factory()->role(StaffRole::Driver)->create(['phone' => '+2250700000009'])],
]);

it('valide les champs de la demande', function () {
    $this->postJson('/api/v1/auth/otp', ['phone' => 'abc', 'audience' => 'pirate'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone', 'audience']);
});

it('limite le nombre de demandes de code par numéro', function () {
    foreach (range(1, 3) as $i) {
        requestOtp('0707080839');
        $this->travel(61)->seconds();
    }

    requestOtp('0707080839')->assertStatus(429);
});
