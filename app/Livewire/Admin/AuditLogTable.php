<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Console ACCES EVENT · Journal d'audit (NF-04), en lecture seule.
 */
class AuditLogTable extends Component
{
    use ManagesRecords;

    #[Url(except: '')]
    public string $subjectType = '';

    /**
     * Libellés des types d'objets journalisés.
     *
     * @var array<string, string>
     */
    public const SUBJECT_LABELS = [
        'App\Models\Company' => 'Compagnie',
        'App\Models\Promoter' => 'Promoteur',
        'App\Models\User' => 'Utilisateur',
        'App\Models\Staff' => 'Personnel',
        'App\Models\Venue' => 'Lieu',
        'App\Models\Depot' => 'Dépôt',
        'App\Models\Bus' => 'Bus',
        'App\Models\TechTeam' => 'Équipe technique',
        'App\Models\Device' => 'Terminal',
    ];

    protected function permission(): Permission
    {
        return Permission::AuditView;
    }

    public function updatedSubjectType(): void
    {
        $this->resetPage();
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            'created' => 'Création',
            'updated' => 'Modification',
            'deleted' => 'Suppression',
            'roles_changed' => 'Rôles modifiés',
            'two_factor_reset' => '2FA réinitialisée',
            'device_blocked' => 'Terminal bloqué',
            'device_unblocked' => 'Terminal débloqué',
            'staff_imported' => 'Import du personnel',
            'buses_imported' => 'Import des bus',
            default => $action,
        };
    }

    public function render(): View
    {
        return view('livewire.admin.audit-log-table', [
            'logs' => AuditLog::query()
                ->with('actor')
                ->when($this->subjectType !== '', fn ($query) => $query->where('auditable_type', $this->subjectType))
                ->latest('id')
                ->paginate(25),
            'subjectTypes' => self::SUBJECT_LABELS,
            'morphMap' => Relation::morphMap(),
        ]);
    }
}
