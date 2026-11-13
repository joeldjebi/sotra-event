<?php

use App\Enums\Role;
use App\Enums\StaffRole;
use App\Models\Company;
use App\Models\Staff;

it('ne montre à une compagnie que son propre personnel', function () {
    $sotra = Company::factory()->create();
    $other = Company::factory()->create();
    Staff::factory()->count(3)->for($sotra)->create();
    Staff::factory()->count(2)->for($other)->create();

    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $sotra->id]));

    expect(Staff::query()->count())->toBe(3)
        ->and(Staff::query()->pluck('company_id')->unique()->all())->toBe([$sotra->id]);
});

it('laisse ACCES EVENT voir le personnel de toutes les compagnies', function () {
    Staff::factory()->count(2)->create();
    Staff::factory()->count(2)->create();

    $this->actingAs(userWithRole(Role::SuperAdmin));

    expect(Staff::query()->count())->toBe(4);
});

it('rattache automatiquement un nouveau membre à la compagnie de l\'utilisateur', function () {
    $sotra = Company::factory()->create();
    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $sotra->id]));

    $staff = Staff::query()->create([
        'role' => StaffRole::Conveyor,
        'first_name' => 'Moussa',
        'last_name' => 'Bamba',
        'phone' => '+2250700000001',
    ]);

    expect($staff->company_id)->toBe($sotra->id);
});

it('affiche les effectifs de sa seule compagnie sur l\'accueil', function () {
    $sotra = Company::factory()->create();
    Staff::factory()->count(2)->for($sotra)->role(StaffRole::Conveyor)->create();
    Staff::factory()->count(5)->role(StaffRole::Conveyor)->create();

    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $sotra->id]))
        ->get('/compagnie')
        ->assertOk()
        ->assertViewHas('staffCounts', fn ($counts) => $counts['Convoyeur'] === 2);
});
