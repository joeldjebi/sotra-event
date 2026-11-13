<?php

namespace App\Livewire\Admin\Events;

use App\Enums\EventStatus;
use App\Enums\Permission;
use App\Enums\Role;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Company;
use App\Models\Event;
use App\Models\Promoter;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Étape 1 · Informations (AD-01).
 */
class InformationStep extends Component
{
    use AuthorizesPermission;

    public ?Event $event = null;

    public string $name = '';

    public string $description = '';

    public ?int $venue_id = null;

    public string $date = '';

    public string $start_time = '18:00';

    public string $end_time = '23:00';

    public string $doors_time = '';

    public string $estimated_passes = '';

    public ?int $promoter_id = null;

    public ?int $transport_lead_id = null;

    public ?int $company_id = null;

    public string $commission_rate = '10';

    public string $fee_rate = '';

    protected function permission(): Permission
    {
        return Permission::EventsManage;
    }

    public function mount(?Event $event = null): void
    {
        $this->event = $event;
        $this->fee_rate = (string) config('eventtrans.events.default_fee_rate');

        if ($event === null) {
            return;
        }

        $this->fill([
            'name' => $event->name,
            'description' => (string) $event->description,
            'venue_id' => $event->venue_id,
            'date' => $event->starts_at->toDateString(),
            'start_time' => $event->starts_at->format('H:i'),
            'end_time' => $event->ends_at->format('H:i'),
            'doors_time' => (string) $event->doors_open_at?->format('H:i'),
            'estimated_passes' => (string) $event->estimated_passes,
            'promoter_id' => $event->promoter_id,
            'transport_lead_id' => $event->transport_lead_id,
            'company_id' => $event->company_id,
            'commission_rate' => (string) (float) $event->commission_rate,
            'fee_rate' => (string) (float) $event->fee_rate,
        ]);
    }

    public function updatedCompanyId(): void
    {
        $company = Company::query()->find($this->company_id);

        if ($company !== null) {
            $this->commission_rate = (string) (float) $company->default_commission_rate;
        }
    }

    public function updatedPromoterId(): void
    {
        $this->transport_lead_id = null;
    }

    public function isLocked(): bool
    {
        return $this->event !== null && ! $this->event->status->isEditable();
    }

    public function save(): Redirector|RedirectResponse|null
    {
        abort_if($this->isLocked(), 403, 'Événement verrouillé : il a été proposé à la compagnie.');

        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'venue_id' => ['required', 'exists:venues,id'],
            'date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'doors_time' => ['nullable', 'date_format:H:i'],
            'estimated_passes' => ['nullable', 'integer', 'min:1'],
            'promoter_id' => ['required', 'exists:promoters,id'],
            'transport_lead_id' => ['nullable', Rule::exists('users', 'id')->where('promoter_id', $this->promoter_id)],
            'company_id' => ['required', Rule::exists('companies', 'id')->where('is_active', true)],
            'commission_rate' => ['required', 'numeric', 'between:0,100'],
            'fee_rate' => ['required', 'numeric', 'between:0,100'],
        ], attributes: [
            'name' => 'nom', 'venue_id' => 'lieu', 'start_time' => 'heure de début', 'end_time' => 'fin prévue',
            'promoter_id' => 'promoteur', 'transport_lead_id' => 'chef de projet transport', 'company_id' => 'compagnie',
            'commission_rate' => 'commission', 'fee_rate' => 'frais de paiement',
        ]);

        $startsAt = Carbon::parse("{$this->date} {$this->start_time}");
        $endsAt = Carbon::parse("{$this->date} {$this->end_time}");

        if ($endsAt->lte($startsAt)) {
            $endsAt->addDay();
        }

        $attributes = [
            'name' => $this->name,
            'description' => $this->description ?: null,
            'venue_id' => $this->venue_id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'doors_open_at' => $this->doors_time !== '' ? Carbon::parse("{$this->date} {$this->doors_time}") : null,
            'estimated_passes' => $this->estimated_passes !== '' ? (int) $this->estimated_passes : null,
            'promoter_id' => $this->promoter_id,
            'transport_lead_id' => $this->transport_lead_id,
            'company_id' => $this->company_id,
            'commission_rate' => $this->commission_rate,
            'fee_rate' => $this->fee_rate,
        ];

        if ($this->event === null) {
            $event = Event::query()->create($attributes + [
                'slug' => $this->uniqueSlug($startsAt),
                'status' => EventStatus::Draft,
                'channels' => [],
                'created_by' => auth()->id(),
            ]);

            session()->flash('flash', 'Événement créé. Ajoutez maintenant les points d\'embarquement.');

            return $this->redirectRoute('admin.events.edit', ['event' => $event, 'etape' => 2]);
        }

        DB::transaction(function () use ($attributes, $startsAt): void {
            $dayShift = (int) $this->event->starts_at->copy()->startOfDay()->diffInDays($startsAt->copy()->startOfDay(), false);
            $this->event->update($attributes);

            // Les horaires des points suivent un changement de date de l'événement.
            if ($dayShift !== 0) {
                foreach ($this->event->boardingPoints as $point) {
                    $point->update([
                        'departure_at' => $point->departure_at->copy()->addDays($dayShift),
                        'return_departure_at' => $point->return_departure_at->copy()->addDays($dayShift),
                    ]);
                }
            }
        });

        $this->dispatch('event-updated');
        $this->notify('Informations enregistrées.');

        return null;
    }

    private function uniqueSlug(Carbon $startsAt): string
    {
        $base = Str::slug($this->name).'-'.$startsAt->format('Y-m-d');
        $slug = $base;
        $suffix = 2;

        while (Event::query()->withoutGlobalScopes()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    public function render(): View
    {
        return view('livewire.admin.events.information-step', [
            'venues' => Venue::query()->orderBy('name')->pluck('name', 'id')->all(),
            'promoters' => Promoter::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'companies' => Company::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'transportLeads' => $this->promoter_id === null ? [] : User::query()
                ->where('promoter_id', $this->promoter_id)
                ->role(Role::TransportLead->value)
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (User $user): array => [$user->id => $user->name.($user->phone ? ' · '.$user->phone : '')])
                ->all(),
            'locked' => $this->isLocked(),
        ]);
    }
}
