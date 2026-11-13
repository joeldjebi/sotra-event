<?php

namespace App\Livewire\Admin\Events;

use App\Enums\DropZoneUsage;
use App\Enums\Permission;
use App\Enums\SalesChannel;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\DropZone;
use App\Models\Event;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Étape 4 · Canaux & publication (AD-04) : canaux de vente, zones de dépose et de reprise, visuel.
 * Reste modifiable après l'ouverture de la vente (affiner une zone de reprise, changer le visuel).
 */
class PublicationStep extends Component
{
    use AuthorizesPermission, WithFileUploads;

    public Event $event;

    /** @var array<int, string> Cases cochées : Livewire peut laisser des trous dans les clés. */
    public array $channels = [];

    public bool $featured = false;

    /** @var mixed Fichier temporaire Livewire */
    public $visual = null;

    public bool $showZoneForm = false;

    public ?int $editingZoneId = null;

    public string $zone_name = '';

    public string $zone_usage = 'depose';

    public string $zone_time = '';

    public string $zone_latitude = '';

    public string $zone_longitude = '';

    public string $zone_radius = '150';

    protected function permission(): Permission
    {
        return Permission::EventsManage;
    }

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->channels = $event->channels ?? [];
        $this->featured = (bool) $event->featured;
    }

    public function saveChannels(): void
    {
        $this->validate([
            'channels' => ['array'],
            'channels.*' => [Rule::in(array_map(fn (SalesChannel $channel) => $channel->value, array_filter(SalesChannel::cases(), fn (SalesChannel $channel) => $channel->isAvailable())))],
            'featured' => ['boolean'],
            'visual' => ['nullable', 'image', 'max:4096'],
        ], attributes: ['visual' => 'visuel']);

        $attributes = ['channels' => array_values($this->channels), 'featured' => $this->featured];

        if ($this->visual !== null) {
            if ($this->event->visual_path) {
                Storage::disk('public')->delete($this->event->visual_path);
            }

            $attributes['visual_path'] = $this->visual->store('events', 'public');
            $this->visual = null;
        }

        $this->event->update($attributes);
        $this->dispatch('event-updated');
        $this->notify('Publication enregistrée.');
    }

    public function createZone(): void
    {
        $this->reset(['editingZoneId', 'zone_name', 'zone_usage', 'zone_time', 'zone_latitude', 'zone_longitude', 'zone_radius']);
        $this->resetValidation();
        $this->showZoneForm = true;
    }

    public function editZone(int $zoneId): void
    {
        $zone = $this->event->dropZones()->findOrFail($zoneId);
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
            $scheduledAt = Carbon::parse($this->event->starts_at->toDateString().' '.$this->zone_time);

            if ($scheduledAt->lt($this->event->starts_at)) {
                $scheduledAt->addDay();
            }
        }

        $zone = DropZone::query()->updateOrCreate(['id' => $this->editingZoneId, 'event_id' => $this->event->id], [
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
        $this->event->dropZones()->findOrFail($zoneId)->delete();
        $this->dispatch('event-updated');
        $this->notify('Zone supprimée.');
    }

    public function render(): View
    {
        return view('livewire.admin.events.publication-step', [
            'availableChannels' => SalesChannel::cases(),
            'zones' => $this->event->dropZones()->orderBy('usage')->get(),
            'usages' => DropZoneUsage::options(),
        ]);
    }
}
