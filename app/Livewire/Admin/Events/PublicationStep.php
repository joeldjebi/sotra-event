<?php

namespace App\Livewire\Admin\Events;

use App\Enums\DropZoneUsage;
use App\Enums\Permission;
use App\Enums\SalesChannel;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Livewire\Concerns\EditsDropZones;
use App\Models\Event;
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
    use AuthorizesPermission, EditsDropZones, WithFileUploads;

    public Event $event;

    /** @var array<int, string> Cases cochées : Livewire peut laisser des trous dans les clés. */
    public array $channels = [];

    public bool $featured = false;

    /** @var mixed Fichier temporaire Livewire */
    public $visual = null;

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

    protected function dropZoneEvent(): Event
    {
        return $this->event;
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
