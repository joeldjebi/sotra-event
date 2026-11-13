<?php

use App\Enums\EventStatus;
use App\Enums\PayoutStatus;
use App\Enums\RefundReason;
use App\Enums\Role;
use App\Livewire\Admin\PayoutsTable;
use App\Livewire\Company\Payouts;
use App\Models\Company;
use App\Models\Payout;
use App\Services\Finance\PayoutCalculator;
use App\Services\Finance\RefundService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo('2026-11-10 10:00');
    $this->event = eventOnSale(seats: 10);
    paidOrder($this->event, 3);
    $cancelled = paidOrder($this->event, 1)->passes->first();
    app(RefundService::class)->cancel($cancelled, RefundReason::Customer);
});

it('verse exactement 90 % de la recette brute hors frais, moins les remboursements (RG-14, jeu d\'essai)', function () {
    $statement = app(PayoutCalculator::class)->statement($this->event);

    // 4 Pass à 3 000 vendus, 1 remboursé : (12 000 − 3 000) × 90 % = 8 100.
    expect($statement)->toBe([
        'passes_sold' => 3,
        'passes_refunded' => 1,
        'gross' => 12000,
        'refunds' => 3000,
        'commission' => 900,
        'net' => 8100,
    ])->and($statement['net'])->toBe(intdiv(($statement['gross'] - $statement['refunds']) * 90, 100));
});

it('fixe l\'échéance à 5 jours ouvrés après l\'événement, hors week-ends et jours fériés', function () {
    // Dimanche 15/11/2026 → lundi 16 … vendredi 20.
    expect(PayoutCalculator::dueOn(Carbon::parse('2026-11-15 23:00'))->toDateString())->toBe('2026-11-20')
        // Jeudi 30/04/2026 : 1er mai férié, week-end → 4, 5, 6, 7, 8 mai.
        ->and(PayoutCalculator::dueOn(Carbon::parse('2026-04-30 23:00'))->toDateString())->toBe('2026-05-08');
});

it('clôt l\'événement terminé, calcule le relevé, puis le fige à la validation (PA-05)', function () {
    $this->travelTo('2026-11-16 03:00');
    $this->artisan('payouts:generate')->expectsOutputToContain('1 relevé(s)')->assertSuccessful();

    $payout = Payout::query()->sole();
    expect($this->event->fresh()->status)->toBe(EventStatus::Finished)
        ->and($payout)->net->toBe(8100)->status->toBe(PayoutStatus::Calculated)->due_on->toDateString()->toBe('2026-11-20');

    $this->actingAs(userWithRole(Role::SuperAdmin));
    Livewire::test(PayoutsTable::class)->call('show', $payout->id)->call('validatePayout');

    // Un remboursement tardif ne modifie plus un relevé validé.
    $this->event->passes()->where('status', 'valide')->first()->update(['status' => 'annule']);
    $this->artisan('payouts:generate')->assertSuccessful();
    expect($payout->fresh())->net->toBe(8100)->status->toBe(PayoutStatus::Validated);
});

it('enregistre le paiement avec son justificatif, visible par la compagnie et elle seule (AD-11, CO-05)', function () {
    $this->travelTo('2026-11-16 03:00');
    $payout = app(PayoutCalculator::class)->generate($this->event->fresh());
    $admin = userWithRole(Role::SuperAdmin);
    // Les fichiers téléversés de Livewire sont datés sur l'horloge réelle.
    $this->travelBack();

    Livewire::actingAs($admin)->test(PayoutsTable::class)
        ->call('show', $payout->id)
        ->call('validatePayout')
        ->set('paymentMethod', 'virement')
        ->set('paymentReference', 'VIR-SGBCI-0042')
        ->call('markPaid')
        ->assertHasErrors('proof')
        ->set('proof', UploadedFile::fake()->image('virement.png'))
        ->call('markPaid')
        ->assertHasNoErrors();

    expect($payout->fresh())->status->toBe(PayoutStatus::Paid)->payment_reference->toBe('VIR-SGBCI-0042');
    Storage::disk('local')->assertExists($payout->fresh()->proof_path);

    $commercial = userWithRole(Role::Commercial, ['company_id' => $this->event->company_id]);
    Livewire::actingAs($commercial)->test(Payouts::class)->assertSee($payout->reference)->assertSee('8 100 FCFA');
    $this->actingAs($commercial)->get("/compagnie/reversements/{$payout->id}/releve.pdf")->assertOk()->assertHeader('content-type', 'application/pdf');
    $this->actingAs($commercial)->get("/compagnie/reversements/{$payout->id}/justificatif")->assertOk();

    $stranger = userWithRole(Role::Commercial, ['company_id' => Company::factory()->create()->id]);
    $this->actingAs($stranger)->get("/compagnie/reversements/{$payout->id}/releve.pdf")->assertNotFound();

    // Les fichiers téléversés de Livewire ont besoin du vrai disque local : on nettoie après le test.
    Storage::disk('local')->delete((string) $payout->fresh()->proof_path);
});

it('réserve la validation des reversements au super administrateur', function () {
    $this->actingAs(userWithRole(Role::Operator))->get('/admin/reversements')->assertForbidden();
    $this->actingAs(userWithRole(Role::SuperAdmin))->get('/admin/reversements')->assertOk();
});
