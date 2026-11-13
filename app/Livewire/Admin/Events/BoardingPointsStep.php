<?php

namespace App\Livewire\Admin\Events;

use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\BoardingPoint;
use App\Models\Event;
use App\Services\ZoneResolver;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Étape 2 · Points d'embarquement (AD-02, RG-06) : commune, lieu de montée, horaires aller et retour,
 * zone calculée depuis la distance au lieu (forçage justifié possible). Pas de quota par point (RG-08).
 */
class BoardingPointsStep extends Component
{
    use AuthorizesPermission;

    public Event $event;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $commune_code = '';

    public string $place = '';

    public string $latitude = '';

    public string $longitude = '';

    public string $departure_time = '15:30';

    public string $return_time = '23:30';

    public string $travel_minutes = '';

    public bool $zone_forced = false;

    public string $forced_zone = '1';

    public string $zone_justification = '';

    protected function permission(): Permission
    {
        return Permission::EventsManage;
    }

    public function mount(Event $event): void
    {
        $this->event = $event;
    }

    public function create(): void
    {
        $this->ensureEditable();
        $this->reset(['editingId', 'commune_code', 'place', 'latitude', 'longitude', 'departure_time', 'return_time', 'travel_minutes', 'zone_forced', 'forced_zone', 'zone_justification']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $pointId): void
    {
        $this->ensureEditable();
        $point = $this->event->boardingPoints()->findOrFail($pointId);
        $this->resetValidation();
        $this->editingId = $point->id;
        $this->commune_code = $point->commune_code;
        $this->place = $point->place;
        $this->latitude = (string) $point->location->getLatitude();
        $this->longitude = (string) $point->location->getLongitude();
        $this->departure_time = $point->departure_at->format('H:i');
        $this->return_time = $point->return_departure_at->format('H:i');
        $this->travel_minutes = (string) $point->travel_minutes;
        $this->zone_forced = $point->zone_forced;
        $this->forced_zone = (string) $point->zone;
        $this->zone_justification = (string) $point->zone_justification;
        $this->showForm = true;
    }

    public function save(ZoneResolver $zones): void
    {
        $this->ensureEditable();

        $this->validate([
            'commune_code' => ['required', Rule::in(array_keys((array) config('eventtrans.communes')))],
            'place' => ['required', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'departure_time' => ['required', 'date_format:H:i'],
            'return_time' => ['required', 'date_format:H:i'],
            'travel_minutes' => ['nullable', 'integer', 'between:5,300'],
            'zone_forced' => ['boolean'],
            'forced_zone' => [Rule::requiredIf($this->zone_forced), Rule::in(array_map('strval', ZoneResolver::zones()))],
            'zone_justification' => [Rule::requiredIf($this->zone_forced), 'nullable', 'string', 'max:255'],
        ], attributes: [
            'commune_code' => 'commune', 'place' => 'lieu de montée', 'departure_time' => 'départ aller',
            'return_time' => 'départ retour', 'travel_minutes' => 'durée de trajet', 'zone_justification' => 'justification',
        ]);

        [$departureAt, $returnAt] = $this->schedule();

        $location = Point::makeGeodetic((float) $this->latitude, (float) $this->longitude);
        $distance = $zones->distanceKm($this->event->venue->location, $location);

        $point = BoardingPoint::query()->updateOrCreate(['id' => $this->editingId, 'event_id' => $this->event->id], [
            'commune' => config('eventtrans.communes')[$this->commune_code],
            'commune_code' => $this->commune_code,
            'place' => $this->place,
            'location' => $location,
            'distance_km' => $distance,
            'zone' => $this->zone_forced ? (int) $this->forced_zone : $zones->zoneFor($distance),
            'zone_forced' => $this->zone_forced,
            'zone_justification' => $this->zone_forced ? $this->zone_justification : null,
            'departure_at' => $departureAt,
            'return_departure_at' => $returnAt,
            'travel_minutes' => $this->travel_minutes !== '' ? (int) $this->travel_minutes : $zones->estimatedTravelMinutes($distance),
        ] + ($this->editingId === null ? ['position' => (int) $this->event->boardingPoints()->max('position') + 1] : []));

        $this->showForm = false;
        $this->dispatch('event-updated');
        $this->notify("{$point->commune} · {$point->place} : zone {$point->zone} ({$point->distance_km} km).");
    }

    public function delete(int $pointId): void
    {
        $this->ensureEditable();
        $point = $this->event->boardingPoints()->findOrFail($pointId);
        $point->delete();
        $this->dispatch('event-updated');
        $this->notify("Point {$point->commune} supprimé.");
    }

    /**
     * Compose les horaires avec la date de l'événement. Le retour passe au lendemain s'il est plus tôt
     * que l'aller (ex. 00 h 30). Contrôles AD-06 : aller avant le début, retour après la fin prévue.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function schedule(): array
    {
        $day = $this->event->starts_at->toDateString();
        $departureAt = Carbon::parse("{$day} {$this->departure_time}");
        $returnAt = Carbon::parse("{$day} {$this->return_time}");

        if ($returnAt->lte($departureAt)) {
            $returnAt->addDay();
        }

        $errors = [];

        if ($departureAt->gte($this->event->starts_at)) {
            $errors['departure_time'] = 'Le départ aller doit précéder le début de l\'événement ('.$this->event->starts_at->format('H\hi').').';
        }

        if ($returnAt->lt($this->event->ends_at)) {
            $errors['return_time'] = 'Le départ retour doit être après la fin prévue ('.$this->event->ends_at->format('H\hi').') (AD-06).';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [$departureAt, $returnAt];
    }

    private function ensureEditable(): void
    {
        abort_unless($this->event->status->isEditable(), 403, 'Événement verrouillé : il a été proposé à la compagnie.');
    }

    public function render(): View
    {
        return view('livewire.admin.events.boarding-points-step', [
            'points' => $this->event->boardingPoints()->get(),
            'communes' => collect((array) config('eventtrans.communes'))->mapWithKeys(fn ($name, $code) => [$code => "{$name} ({$code})"])->all(),
            'zoneOptions' => collect(ZoneResolver::zones())->mapWithKeys(fn (int $zone) => [(string) $zone => "Zone {$zone} · ".ZoneResolver::zoneLabel($zone)])->all(),
            'locked' => ! $this->event->status->isEditable(),
            'leadMinutes' => (int) config('eventtrans.events.boarding_lead_minutes'),
        ]);
    }
}
