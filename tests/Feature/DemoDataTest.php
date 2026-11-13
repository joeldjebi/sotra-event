<?php

use App\Enums\PayoutStatus;
use App\Models\BreakdownAlert;
use App\Models\Event;
use App\Models\Payout;
use App\Models\Review;
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
