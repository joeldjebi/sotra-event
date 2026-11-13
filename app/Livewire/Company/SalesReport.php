<?php

namespace App\Livewire\Company;

use App\Enums\EventStatus;
use App\Enums\Permission;
use App\Exports\EventSalesExport;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Event;
use App\Services\Reporting\EventSalesReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Espace compagnie · Direction commerciale · Ventes (CO-01 à CO-03, CO-06).
 */
class SalesReport extends Component
{
    use AuthorizesPermission;

    #[Url(as: 'evenement')]
    public ?int $eventId = null;

    protected function permission(): Permission
    {
        return Permission::SalesView;
    }

    public function mount(): void
    {
        $this->eventId ??= $this->events()->first()?->id;
    }

    public function export(): BinaryFileResponse
    {
        $event = $this->events()->findOrFail($this->eventId);

        return Excel::download(new EventSalesExport($event), 'ventes-'.Str::slug($event->name).'.xlsx');
    }

    /**
     * Événements de la compagnie qui ont des ventes (le scope compagnie s'applique).
     *
     * @return Builder<Event>
     */
    private function events()
    {
        return Event::query()->whereIn('status', [EventStatus::OnSale, EventStatus::Finished])->orderByDesc('starts_at');
    }

    public function render(EventSalesReport $reports): View
    {
        $event = $this->eventId ? $this->events()->with(['venue', 'offers', 'boardingPoints'])->find($this->eventId) : null;

        return view('livewire.company.sales-report', [
            'event' => $event,
            'eventOptions' => $this->events()->pluck('name', 'id')->all(),
            'report' => $event ? $reports->build($event) : null,
        ]);
    }
}
