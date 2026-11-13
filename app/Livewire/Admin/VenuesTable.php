<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\Venue;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Console ACCES EVENT · Lieux d'événements. La position GPS sert au calcul des zones tarifaires (RG-06).
 */
class VenuesTable extends Component
{
    use ManagesRecords;

    public string $name = '';

    public string $address = '';

    public string $city = 'Abidjan';

    public string $latitude = '';

    public string $longitude = '';

    protected function permission(): Permission
    {
        return Permission::EventsManage;
    }

    public function create(): void
    {
        $this->reset(['name', 'address', 'city', 'latitude', 'longitude', 'editingId']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $venueId): void
    {
        $venue = Venue::query()->findOrFail($venueId);
        $this->resetValidation();
        $this->editingId = $venue->id;
        $this->name = $venue->name;
        $this->address = (string) $venue->address;
        $this->city = $venue->city;
        $this->latitude = (string) $venue->location->getLatitude();
        $this->longitude = (string) $venue->location->getLongitude();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:150'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:80'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ], attributes: ['name' => 'nom', 'city' => 'ville']);

        $venue = Venue::query()->updateOrCreate(['id' => $this->editingId], [
            'name' => $this->name,
            'address' => $this->address ?: null,
            'city' => $this->city,
            'location' => Point::makeGeodetic((float) $this->latitude, (float) $this->longitude),
        ]);

        $this->showForm = false;
        $this->notify($this->editingId ? "{$venue->name} mis à jour." : "{$venue->name} ajouté.");
    }

    public function render(): View
    {
        return view('livewire.admin.venues-table', [
            'venues' => Venue::query()
                ->when($this->search !== '', fn ($query) => $query->whereRaw('lower(name) like ?', [$this->searchTerm()]))
                ->orderBy('name')
                ->paginate(15),
        ]);
    }
}
