<?php

use App\Enums\PayoutStatus;
use App\Enums\Role;
use App\Models\BreakdownAlert;
use App\Models\Bus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Mission;
use App\Models\Payout;
use App\Models\Promoter;
use App\Models\Review;
use App\Models\Staff;
use App\Services\Reporting\PromoterEventInsights;
use Database\Seeders\DemoEventsSeeder;
use Database\Seeders\DemoOperationsSeeder;
use Database\Seeders\DemoSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

it('construit le jeu de démonstration jour J et bilan', function () {
    $this->seed([RolesAndPermissionsSeeder::class, DemoSeeder::class, DemoEventsSeeder::class, DemoOperationsSeeder::class]);

    $past = Event::query()->withoutGlobalScopes()->where('name', 'like', 'Festival%')->sole();
    $live = Event::query()->withoutGlobalScopes()->where('name', 'like', 'Match de gala%')->sole();

    expect(Payout::query()->withoutGlobalScopes()->where('event_id', $past->id)->sole()->status)->toBe(PayoutStatus::Paid)
        ->and(Review::query()->withoutGlobalScopes()->where('event_id', $past->id)->count())->toBeGreaterThan(30)
        ->and(BreakdownAlert::query()->withoutGlobalScopes()->where('event_id', $live->id)->count())->toBe(1)
        ->and($live->complaints()->withoutGlobalScopes()->where('is_collective', true)->count())->toBe(1);
});

it('crée puis supprime les données de test d\'un promoteur, sans toucher aux données réelles', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $company = Company::factory()->create(['name' => 'Transporteur Réel']);
    $realBus = Bus::factory()->create(['company_id' => $company->id, 'number' => '101']);
    $realCustomer = Customer::factory()->create(['phone' => '+2250712345678']);
    $promoter = Promoter::factory()->create(['name' => 'Promoteur Test']);
    $lead = userWithRole(Role::TransportLead, ['promoter_id' => $promoter->id]);
    userWithRole(Role::SuperAdmin);

    $this->artisan('demo:promoter-test', ['--promoter' => 'Promoteur Test', '--company' => 'Transporteur Réel', '--force' => true])->assertSuccessful();

    $events = Event::query()->withoutGlobalScopes()->where('name', 'like', '[TEST]%')->get();

    expect($events)->toHaveCount(2)
        ->and($events->pluck('promoter_id')->unique()->all())->toBe([$promoter->id])
        ->and($events->every(fn (Event $event) => $event->channels === []))->toBeTrue()
        ->and(Payout::query()->withoutGlobalScopes()->count())->toBe(0)
        ->and(Customer::query()->where('phone', 'like', '+22500%')->count())->toBeGreaterThan(100)
        ->and(Customer::query()->where('phone', 'like', '+2250%')->where('phone', 'not like', '+22500%')->where('id', '!=', $realCustomer->id)->count())->toBe(0)
        ->and($realBus->refresh()->status->value)->not->toBe('reserve')
        ->and(Mission::query()->withoutGlobalScopes()->whereIn('event_id', $events->pluck('id'))->whereHas('bus', fn ($query) => $query->where('id', $realBus->id))->count())->toBe(0);

    // Le promoteur voit ses deux événements, sur le site public rien n'apparaît.
    $this->actingAs($lead);
    expect(Event::query()->whereIn('status', PromoterEventInsights::visibleStatuses())->count())->toBe(2);
    $this->get('/')->assertDontSee('[TEST]');

    $this->artisan('demo:promoter-test', ['--purge' => true, '--company' => 'Transporteur Réel'])->assertSuccessful();

    expect(Event::query()->withoutGlobalScopes()->where('name', 'like', '[TEST]%')->count())->toBe(0)
        ->and(Customer::query()->where('phone', 'like', '+22500%')->count())->toBe(0)
        ->and(Bus::query()->withoutGlobalScopes()->where('plate', 'like', 'TEST %')->count())->toBe(0)
        ->and(Staff::query()->withoutGlobalScopes()->where('employee_number', 'like', 'TEST-%')->count())->toBe(0)
        ->and($realBus->fresh())->not->toBeNull()
        ->and($realCustomer->fresh())->not->toBeNull();
});
