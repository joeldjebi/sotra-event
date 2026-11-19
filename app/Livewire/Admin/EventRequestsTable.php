<?php

namespace App\Livewire\Admin;

use App\Contracts\SmsSender;
use App\Enums\EventRequestStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\ManagesRecords;
use App\Models\EventRequest;
use App\Support\PhoneNumber;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Console ACCES EVENT · Demandes d'événement des promoteurs. Accepter ouvre la création de l'événement,
 * préremplie avec la demande (la compagnie, les points et la grille restent à fixer) ; refuser exige un motif.
 */
class EventRequestsTable extends Component
{
    use ManagesRecords;

    #[Url(as: 'statut', except: 'soumise')]
    public string $statusFilter = 'soumise';

    public ?int $rejectingId = null;

    public string $reason = '';

    protected function permission(): Permission
    {
        return Permission::EventsView;
    }

    protected function managePermission(): ?Permission
    {
        return Permission::EventsManage;
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function accept(int $requestId): void
    {
        $this->authorizeManage();
        $this->redirectRoute('admin.events.create', ['demande' => $this->pending($requestId)->id]);
    }

    public function startRejection(int $requestId): void
    {
        $this->authorizeManage();
        $this->rejectingId = $this->pending($requestId)->id;
        $this->reason = '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function reject(SmsSender $sms): void
    {
        $this->authorizeManage();
        $this->validate(['reason' => ['required', 'string', 'max:500']], attributes: ['reason' => 'motif']);

        $request = $this->pending((int) $this->rejectingId);
        $request->update([
            'status' => EventRequestStatus::Rejected,
            'reviewed_at' => now(),
            'reviewed_by' => auth()->id(),
            'review_note' => trim($this->reason),
        ]);

        $phone = $request->promoter->phone;

        if (filled($phone) && PhoneNumber::isValid($phone)) {
            $sms->send(PhoneNumber::parse($phone), sprintf(
                '%s : votre demande « %s » n\'a pas été retenue. Motif : %s',
                config('eventtrans.sms.sender_name'), $request->name, $request->review_note,
            ));
        }

        $this->showForm = false;
        $this->rejectingId = null;
        $this->notify('Demande refusée ; le promoteur est prévenu.');
    }

    private function pending(int $requestId): EventRequest
    {
        $request = EventRequest::query()->findOrFail($requestId);
        abort_unless($request->isPending(), 403, 'Cette demande a déjà été traitée.');

        return $request;
    }

    public function render(): View
    {
        $status = EventRequestStatus::tryFrom($this->statusFilter);

        return view('livewire.admin.event-requests-table', [
            'requests' => EventRequest::query()
                ->with(['promoter', 'venue', 'event', 'submitter'])
                ->when($status !== null, fn ($query) => $query->where('status', $status))
                ->when($this->search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->whereRaw('lower(name) like ?', [$this->searchTerm()])
                    ->orWhereHas('promoter', fn ($query) => $query->whereRaw('lower(name) like ?', [$this->searchTerm()]))))
                ->orderBy('starts_at')
                ->paginate(20),
            'pendingCount' => EventRequest::query()->where('status', EventRequestStatus::Submitted)->count(),
            'statuses' => EventRequestStatus::options(),
        ]);
    }
}
