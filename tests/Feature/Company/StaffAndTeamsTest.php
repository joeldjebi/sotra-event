<?php

use App\Enums\DeviceStatus;
use App\Enums\Role;
use App\Enums\StaffRole;
use App\Livewire\Company\DevicesTable;
use App\Livewire\Company\StaffTable;
use App\Livewire\Company\TechTeamsTable;
use App\Models\Company;
use App\Models\Device;
use App\Models\Staff;
use App\Models\TechTeam;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->company = Company::factory()->create();
    $this->actingAs(userWithRole(Role::Operations, ['company_id' => $this->company->id]));
});

it('ajoute un convoyeur avec un téléphone normalisé', function () {
    Livewire::test(StaffTable::class)
        ->call('create')
        ->set('role', 'convoyeur')
        ->set('first_name', 'Moussa')
        ->set('last_name', 'Bamba')
        ->set('phone', '07 00 00 00 01')
        ->call('save')
        ->assertHasNoErrors();

    expect(Staff::query()->sole())
        ->phone->toBe('+2250700000001')
        ->company_id->toBe($this->company->id);
});

it('refuse un téléphone déjà attribué, même dans une autre compagnie', function () {
    Staff::factory()->create(['phone' => '+2250700000001']);

    Livewire::test(StaffTable::class)
        ->call('create')
        ->set('first_name', 'A')
        ->set('last_name', 'B')
        ->set('phone', '0700000001')
        ->call('save')
        ->assertHasErrors('phone');
});

it('révoque l\'accès mobile d\'un membre désactivé', function () {
    $staff = Staff::factory()->for($this->company)->create();
    $staff->createToken('Pixel', ['conveyor']);

    Livewire::test(StaffTable::class)
        ->call('edit', $staff->id)
        ->set('is_active', false)
        ->call('save')
        ->assertHasNoErrors();

    expect($staff->tokens()->count())->toBe(0);
});

it('importe le personnel et corrige le 0 initial supprimé par Excel', function () {
    $csv = "fonction,prenom,nom,telephone,matricule\n"
        ."Convoyeur,Moussa,Bamba,700000001,SOT-0001\n"
        ."technicien,Ibrahim,Traoré,+225 07 00 00 00 03,SOT-0003\n";

    Livewire::test(StaffTable::class)
        ->set('importFile', UploadedFile::fake()->createWithContent('personnel.csv', $csv))
        ->call('import')
        ->assertSet('importErrors', []);

    expect(Staff::query()->pluck('phone')->sort()->values()->all())->toBe(['+2250700000001', '+2250700000003'])
        ->and(Staff::query()->where('role', StaffRole::Technician)->count())->toBe(1);
});

it('compose une équipe technique avec ses techniciens', function () {
    $technicians = Staff::factory()->count(2)->for($this->company)->role(StaffRole::Technician)->create();
    $conveyor = Staff::factory()->for($this->company)->role(StaffRole::Conveyor)->create();

    Livewire::test(TechTeamsTable::class)
        ->call('create')
        ->set('code', 't3')
        ->set('members', $technicians->pluck('id')->map(fn ($id) => (string) $id)->all())
        ->call('save')
        ->assertHasNoErrors();

    $team = TechTeam::query()->sole();

    expect($team->code)->toBe('T3')
        ->and($team->members()->count())->toBe(2);

    Livewire::test(TechTeamsTable::class)
        ->call('edit', $team->id)
        ->set('members', [(string) $conveyor->id])
        ->call('save')
        ->assertHasErrors('members.0');
});

it('bloque un terminal et déconnecte la personne qui l\'utilise', function () {
    $conveyor = Staff::factory()->for($this->company)->create();
    $conveyor->createToken('PDA', ['conveyor']);
    $device = Device::factory()->for($this->company)->create(['staff_id' => $conveyor->id]);

    Livewire::test(DevicesTable::class)
        ->call('confirmBlock', $device->id)
        ->call('block')
        ->assertHasErrors('blocked_reason')
        ->set('blocked_reason', 'Perdu au stade')
        ->call('block')
        ->assertHasNoErrors();

    expect($device->fresh()->status)->toBe(DeviceStatus::Blocked)
        ->and($conveyor->tokens()->count())->toBe(0);

    Livewire::test(DevicesTable::class)->call('unblock', $device->id);
    expect($device->fresh()->status)->toBe(DeviceStatus::Active);
});
