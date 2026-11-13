<?php

namespace Database\Seeders;

use App\Enums\BusCategory;
use App\Enums\BusStatus;
use App\Enums\DeviceStatus;
use App\Enums\Role;
use App\Enums\StaffRole;
use App\Enums\TechTeamStatus;
use App\Enums\TechTeamType;
use App\Models\Bus;
use App\Models\Company;
use App\Models\Depot;
use App\Models\Device;
use App\Models\Promoter;
use App\Models\Staff;
use App\Models\TechTeam;
use App\Models\User;
use App\Models\Venue;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration (local et recette uniquement). Mot de passe de tous les comptes : « password ».
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $sotra = Company::query()->updateOrCreate(['slug' => 'sotra'], [
            'name' => 'SOTRA',
            'email' => 'contact@sotra.test',
            'phone' => '+2252720000000',
            'default_commission_rate' => 10,
        ]);

        $promoter = Promoter::query()->updateOrCreate(['name' => 'Promoteur Démo'], [
            'email' => 'contact@promoteur.test',
            'phone' => '+2250700000099',
        ]);

        $accounts = [
            ['Awa Admin', 'admin@eventtrans.test', Role::SuperAdmin, []],
            ['Olivier Opérateur', 'operateur@eventtrans.test', Role::Operator, []],
            ['Clarisse Commerciale', 'commercial@sotra.test', Role::Commercial, ['company_id' => $sotra->id]],
            ['Quentin Qualité', 'qualite@sotra.test', Role::Quality, ['company_id' => $sotra->id]],
            ['Edouard Exploitation', 'exploitation@sotra.test', Role::Operations, ['company_id' => $sotra->id]],
            ['Paul Chef de projet', 'chef.projet@promoteur.test', Role::TransportLead, ['promoter_id' => $promoter->id]],
        ];

        foreach ($accounts as [$name, $email, $role, $attributes]) {
            $user = User::query()->updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => 'password',
                'is_active' => true,
                ...$attributes,
            ]);
            $user->syncRoles([$role->value]);
        }

        $staff = [
            [StaffRole::Conveyor, 'Moussa', 'Bamba', '+2250700000001', 'SOT-0001'],
            [StaffRole::Conveyor, 'Kouassi', 'Yao', '+2250700000002', 'SOT-0002'],
            [StaffRole::Technician, 'Ibrahim', 'Traoré', '+2250700000003', 'SOT-0003'],
            [StaffRole::Driver, 'Adama', 'Koné', '+2250700000004', 'SOT-0004'],
            [StaffRole::Driver, 'Seydou', 'Traoré', '+2250700000005', 'SOT-0005'],
            [StaffRole::Technician, 'Jean', 'Kouassi', '+2250700000006', 'SOT-0006'],
        ];

        foreach ($staff as [$role, $firstName, $lastName, $phone, $employeeNumber]) {
            Staff::query()->withoutGlobalScopes()->updateOrCreate(['phone' => $phone], [
                'company_id' => $sotra->id,
                'role' => $role,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'employee_number' => $employeeNumber,
            ]);
        }

        // Coordonnées approximatives, à vérifier sur le terrain.
        Venue::query()->updateOrCreate(['name' => 'Stade Olympique d\'Ebimpé'], [
            'address' => 'Ebimpé, Anyama',
            'city' => 'Abidjan',
            'location' => Point::makeGeodetic(5.4925, -4.0791),
        ]);

        $depot = Depot::query()->withoutGlobalScopes()->updateOrCreate(
            ['company_id' => $sotra->id, 'name' => 'Dépôt de Yopougon'],
            ['address' => 'Yopougon', 'phone' => '+2252723000000', 'location' => Point::makeGeodetic(5.3364, -4.0756)],
        );

        $buses = [
            ['12', '1201 AB 01', BusStatus::InService], ['27', '2701 AB 01', BusStatus::InService],
            ['08', '0801 AB 01', BusStatus::InService], ['33', '3301 AB 01', BusStatus::InService],
            ['19', '1901 AB 01', BusStatus::InService], ['41', '4101 AB 01', BusStatus::Reserve],
            ['52', '5201 AB 01', BusStatus::Reserve],
        ];

        foreach ($buses as [$number, $plate, $status]) {
            Bus::query()->withoutGlobalScopes()->updateOrCreate(['plate' => $plate], [
                'company_id' => $sotra->id,
                'depot_id' => $depot->id,
                'number' => $number,
                'capacity' => 50,
                'category' => BusCategory::Standard,
                'status' => $status,
            ]);
        }

        foreach (['T1' => TechTeamType::Heavy, 'T3' => TechTeamType::Light] as $code => $type) {
            $team = TechTeam::query()->withoutGlobalScopes()->updateOrCreate(
                ['company_id' => $sotra->id, 'code' => $code],
                ['type' => $type, 'status' => TechTeamStatus::Available, 'depot_id' => $depot->id],
            );

            Staff::query()->withoutGlobalScopes()
                ->where('phone', $code === 'T1' ? '+2250700000006' : '+2250700000003')
                ->update(['tech_team_id' => $team->id]);
        }

        Device::query()->withoutGlobalScopes()->updateOrCreate(['serial_number' => 'PDA-00000012'], [
            'company_id' => $sotra->id,
            'label' => 'PDA 12',
            'model' => 'Zebra TC21',
            'status' => DeviceStatus::Active,
            'staff_id' => Staff::query()->withoutGlobalScopes()->where('phone', '+2250700000001')->value('id'),
        ]);
    }
}
