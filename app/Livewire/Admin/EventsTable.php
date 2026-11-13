<?php

namespace App\Livewire\Admin;

use App\Enums\EventStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\Event;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Console ACCES EVENT · Événements : liste et accès à la configuration en 4 étapes (§ 7 CDC).
 */
class EventsTable extends Component
{
    use ManagesRecords;

    #[Url(except: '')]
    public string $statusFilter = '';

    protected function permission(): Permission
    {
        return Permission::EventsManage;
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.admin.events-table', [
            'events' => Event::query()
                ->with(['venue', 'company', 'promoter'])
                ->withCount('boardingPoints')
                ->when($this->statusFilter !== '', fn ($query) => $query->where('status', $this->statusFilter))
                ->when($this->search !== '', fn ($query) => $query->whereRaw('lower(name) like ?', [$this->searchTerm()]))
                ->orderByDesc('starts_at')
                ->paginate(15),
            'statuses' => EventStatus::options(),
        ]);
    }
}
