<?php

namespace App\Livewire\Promoter;

use App\Enums\EventRequestStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\EventRequest;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace promoteur · Demandes d'événement : le promoteur propose ses événements (nom, date, lieu, public attendu,
 * communes de départ souhaitées). ACCES EVENT les étudie, choisit la compagnie et fixe les prix dans la console.
 * Une demande se modifie ou se retire tant qu'elle est à l'étude.
 */
class EventRequests extends Component
{
    use ManagesRecords;

    public string $name = '';

    public string $description = '';

    public ?int $venue_id = null;

    public string $venue_name = '';

    public string $date = '';

    public string $start_time = '20:00';

    public string $end_time = '';

    public string $expected_attendance = '';

    public string $estimated_passes = '';

    public string $desired_communes = '';

    protected function permission(): Permission
    {
        return Permission::EventRequestsSubmit;
    }

    public function create(): void
    {
        $this->reset(['name', 'description', 'venue_id', 'venue_name', 'date', 'start_time', 'end_time', 'expected_attendance', 'estimated_passes', 'desired_communes', 'editingId']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $requestId): void
    {
        $request = $this->pending($requestId);
        $this->resetValidation();
        $this->editingId = $request->id;
        $this->fill([
            'name' => $request->name,
            'description' => (string) $request->description,
            'venue_id' => $request->venue_id,
            'venue_name' => (string) $request->venue_name,
            'date' => $request->starts_at->toDateString(),
            'start_time' => $request->starts_at->format('H:i'),
            'end_time' => (string) $request->ends_at?->format('H:i'),
            'expected_attendance' => (string) $request->expected_attendance,
            'estimated_passes' => (string) $request->estimated_passes,
            'desired_communes' => (string) $request->desired_communes,
        ]);
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'venue_name' => [Rule::requiredIf($this->venue_id === null), 'nullable', 'string', 'max:180'],
            'date' => ['required', 'date', 'after:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'expected_attendance' => ['nullable', 'integer', 'min:1'],
            'estimated_passes' => ['nullable', 'integer', 'min:1'],
            'desired_communes' => ['nullable', 'string', 'max:500'],
        ], [
            'venue_name.required' => 'Choisissez un lieu dans la liste ou indiquez-le.',
            'date.after' => 'La date doit être à venir.',
        ], [
            'name' => 'nom', 'venue_id' => 'lieu', 'venue_name' => 'lieu', 'start_time' => 'heure de début', 'end_time' => 'heure de fin',
            'expected_attendance' => 'public attendu', 'estimated_passes' => 'Pass transport estimés', 'desired_communes' => 'communes de départ',
        ]);

        $startsAt = Carbon::parse("{$this->date} {$this->start_time}");
        $endsAt = $this->end_time !== '' ? Carbon::parse("{$this->date} {$this->end_time}") : null;

        if ($endsAt?->lte($startsAt)) {
            $endsAt->addDay();
        }

        $attributes = [
            'name' => trim($this->name),
            'description' => trim($this->description) ?: null,
            'venue_id' => $this->venue_id,
            'venue_name' => $this->venue_id === null ? trim($this->venue_name) : null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'expected_attendance' => $this->expected_attendance !== '' ? (int) $this->expected_attendance : null,
            'estimated_passes' => $this->estimated_passes !== '' ? (int) $this->estimated_passes : null,
            'desired_communes' => trim($this->desired_communes) ?: null,
        ];

        if ($this->editingId === null) {
            EventRequest::query()->create($attributes + [
                'promoter_id' => auth()->user()->promoter_id,
                'submitted_by' => auth()->id(),
                'status' => EventRequestStatus::Submitted,
            ]);
        } else {
            $this->pending($this->editingId)->update($attributes);
        }

        $this->showForm = false;
        $this->notify($this->editingId === null ? 'Demande envoyée à ACCES EVENT.' : 'Demande mise à jour.');
    }

    public function withdraw(int $requestId): void
    {
        $this->pending($requestId)->update(['status' => EventRequestStatus::Withdrawn]);
        $this->notify('Demande retirée.');
    }

    /**
     * Demande de la structure encore à l'étude (le scope promoteur exclut celles des autres promoteurs).
     */
    private function pending(int $requestId): EventRequest
    {
        $request = EventRequest::query()->findOrFail($requestId);
        abort_unless($request->isPending(), 403, 'Cette demande a déjà été traitée.');

        return $request;
    }

    public function render(): View
    {
        return view('livewire.promoter.event-requests', [
            'requests' => EventRequest::query()
                ->with(['venue', 'event'])
                ->when($this->search !== '', fn ($query) => $query->whereRaw('lower(name) like ?', [$this->searchTerm()]))
                ->latest()
                ->paginate(15),
            'venues' => Venue::query()->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }
}
