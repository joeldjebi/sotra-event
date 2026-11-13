<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StaffRole;
use App\Models\Company;
use App\Models\Promoter;
use App\Models\Staff;
use App\Models\User;
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
    }
}
