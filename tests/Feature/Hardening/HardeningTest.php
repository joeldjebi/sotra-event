<?php

use App\Enums\MissionStatus;
use App\Enums\Role;
use App\Enums\StaffRole;
use App\Http\Controllers\Api\V1\HealthController;
use App\Models\Customer;
use App\Models\Mission;
use App\Models\Order;
use App\Models\OtpCode;
use App\Models\Position;
use App\Models\SmsMessage;
use App\Models\Staff;
use App\Models\User;
use App\Services\Operations\MissionPlanner;
use App\Services\Sales\PassTokenService;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;

it('ajoute les en-têtes de sécurité aux pages et à l\'API (NF-03)', function () {
    foreach (['/', '/api/v1/events'] as $uri) {
        $this->get($uri)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Content-Security-Policy');
    }

    config(['eventtrans.security.csp' => true]);
    expect($this->get('/')->headers->get('Content-Security-Policy'))
        ->toContain("frame-ancestors 'none'")
        ->toContain('https://*.tile.openstreetmap.org');

    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

it('limite le nombre de requêtes à l\'API par compte (NF-03)', function () {
    Sanctum::actingAs(Customer::factory()->create(), ['customer']);

    for ($i = 0; $i < 300; $i++) {
        $this->getJson('/api/v1/me/passes');
    }

    $this->getJson('/api/v1/me/passes')->assertStatus(429);
});

it('exige l\'acceptation des CGV pour commander, pas pour un devis (NF-14)', function () {
    $event = eventOnSale();
    Sanctum::actingAs(Customer::factory()->create(), ['customer']);
    $order = ['event_id' => $event->id, 'boarding_point_id' => $event->boardingPoints->first()->id, 'offer' => 'standard', 'pass_type' => 'aller_retour', 'quantity' => 1];

    $this->getJson("/api/v1/events/{$event->id}")->assertJsonPath('data.terms.terms_url', route('legal.terms'));
    $this->postJson('/api/v1/orders/quote', $order)->assertOk();
    $this->postJson('/api/v1/orders', $order)->assertJsonValidationErrors('accept_terms');
    $reference = $this->postJson('/api/v1/orders', $order + ['accept_terms' => true])->assertCreated()->json('data.reference');

    expect(Order::query()->firstWhere('reference', $reference))
        ->terms_version->toBe('2026-11')
        ->terms_accepted_at->not->toBeNull();

    foreach (['/cgv' => 'Conditions générales de vente', '/confidentialite' => '2013-450', '/mentions-legales' => 'Mentions légales'] as $uri => $text) {
        $this->get($uri)->assertOk()->assertSee($text);
    }
});

it('ne conserve les positions GPS que pendant la mission (NF-12)', function () {
    $event = eventOnSale();
    paidOrder($event, 1);
    app(MissionPlanner::class)->plan($event);
    $conveyor = Staff::factory()->role(StaffRole::Conveyor)->create(['company_id' => $event->company_id]);
    $mission = Mission::query()->where('direction', 'aller')->sole();
    $mission->update(['conveyor_id' => $conveyor->id]);
    Sanctum::actingAs($conveyor, ['conveyor']);
    $send = fn (string $at) => $this->postJson("/api/v1/missions/{$mission->id}/positions", ['positions' => [['latitude' => 5.40, 'longitude' => -4.05, 'recorded_at' => $at]]]);

    $send(now()->toIso8601String())->assertJsonPath('data.stored', 0);

    $mission->update(['status' => MissionStatus::OnRoute]);
    $send(now()->toIso8601String())->assertJsonPath('data.stored', 1);

    $mission->update(['status' => MissionStatus::Arrived, 'arrived_at' => now()]);
    $send(now()->subMinutes(10)->toIso8601String())->assertJsonPath('data.stored', 1);
    $send(now()->addMinutes(30)->toIso8601String())->assertJsonPath('data.stored', 0);
});

it('purge les données personnelles au-delà des durées de conservation (NF-12)', function () {
    $event = eventOnSale();
    paidOrder($event, 1);
    app(MissionPlanner::class)->plan($event);
    $event->update(['starts_at' => now()->subDays(100), 'ends_at' => now()->subDays(100)->addHours(5)]);
    Mission::query()->where('direction', 'aller')->sole()->positions()->create(['recorded_at' => now()->subDays(100), 'location' => Point::makeGeodetic(5.4, -4.0)]);
    SmsMessage::query()->create(['to' => '+2250707070707', 'body' => 'ancien', 'driver' => 'log', 'status' => 'simulated', 'created_at' => now()->subDays(200)]);
    OtpCode::query()->create(['phone' => '+2250707070707', 'audience' => 'customer', 'code_hash' => 'x', 'expires_at' => now()->subDays(40)])
        ->forceFill(['created_at' => now()->subDays(40)])->save();
    $recent = SmsMessage::query()->create(['to' => '+2250707070707', 'body' => 'récent', 'driver' => 'log', 'status' => 'simulated']);

    $this->artisan('model:prune', ['--model' => [Position::class, SmsMessage::class, OtpCode::class]])->assertSuccessful();

    expect(['positions' => Position::query()->count(), 'otp' => OtpCode::query()->count()])->toBe(['positions' => 0, 'otp' => 0])
        ->and(SmsMessage::query()->where('created_at', '<', now()->subDays(180))->count())->toBe(0)
        ->and(SmsMessage::query()->whereKey($recent->id)->exists())->toBeTrue();
});

it('expose une sonde de supervision (NF-08)', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database', true)
        ->assertJsonPath('checks.scheduler', false);

    Cache::forever(HealthController::SCHEDULER_HEARTBEAT, now()->timestamp);
    $this->getJson('/api/v1/health')->assertJsonPath('checks.scheduler', true);
});

it('ne stocke aucune donnée de carte bancaire (PA-06)', function () {
    $columns = collect(Schema::getTables())->flatMap(fn (array $table) => Schema::getColumnListing($table['name']));

    expect($columns->filter(fn (string $column) => preg_match('/card_number|pan$|cvv|cvc|expiry|card_holder/i', $column) === 1)->all())->toBe([]);
});

it('crée le premier super administrateur depuis la console, mot de passe saisi au clavier', function () {
    $this->artisan('users:create-admin', ['email' => 'dsi@acces-event.test', 'name' => 'DSI ACCES EVENT'])
        ->expectsQuestion('Mot de passe (12 caractères au moins, lettres et chiffres)', 'court')
        ->assertFailed();

    $this->artisan('users:create-admin', ['email' => 'dsi@acces-event.test', 'name' => 'DSI ACCES EVENT'])
        ->expectsQuestion('Mot de passe (12 caractères au moins, lettres et chiffres)', 'MotDePasse2026Solide')
        ->assertSuccessful();

    expect(User::query()->firstWhere('email', 'dsi@acces-event.test')->hasRole(Role::SuperAdmin->value))->toBeTrue();
});

it('génère une clé de signature des QR utilisable par l\'application (NF-01)', function () {
    $this->artisan('passes:generate-signing-key')->assertSuccessful();

    $secret = base64_encode(sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair()));
    config(['eventtrans.pass_signing.secret_key' => $secret]);
    $pass = paidOrder(eventOnSale(), 1)->passes->first();
    $token = app(PassTokenService::class)->sign($pass);

    expect(app(PassTokenService::class)->publicKeyBase64())->toBe(base64_encode(sodium_crypto_sign_publickey_from_secretkey(base64_decode($secret))))
        ->and(app(PassTokenService::class)->verify($token))->not->toBeNull();
});
