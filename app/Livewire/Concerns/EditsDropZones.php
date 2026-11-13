<?php

namespace App\Livewire\Concerns;

use App\Enums\DropZoneUsage;
use App\Models\DropZone;
use App\Models\Event;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * Zones de dépose et de reprise d'un événement (AD-04, PR-05) : ajout, modification, suppression.
 * Partagé par la console ACCES EVENT et l'espace promoteur.
 */
trait EditsDropZones
{
    public bool $showZoneForm = false;

    public ?int $editingZoneId = null;

    public string $zone_name = '';

    public string $zone_usage = 'depose';

    public string $zone_time = '';

    public string $zone_latitude = '';

    public string $zone_longitude = '';

    public string $zone_radius = '150';

    abstract protected function dropZoneEvent(): Event;

    public function createZone(): void
    {
        $this->reset(['editingZoneId', 'zone_name', 'zone_usage', 'zone_time', 'zone_latitude', 'zone_longitude', 'zone_radius']);
        $this->resetValidation();
        $this->showZoneForm = true;
    }

    public function editZone(int $zoneId): void
    {
        $zone = $this->dropZoneEvent()->dropZones()->findOrFail($zoneId);
        $this->resetValidation();
        $this->editingZoneId = $zone->id;
        $this->zone_name = $zone->name;
        $this->zone_usage = $zone->usage->value;
        $this->zone_time = (string) $zone->scheduled_at?->format('H:i');
        $this->zone_latitude = (string) $zone->location->getLatitude();
        $this->zone_longitude = (string) $zone->location->getLongitude();
        $this->zone_radius = (string) $zone->radius_m;
        $this->showZoneForm = true;
    }

    public function saveZone(): void
    {
        $event = $this->dropZoneEvent();

        $this->validate([
            'zone_name' => ['required', 'string', 'max:120'],
            'zone_usage' => ['required', Rule::enum(DropZoneUsage::class)],
            'zone_time' => ['nullable', 'date_format:H:i'],
            'zone_latitude' => ['required', 'numeric', 'between:-90,90'],
            'zone_longitude' => ['required', 'numeric', 'between:-180,180'],
            'zone_radius' => ['required', 'integer', 'between:20,2000'],
        ], attributes: ['zone_name' => 'nom', 'zone_usage' => 'usage', 'zone_time' => 'heure', 'zone_latitude' => 'latitude', 'zone_longitude' => 'longitude', 'zone_radius' => 'rayon']);

        $scheduledAt = null;

        if ($this->zone_time !== '') {
            $scheduledAt = Carbon::parse($event->starts_at->toDateString().' '.$this->zone_time);

            if ($scheduledAt->lt($event->starts_at)) {
                $scheduledAt->addDay();
            }
        }

        if ($this->editingZoneId !== null) {
            $event->dropZones()->findOrFail($this->editingZoneId);
        }

        $zone = DropZone::query()->updateOrCreate(['id' => $this->editingZoneId, 'event_id' => $event->id], [
            'name' => $this->zone_name,
            'usage' => $this->zone_usage,
            'scheduled_at' => $scheduledAt,
            'location' => Point::makeGeodetic((float) $this->zone_latitude, (float) $this->zone_longitude),
            'radius_m' => (int) $this->zone_radius,
        ]);

        $this->showZoneForm = false;
        $this->dispatch('event-updated');
        $this->notify("Zone « {$zone->name} » enregistrée.");
    }

    public function deleteZone(int $zoneId): void
    {
        $this->dropZoneEvent()->dropZones()->findOrFail($zoneId)->delete();
        $this->dispatch('event-updated');
        $this->notify('Zone supprimée.');
    }
}
