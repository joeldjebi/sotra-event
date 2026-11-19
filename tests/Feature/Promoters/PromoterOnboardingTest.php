<?php

use App\Enums\PromoterStatus;
use App\Enums\Role;
use App\Livewire\Admin\PromotersTable;
use App\Livewire\Promoter\Organisation;
use App\Livewire\Site\BecomePromoter;
use App\Models\AuditLog;
use App\Models\Promoter;
use App\Models\SmsMessage;
use App\Models\User;
use App\Notifications\PromoterApplicationReceived;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * Dernier code SMS envoyé au numéro (pilote SMS « log »).
 */
function lastCodeSentTo(string $phone): string
{
    preg_match('/\b(\d{6})\b/', (string) SmsMessage::query()->where('to', $phone)->latest('id')->value('body'), $matches);

    return $matches[1];
}

/**
 * Formulaire d'inscription rempli, prêt à recevoir le code.
 */
function fillPromoterApplication(array $overrides = []): Testable
{
    $component = Livewire::test(BecomePromoter::class);

    foreach ([
        'name' => 'Gbairai Productions',
        'phone' => '07 08 09 10 11',
        'email' => 'contact@gbairai.test',
        'address' => 'Cocody, Riviera 2',
        'account_name' => 'Aya Koné',
        'account_email' => 'aya@gbairai.test',
        'password' => 'MotDePasse2026',
        'password_confirmation' => 'MotDePasse2026',
        'accept_terms' => true,
    ] as $field => $value) {
        $component->set($field, $overrides[$field] ?? $value);
    }

    return $component;
}

it('inscrit un promoteur après vérification de son téléphone, en attente de validation', function () {
    Notification::fake();
    $admin = userWithRole(Role::SuperAdmin);

    $this->get('/devenir-promoteur')->assertOk()->assertSee('Devenir promoteur');

    $component = fillPromoterApplication()->call('sendCode')->assertHasNoErrors()->assertSet('step', 'code');

    // Rien n'est créé tant que le téléphone n'est pas vérifié.
    expect(Promoter::query()->count())->toBe(0);

    $component->set('code', lastCodeSentTo('+2250708091011'))->call('verify')->assertHasNoErrors()->assertSet('step', 'done');

    $promoter = Promoter::query()->sole();
    $user = User::query()->where('email', 'aya@gbairai.test')->sole();

    expect($promoter->status)->toBe(PromoterStatus::Pending)
        ->and($promoter->is_active)->toBeFalse()
        ->and($promoter->phone)->toBe('+2250708091011')
        ->and($promoter->phone_verified_at)->not->toBeNull()
        ->and($promoter->isFileComplete())->toBeFalse()
        ->and($promoter->missingItems())->toBe(['Numéro RCCM', 'Extrait RCCM', 'Pièce d\'identité du responsable'])
        ->and($user->promoter_id)->toBe($promoter->id)
        ->and($user->hasRole(Role::PromoterAdmin->value))->toBeTrue()
        ->and($user->must_change_password)->toBeFalse()
        ->and(Hash::check('MotDePasse2026', $user->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'promoter_application_submitted')->exists())->toBeTrue();

    Notification::assertSentTo($admin, PromoterApplicationReceived::class);
});

it('accepte un dossier complet avec RCCM et justificatifs, stockés sur le disque privé', function () {
    Notification::fake();
    Storage::fake('local');
    $this->travelBack();

    fillPromoterApplication()
        ->set('rccm_number', 'CI-ABJ-2024-B-12345')
        ->set('rccm_document', UploadedFile::fake()->create('rccm.pdf', 200, 'application/pdf'))
        ->set('identity_document', UploadedFile::fake()->image('cni.jpg'))
        ->call('sendCode')
        ->set('code', lastCodeSentTo('+2250708091011'))
        ->call('verify')
        ->assertSet('step', 'done');

    $promoter = Promoter::query()->sole();

    expect($promoter->isFileComplete())->toBeTrue();
    Storage::disk('local')->assertExists([$promoter->rccm_document_path, $promoter->id_document_path]);
});

it('refuse un mauvais code, un nom ou un téléphone déjà pris et un mot de passe faible', function () {
    $component = fillPromoterApplication()->call('sendCode');
    $component->set('code', '000000')->call('verify')->assertHasErrors('code');
    expect(Promoter::query()->count())->toBe(0);

    Promoter::factory()->create(['name' => 'Déjà Là', 'phone' => '+2250102030405']);

    fillPromoterApplication(['name' => 'Déjà Là', 'phone' => '0102030405', 'password' => 'court', 'password_confirmation' => 'court', 'accept_terms' => false])
        ->call('sendCode')
        ->assertHasErrors(['name', 'phone', 'password', 'accept_terms'])
        ->assertSet('step', 'form');
});

it('montre le suivi de la demande, sans accès à l\'espace, tant que le promoteur n\'est pas validé', function () {
    $promoter = Promoter::factory()->create(['status' => PromoterStatus::Pending]);
    $user = userWithRole(Role::PromoterAdmin, ['promoter_id' => $promoter->id], withTwoFactor: false);

    $this->actingAs($user);
    $this->get('/espace')->assertRedirect(route('promoter.application'));
    $this->get('/promoteur')->assertRedirect(route('promoter.application'));
    $this->get('/promoteur/demande')->assertOk()->assertSee('En attente de validation')->assertSee('Dossier incomplet');
});

