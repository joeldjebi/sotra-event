<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Enums\RefundMode;
use App\Enums\RefundStatus;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Refund;
use App\Models\User;
use App\Services\Finance\RefundService;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Console ACCES EVENT · Remboursements (PA-04) : suivi des délais (48 h / 72 h), relance par API,
 * liste de traitement manuel tracée (référence de l'opération saisie par le support).
 */
class RefundsTable extends Component
{
    use AuthorizesPermission, WithPagination;

    /** « manuel » : la liste de traitement manuel ; sinon un statut. */
    #[Url(as: 'vue', except: 'manuel')]
    public string $view = 'manuel';

    public ?int $processingId = null;

    public bool $showManual = false;

    public string $operationReference = '';

    protected function permission(): Permission
    {
        return Permission::PassesManage;
    }

    public function updatedView(): void
    {
        $this->resetPage();
    }

    public function retry(int $refundId, RefundService $refunds): void
    {
        $refund = $refunds->retry(Refund::query()->findOrFail($refundId));

        $refund->status === RefundStatus::Refunded
            ? $this->notify("Remboursement {$refund->reference} effectué.")
            : $this->notify("Remboursement {$refund->reference} : {$refund->failure_reason}", 'warning');
    }

    public function openManual(int $refundId): void
    {
        $this->processingId = Refund::query()->findOrFail($refundId)->id;
        $this->operationReference = '';
        $this->resetErrorBag();
        $this->showManual = true;
    }

    public function markRefunded(RefundService $refunds): void
    {
        $this->validate(['operationReference' => ['required', 'string', 'min:4', 'max:80']], attributes: ['operationReference' => 'référence de l\'opération']);
        $refund = Refund::query()->where('status', '!=', RefundStatus::Refunded)->findOrFail($this->processingId);

        /** @var User $user */
        $user = auth()->user();
        $refunds->markRefunded($refund, $user, trim($this->operationReference));
        $this->reset(['showManual', 'processingId', 'operationReference']);
        $this->notify("Remboursement {$refund->reference} marqué effectué, spectateur prévenu.");
    }

    public function render(): View
    {
        $pending = fn () => Refund::query()->where('status', '!=', RefundStatus::Refunded);

        return view('livewire.admin.refunds-table', [
            'refunds' => Refund::query()->with(['pass', 'customer', 'payment', 'event', 'processor'])
                ->when($this->view === 'manuel', fn ($query) => $query->where('status', '!=', RefundStatus::Refunded)
                    ->where(fn ($inner) => $inner->where('mode', RefundMode::Manual)->orWhere('status', RefundStatus::Failed)))
                ->when($this->view !== 'manuel' && $this->view !== 'tous', fn ($query) => $query->where('status', $this->view))
                ->orderBy('due_at')
                ->paginate(25),
            'statuses' => RefundStatus::options(),
            'counts' => [
                'manual' => $pending()->where(fn ($inner) => $inner->where('mode', RefundMode::Manual)->orWhere('status', RefundStatus::Failed))->count(),
                'overdue' => $pending()->where('due_at', '<', now())->count(),
            ],
            'processing' => $this->processingId ? Refund::query()->with(['pass', 'customer', 'payment'])->find($this->processingId) : null,
        ]);
    }
}
