<?php

use App\Livewire\Site\DeleteAccount;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SmsMessage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

function lastOtpCode(): string
{
    preg_match('/est (\d{6})/', (string) SmsMessage::query()->latest('id')->value('body'), $match);

    return $match[1] ?? '';
}

it('publie la politique de confidentialité et la page de suppression de compte sans connexion', function () {
    $this->get('/confidentialite')->assertOk()
        ->assertSee('Politique de confidentialité')
        ->assertSee('uniquement pendant une mission')
        ->assertSee(route('legal.account-deletion'))
        ->assertDontSee('Projet de texte');

    $this->get('/suppression-compte')->assertOk()
        ->assertSee('Supprimer mon compte')
        ->assertSee('Supprimé immédiatement');
});

it('supprime le compte depuis le web après vérification par SMS, en gardant les ventes anonymes', function () {
    // Achat avant l'événement du 15/11, suppression après sa fin.
    $this->travelTo('2026-11-10 10:00');
    $order = paidOrder(eventOnSale(), 1);
    $customer = $order->customer;
    $phone = $customer->phone;
    $customer->update(['first_name' => 'Awa', 'last_name' => 'Traoré']);
    $this->travelTo('2026-11-25 10:00');

    $component = Livewire::test(DeleteAccount::class)
        ->set('phone', $customer->phone)
        ->call('sendCode')
        ->assertSet('step', 'code');

    $component->set('code', lastOtpCode())->call('verify')->assertSet('step', 'confirm')
        ->call('delete')->assertHasErrors('understood')
        ->set('understood', true)->call('delete')->assertSet('step', 'done')->assertSee('Compte supprimé');

    expect($customer->fresh())
        ->phone->toBe('supprime-'.$customer->id)
        ->first_name->toBeNull()
        ->anonymized_at->not->toBeNull()
        ->and(Order::query()->whereKey($order->id)->exists())->toBeTrue()
        ->and(SmsMessage::query()->where('to', $phone)->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'customer_account_deleted')->exists())->toBeTrue();
});

it('refuse la suppression tant qu’un Pass est à venir', function () {
    $this->travelTo('2026-11-10 10:00');
    $customer = paidOrder(eventOnSale(), 1)->customer;
    Sanctum::actingAs($customer, ['customer']);

    $this->deleteJson('/api/v1/me')->assertStatus(409)->assertJsonPath('code', 'ACCOUNT_HAS_UPCOMING_PASSES');
    expect($customer->fresh()->anonymized_at)->toBeNull();
});

it('supprime le compte depuis l’application et révoque tous ses jetons', function () {
    $customer = Customer::factory()->create();
    $customer->createToken('autre téléphone', ['customer']);
    Sanctum::actingAs($customer, ['customer']);

    $this->deleteJson('/api/v1/me')->assertOk()->assertJsonPath('message', 'Votre compte a été supprimé.');

    expect($customer->tokens()->count())->toBe(0)
        ->and($customer->fresh()->anonymized_at)->not->toBeNull();
});