it('valide un promoteur : il est prévenu par SMS et son équipe accède à l\'espace', function () {
    $promoter = Promoter::factory()->create(['status' => PromoterStatus::Pending, 'phone' => '+2250708091011']);
    $member = userWithRole(Role::PromoterAdmin, ['promoter_id' => $promoter->id]);

    $this->actingAs(userWithRole(Role::SuperAdmin));
    Livewire::test(PromotersTable::class)
        ->set('statusFilter', 'en_attente')
        ->assertSee($promoter->name)
        ->call('review', $promoter->id)
        ->assertSee('Dossier incomplet')
        ->call('approve')
        ->assertHasNoErrors();

    expect($promoter->refresh()->status)->toBe(PromoterStatus::Approved)
        ->and($promoter->is_active)->toBeTrue()
        ->and($promoter->reviewed_by)->toBe(auth()->id())
        ->and(SmsMessage::query()->where('to', '+2250708091011')->latest('id')->value('body'))->toContain('validé')
        ->and(AuditLog::query()->where('action', 'promoter_status_changed')->exists())->toBeTrue();

    $this->actingAs($member)->get('/promoteur')->assertOk();
    $this->get('/promoteur/demande')->assertRedirect(route('space'));
});

it('refuse une demande avec un motif, puis suspend et réactive un promoteur validé', function () {
    $pending = Promoter::factory()->create(['status' => PromoterStatus::Pending]);
    $approved = Promoter::factory()->create();
    $member = userWithRole(Role::TransportLead, ['promoter_id' => $approved->id]);

    $this->actingAs(userWithRole(Role::Operator));
    $table = Livewire::test(PromotersTable::class);

    $table->call('review', $pending->id)->call('reject')->assertHasErrors('decisionNote');
    $table->set('decisionNote', 'Structure inconnue, merci de nous contacter.')->call('reject')->assertHasNoErrors();
    expect($pending->refresh()->status)->toBe(PromoterStatus::Rejected)
        ->and($pending->review_note)->toBe('Structure inconnue, merci de nous contacter.');

    $table->call('review', $approved->id)->set('decisionNote', 'Impayés')->call('suspend');
    expect($approved->refresh()->status)->toBe(PromoterStatus::Suspended);

    $this->actingAs($member)->get('/promoteur')->assertRedirect(route('promoter.application'));
    $this->get('/promoteur/demande')->assertSee('suspendu')->assertSee('Impayés');

    $this->actingAs(userWithRole(Role::Operator));
    Livewire::test(PromotersTable::class)->call('review', $approved->id)->call('reactivate');
    expect($approved->refresh()->status)->toBe(PromoterStatus::Approved);

    // Une décision impossible est refusée (un promoteur validé ne se « refuse » pas).
    Livewire::test(PromotersTable::class)->call('review', $approved->id)->set('decisionNote', 'x')->call('reject')->assertHasErrors('status');
});

it('crée un promoteur validé d\'office dans la console, avec son administrateur', function () {
    $this->actingAs(userWithRole(Role::Operator));

    Livewire::test(PromotersTable::class)
        ->call('create')
        ->set('name', 'Live Nation CI')
        ->set('phone', '+2250700000001')
        ->set('account_name', 'Koffi Admin')
        ->set('account_email', 'koffi@livenation.test')
        ->set('account_password', 'Provisoire2026')
        ->call('save')
        ->assertHasNoErrors();

    $promoter = Promoter::query()->where('name', 'Live Nation CI')->sole();
    $user = User::query()->where('email', 'koffi@livenation.test')->sole();

    expect($promoter->status)->toBe(PromoterStatus::Approved)
        ->and($promoter->applied_at)->toBeNull()
        ->and($user->hasRole(Role::PromoterAdmin->value))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue();
});

it('ouvre les justificatifs à ACCES EVENT seulement', function () {
    Storage::fake('local');
    $this->travelBack();
    $promoter = Promoter::factory()->create(['rccm_document_path' => UploadedFile::fake()->create('rccm.pdf', 10, 'application/pdf')->store('promoters/1', 'local')]);

    $this->actingAs(userWithRole(Role::Operator))
        ->get(route('admin.promoters.document', [$promoter, 'rccm']))
        ->assertOk();
    $this->get(route('admin.promoters.document', [$promoter, 'identity']))->assertNotFound();

    $this->actingAs(userWithRole(Role::PromoterAdmin, ['promoter_id' => $promoter->id]))
        ->get(route('admin.promoters.document', [$promoter, 'rccm']))
        ->assertForbidden();
});

it('laisse l\'administrateur du promoteur compléter son dossier en attente', function () {
    Storage::fake('local');
    $this->travelBack();
    $promoter = Promoter::factory()->create(['status' => PromoterStatus::Pending]);
    $this->actingAs(userWithRole(Role::PromoterAdmin, ['promoter_id' => $promoter->id], withTwoFactor: false));

    Livewire::test(Organisation::class)
        ->set('rccm_number', 'CI-ABJ-2024-B-999')
        ->set('rccm_document', UploadedFile::fake()->create('rccm.pdf', 50, 'application/pdf'))
        ->set('identity_document', UploadedFile::fake()->image('cni.png'))
        ->call('save')
        ->assertHasNoErrors();

    expect($promoter->refresh()->isFileComplete())->toBeTrue()
        ->and($promoter->status)->toBe(PromoterStatus::Pending);
});

it('n\'accepte pas l\'audience des candidats promoteurs sur l\'API mobile', function () {
    $this->postJson('/api/v1/auth/otp', ['phone' => '0708091011', 'audience' => 'promoter_applicant'])
        ->assertUnprocessable();
});
