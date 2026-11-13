<?php

namespace App\Livewire\Company\Quality;

use App\Enums\ComplaintStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\SelectsEvent;
use App\Models\Complaint;
use App\Models\User;
use App\Services\Quality\ComplaintService;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Espace compagnie · Direction qualité · Réclamations (QU-03) : statut Ouverte / En cours / Traitée,
 * bouton Traiter (prise en charge puis réponse envoyée par SMS), compteur ouvertes / traitées.
 */
class Complaints extends Component
{
    use AuthorizesPermission, SelectsEvent, WithPagination;

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    public ?int $handlingId = null;

    public bool $showHandling = false;

    public string $resolution = '';

    protected function permission(): Permission
    {
        return Permission::ComplaintsManage;
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedEventId(): void
    {
        $this->resetPage();
    }

    /**
     * Traiter : ouvre la réclamation et la passe « En cours ».
     */
    public function handle(int $complaintId, ComplaintService $complaints): void
    {
        $complaint = Complaint::query()->findOrFail($complaintId);
        $complaints->take($complaint, $this->user());
        $this->handlingId = $complaint->id;
        $this->resolution = (string) $complaint->resolution;
        $this->resetErrorBag();
        $this->showHandling = true;
    }

    public function resolve(ComplaintService $complaints): void
    {
        $this->validate(['resolution' => ['required', 'string', 'min:10', 'max:500']], attributes: ['resolution' => 'réponse']);
        $complaint = Complaint::query()->findOrFail($this->handlingId);

        if ($complaint->status === ComplaintStatus::Resolved) {
            $this->showHandling = false;

            return;
        }

        $complaints->resolve($complaint, $this->user(), trim($this->resolution));
        $this->reset(['showHandling', 'handlingId', 'resolution']);
        $this->notify("Réclamation {$complaint->number} traitée, réponse envoyée par SMS.");
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(): View
    {
        $event = $this->selectedEvent();
        $base = Complaint::query()->where('event_id', $event->id ?? 0);
        $relations = ['pass.boardingPoint', 'mission.bus', 'mission.boardingPoint', 'customer', 'handler'];

        return view('livewire.company.quality.complaints', [
            'event' => $event,
            'eventOptions' => $this->eventOptions(),
            'complaints' => (clone $base)->with($relations)
                ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
                ->orderByRaw("case status when 'ouverte' then 0 when 'en_cours' then 1 else 2 end")
                ->latest('id')
                ->paginate(20),
            'statuses' => ComplaintStatus::options(),
            'counts' => [
                'open' => (clone $base)->where('status', '!=', ComplaintStatus::Resolved)->count(),
                'resolved' => (clone $base)->where('status', ComplaintStatus::Resolved)->count(),
            ],
            'handling' => $this->handlingId ? Complaint::query()->with($relations)->find($this->handlingId) : null,
        ]);
    }
}
