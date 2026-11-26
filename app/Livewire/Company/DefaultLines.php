<?php

namespace App\Livewire\Company;

use App\Enums\Offer;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Company;
use App\Models\CompanyLine;
use App\Models\Scopes\CompanyScope;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Espace compagnie · Exploitation · Lignes par défaut : dans chacune des 13 communes d'Abidjan, le lieu
 * d'embarquement habituel et le nombre de bus par offre, plus les places par bus de chaque offre.
 * Une compagnie qui a défini ses lignes accepte d'office les événements qu'on lui confie.
 */
class DefaultLines extends Component
{
    use AuthorizesPermission;

    public bool $showForm = false;

    public string $commune_code = '';

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

    public function edit(string $communeCode): void
    {
        $this->authorizeManage();
        abort_unless(array_key_exists($communeCode, $this->communes()), 404);

        $line = CompanyLine::query()->where('commune_code', $communeCode)->first();
        $this->resetValidation();
        $this->commune_code = $communeCode;
        $this->place = $line->place ?? '';
        $this->latitude = $line ? (string) $line->location->getLatitude() : '';
        $this->longitude = $line ? (string) $line->location->getLongitude() : '';

        foreach (Offer::cases() as $offer) {
            $this->buses[$offer->value] = (string) ($line?->busesFor($offer) ?? 0);
        }

        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorizeManage();
        $this->validate([
            'commune_code' => ['required', Rule::in(array_keys($this->communes()))],
            'place' => ['required', 'string', 'max:120'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'buses' => ['required', 'array'],
            'buses.*' => ['required', 'integer', 'between:0,200'],
        ], attributes: ['place' => 'lieu d\'embarquement', 'buses.*' => 'nombre de bus']);

        $busCounts = collect(Offer::cases())->mapWithKeys(fn (Offer $offer) => [$offer->value => (int) ($this->buses[$offer->value] ?? 0)])->all();

        CompanyLine::query()->updateOrCreate(
            ['company_id' => CompanyScope::currentCompanyId(), 'commune_code' => $this->commune_code],
            [
                'place' => $this->place,
                'location' => Point::makeGeodetic((float) $this->latitude, (float) $this->longitude),
                'bus_counts' => $busCounts,
            ],
        );

        $this->showForm = false;
        $this->notify("Ligne de {$this->communes()[$this->commune_code]} enregistrée.");
    }

    public function delete(string $communeCode): void
    {
        $this->authorizeManage();
        CompanyLine::query()->where('commune_code', $communeCode)->firstOrFail()->delete();
        $this->notify('Ligne supprimée.');
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
        $lines = CompanyLine::query()->get()->keyBy('commune_code');

        return view('livewire.company.default-lines', [
            'communes' => $this->communes(),
            'lines' => $lines,
            'offers' => Offer::cases(),
            'totals' => collect(Offer::cases())->mapWithKeys(fn (Offer $offer) => [$offer->value => $lines->sum(fn (CompanyLine $line) => $line->busesFor($offer))])->all(),
        ]);
    }
}
