<?php

namespace App\Livewire\Company;

use App\Enums\Offer;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Company;
use App\Models\CompanyLine;
use App\Models\Scopes\CompanyScope;
use App\Models\Venue;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace compagnie · Exploitation · Lignes par défaut : dans les 13 communes d'Abidjan, les lieux d'embarquement
 * habituels (plusieurs par commune), chacun vers une destination (un lieu d'événement, ou tous les lieux), avec
 * ses bus par offre ; plus les places par bus de chaque offre. Une compagnie qui a des lignes vers le lieu d'un
 * événement accepte d'office cet événement.
 */
class DefaultLines extends Component
{
    use AuthorizesPermission;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $commune_code = '';

    /** Destination : id du lieu d'événement ('' : tous les lieux). */
    public string $venue_id = '';

    public string $place = '';

    public string $latitude = '';

    public string $longitude = '';

    /** @var array<string, string> nombre de bus indexé par offre */
    public array $buses = [];

    /** @var array<string, string> places par bus indexées par offre */
    public array $seats = [];

    protected function permission(): Permission
    {
        return Permission::OperationsView;
    }

    protected function managePermission(): Permission
    {
        return Permission::OperationsManage;
    }

    public function mount(): void
    {
        $company = $this->company();

        foreach (Offer::cases() as $offer) {
            $this->seats[$offer->value] = (string) $company->seatsPerBus($offer);
        }
    }

    public function create(string $communeCode = ''): void
    {
        $this->authorizeManage();
        $this->reset(['editingId', 'commune_code', 'venue_id', 'place', 'latitude', 'longitude']);
        $this->commune_code = array_key_exists($communeCode, $this->communes()) ? $communeCode : '';
        $this->buses = collect(Offer::cases())->mapWithKeys(fn (Offer $offer) => [$offer->value => '0'])->all();
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $lineId): void
    {
        $this->authorizeManage();
        $line = CompanyLine::query()->findOrFail($lineId);
        $this->resetValidation();
        $this->editingId = $line->id;
        $this->commune_code = $line->commune_code;
        $this->venue_id = (string) $line->venue_id;
        $this->place = $line->place;
        $this->latitude = (string) $line->location->getLatitude();
        $this->longitude = (string) $line->location->getLongitude();
        $this->buses = collect(Offer::cases())->mapWithKeys(fn (Offer $offer) => [$offer->value => (string) $line->busesFor($offer)])->all();
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $this->validate([
            'commune_code' => ['required', Rule::in(array_keys($this->communes()))],
            'venue_id' => ['nullable', Rule::exists('venues', 'id')],
            'place' => [
                'required', 'string', 'max:120',
                Rule::unique('company_lines', 'place')
                    ->where('company_id', CompanyScope::currentCompanyId())
                    ->where('commune_code', $this->commune_code)
                    ->where(fn ($query) => $this->venue_id === '' ? $query->whereNull('venue_id') : $query->where('venue_id', (int) $this->venue_id))
                    ->ignore($this->editingId),
            ],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'buses' => ['required', 'array'],
            'buses.*' => ['required', 'integer', 'between:0,200'],
        ], [
            'place.unique' => 'Cette ligne existe déjà (même lieu d\'embarquement et même destination).',
        ], ['commune_code' => 'commune', 'venue_id' => 'destination', 'place' => 'lieu d\'embarquement', 'buses.*' => 'nombre de bus']);

        $line = CompanyLine::query()->updateOrCreate(['id' => $this->editingId], [
            'commune_code' => $this->commune_code,
            'venue_id' => $this->venue_id !== '' ? (int) $this->venue_id : null,
            'place' => $this->place,
            'location' => Point::makeGeodetic((float) $this->latitude, (float) $this->longitude),
            'bus_counts' => collect(Offer::cases())->mapWithKeys(fn (Offer $offer) => [$offer->value => (int) ($this->buses[$offer->value] ?? 0)])->all(),
        ]);

        $this->showForm = false;
        $this->notify("Ligne {$line->label()} enregistrée.");
    }

    public function delete(int $lineId): void
    {
        $this->authorizeManage();
        $line = CompanyLine::query()->findOrFail($lineId);
        $line->delete();
        $this->notify("Ligne {$line->label()} supprimée.");
    }

    public function saveSeats(): void
    {
        $this->authorizeManage();
        $this->validate([
            'seats' => ['required', 'array'],
            'seats.*' => ['required', 'integer', 'between:10,200'],
        ], ['seats.*.between' => 'Entre 10 et 200 places.'], ['seats.*' => 'places par bus']);

        $this->company()->update([
            'seats_per_bus' => collect(Offer::cases())->mapWithKeys(fn (Offer $offer) => [$offer->value => (int) $this->seats[$offer->value]])->all(),
        ]);

        $this->notify('Places par bus enregistrées.');
    }

    /**
     * @return array<string, string>
     */
    private function communes(): array
    {
        return (array) config('eventtrans.communes');
    }

    private function company(): Company
    {
        return Company::query()->findOrFail(CompanyScope::currentCompanyId());
    }

    public function render(): View
    {
        $lines = CompanyLine::query()->with('venue')->orderBy('place')->get()->groupBy('commune_code');

        return view('livewire.company.default-lines', [
            'communes' => $this->communes(),
            'linesByCommune' => $lines,
            'venues' => Venue::query()->orderBy('name')->pluck('name', 'id')->mapWithKeys(fn (string $name, int $id) => [(string) $id => $name])->all(),
            'offers' => Offer::cases(),
            'totals' => collect(Offer::cases())->mapWithKeys(fn (Offer $offer) => [$offer->value => $lines->flatten()->sum(fn (CompanyLine $line) => $line->busesFor($offer))])->all(),
            'lineCount' => $lines->flatten()->count(),
        ]);
    }
}
