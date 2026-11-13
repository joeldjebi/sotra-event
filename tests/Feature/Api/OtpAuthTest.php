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

it('connecte un spectateur et crée son compte à la première connexion', function () {
    requestOtp('0707080839');

    verifyOtp('0707080839', lastOtpSentTo('+2250707080839'))
        ->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.abilities', ['customer'])
        ->assertJsonPath('data.user.type', 'customer')
        ->assertJsonPath('data.user.phone', '+2250707080839');

    expect(Customer::query()->where('phone', '+2250707080839')->exists())->toBeTrue();
});

it('donne accès à /me avec le jeton puis le révoque à la déconnexion', function () {
    requestOtp('0707080839');
    $token = verifyOtp('0707080839', lastOtpSentTo('+2250707080839'))->json('data.token');

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

it('connecte un convoyeur avec la capacité conveyor', function () {
    $staff = Staff::factory()->role(StaffRole::Conveyor)->create(['phone' => '+2250700000001']);

    requestOtp('0700000001', 'staff')->assertStatus(202);

    verifyOtp('0700000001', lastOtpSentTo('+2250700000001'), 'staff')
        ->assertOk()
        ->assertJsonPath('data.abilities', ['conveyor'])
        ->assertJsonPath('data.user.short_name', $staff->shortName())
        ->assertJsonPath('data.user.company.id', $staff->company_id);
});

it('connecte un technicien avec la capacité tech', function () {
    Staff::factory()->role(StaffRole::Technician)->create(['phone' => '+2250700000003']);
    requestOtp('0700000003', 'staff');

    verifyOtp('0700000003', lastOtpSentTo('+2250700000003'), 'staff')
        ->assertOk()
        ->assertJsonPath('data.abilities', ['tech']);
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
