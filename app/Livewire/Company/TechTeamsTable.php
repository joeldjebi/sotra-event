<?php

namespace App\Livewire\Company;

use App\Enums\Permission;
use App\Enums\StaffRole;
use App\Enums\TechTeamStatus;
use App\Enums\TechTeamType;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\Depot;
use App\Models\Scopes\CompanyScope;
use App\Models\Staff;
use App\Models\TechTeam;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace compagnie · Exploitation · Équipes du service technique (code, type, techniciens, disponibilité).
 * Le secteur couvert sera choisi par événement (étape 7).
 */
class TechTeamsTable extends Component
{
    use ManagesRecords;

    public string $code = '';

    public string $type = 'leger';

    public string $status = 'disponible';

    public ?int $depot_id = null;

    /** @var list<int|string> */
    public array $members = [];

    protected function permission(): Permission
    {
        return Permission::TechTeamsManage;
    }

    public function create(): void
    {
        $this->reset(['code', 'type', 'status', 'depot_id', 'members', 'editingId']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $teamId): void
    {
        $team = TechTeam::query()->with('members')->findOrFail($teamId);
        $this->resetValidation();
        $this->editingId = $team->id;
        $this->code = $team->code;
        $this->type = $team->type->value;
        $this->status = $team->status->value;
        $this->depot_id = $team->depot_id;
        $this->members = $team->members->pluck('id')->map(fn ($id) => (string) $id)->all();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->code = mb_strtoupper(trim($this->code));
        $companyId = CompanyScope::currentCompanyId();

        $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('tech_teams', 'code')->where('company_id', $companyId)->ignore($this->editingId)],
            'type' => ['required', Rule::enum(TechTeamType::class)],
            'status' => ['required', Rule::enum(TechTeamStatus::class)],
            'depot_id' => ['nullable', Rule::exists('depots', 'id')->where('company_id', $companyId)],
            'members' => ['array'],
            'members.*' => [Rule::exists('staff', 'id')->where('company_id', $companyId)->where('role', StaffRole::Technician->value)],
        ], attributes: ['code' => 'code', 'members' => 'techniciens', 'depot_id' => 'dépôt']);

        $team = DB::transaction(function (): TechTeam {
            $team = TechTeam::query()->updateOrCreate(['id' => $this->editingId], [
                'code' => $this->code,
                'type' => $this->type,
                'status' => $this->status,
                'depot_id' => $this->depot_id,
            ]);

            $memberIds = array_map('intval', $this->members);

            Staff::query()->where('tech_team_id', $team->id)->whereNotIn('id', $memberIds)->update(['tech_team_id' => null]);
            Staff::query()->whereIn('id', $memberIds)->update(['tech_team_id' => $team->id]);

            return $team;
        });

        $this->showForm = false;
        $this->notify($this->editingId ? "Équipe {$team->code} mise à jour." : "Équipe {$team->code} créée.");
    }

    public function render(): View
    {
        return view('livewire.company.tech-teams-table', [
            'teams' => TechTeam::query()
                ->with(['members', 'depot'])
                ->when($this->search !== '', fn ($query) => $query->whereRaw('lower(code) like ?', [$this->searchTerm()]))
                ->orderBy('code')
                ->paginate(20),
            'types' => TechTeamType::options(),
            'statuses' => TechTeamStatus::options(),
            'depots' => Depot::query()->orderBy('name')->pluck('name', 'id')->all(),
            'technicians' => Staff::query()->where('role', StaffRole::Technician)->where('is_active', true)->with('techTeam')->orderBy('last_name')->get(),
        ]);
    }
}
