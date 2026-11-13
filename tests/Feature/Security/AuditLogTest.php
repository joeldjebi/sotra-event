<?php

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('journalise la modification d\'une compagnie avec l\'auteur et les valeurs (NF-04)', function () {
    $admin = userWithRole(Role::SuperAdmin);
    $company = Company::factory()->create(['default_commission_rate' => 10]);
    $this->actingAs($admin);

    $company->update(['default_commission_rate' => 12]);

    $log = AuditLog::query()->where('action', 'updated')->where('auditable_id', $company->id)->sole();

    expect($log->actor_id)->toBe($admin->id)
        ->and($log->old_values)->toBe(['default_commission_rate' => '10.00'])
        ->and($log->new_values)->toBe(['default_commission_rate' => '12.00']);
});

it('ne journalise jamais les secrets', function () {
    $user = userWithRole(Role::Operator);

    $user->update(['password' => 'NouveauMotDePasse123']);

    expect(AuditLog::query()->where('auditable_id', $user->id)->get()->pluck('new_values')->filter()->collapse()->keys())
        ->not->toContain('password', 'two_factor_secret', 'remember_token');
});

it('interdit de modifier ou supprimer une entrée par le modèle', function () {
    $log = AuditLog::record('test');

    expect(fn () => $log->delete())->toThrow(LogicException::class);

    $log->action = 'altéré';
    expect(fn () => $log->save())->toThrow(LogicException::class);
});

it('interdit de modifier ou supprimer une entrée directement en base', function () {
    $log = AuditLog::record('test');

    expect(fn () => DB::table('audit_logs')->where('id', $log->id)->update(['action' => 'altéré']))
        ->toThrow(QueryException::class);
});
