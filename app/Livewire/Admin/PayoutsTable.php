<?php

namespace App\Livewire\Admin;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Enums\Permission;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Payout;
use App\Models\User;
use App\Services\Finance\PayoutCalculator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * Console ACCES EVENT · Reversements (AD-11, PA-05) : relevé par événement et par compagnie,
 * recalcul tant qu'il n'est pas validé, validation, paiement (virement ou Mobile Money marchand) et justificatif.
 */
class PayoutsTable extends Component
{
    use AuthorizesPermission, WithFileUploads, WithPagination;

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    #[Url(as: 'compagnie', except: '')]
    public string $company = '';

    public ?int $viewingId = null;

    public bool $showDetails = false;

    public string $paymentMethod = 'virement';

    public string $paymentReference = '';

    /** @var mixed Fichier temporaire Livewire */
    public $proof = null;

    protected function permission(): Permission
    {
        return Permission::PayoutsManage;
    }

    public function show(int $payoutId): void
    {
        $payout = Payout::query()->findOrFail($payoutId);
        $this->viewingId = $payout->id;
        $this->paymentMethod = $payout->company->payout_method->value ?? PayoutMethod::BankTransfer->value;
        $this->reset(['paymentReference', 'proof']);
        $this->resetErrorBag();
        $this->showDetails = true;
    }

    public function recalculate(PayoutCalculator $calculator): void
    {
        $payout = $this->viewing();
        $calculator->generate($payout->event);
        $this->notify("Relevé {$payout->reference} recalculé.");
    }

    public function validatePayout(): void
    {
        $payout = $this->viewing();
        abort_unless($payout->status === PayoutStatus::Calculated, 409);
        $payout->update(['status' => PayoutStatus::Validated, 'validated_by' => $this->user()->id, 'validated_at' => now()]);
        $this->notify("Relevé {$payout->reference} validé : {$this->money($payout->net)} à verser.");
    }

    public function markPaid(): void
    {
        $payout = $this->viewing();
        abort_unless($payout->status === PayoutStatus::Validated, 409);

        $this->validate([
            'paymentMethod' => ['required', Rule::enum(PayoutMethod::class)],
            'paymentReference' => ['required', 'string', 'max:80'],
            'proof' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], attributes: ['paymentMethod' => 'mode de paiement', 'paymentReference' => 'référence', 'proof' => 'justificatif']);

        $payout->update([
            'status' => PayoutStatus::Paid,
            'paid_by' => $this->user()->id,
            'paid_at' => now(),
            'payment_method' => $this->paymentMethod,
            'payment_reference' => trim($this->paymentReference),
            'proof_path' => $this->proof->store('payouts', 'local'),
        ]);
        AuditLog::record('payout_paid', $payout, [], ['net' => $payout->net, 'reference' => $payout->payment_reference]);

        $this->reset(['paymentReference', 'proof']);
        $this->notify("Reversement {$payout->reference} payé.");
    }

    private function viewing(): Payout
    {
        return Payout::query()->with(['event', 'company'])->findOrFail($this->viewingId);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' FCFA';
    }

    public function render(): View
    {
        $query = Payout::query()
            ->when($this->status !== '', fn ($inner) => $inner->where('status', $this->status))
            ->when($this->company !== '', fn ($inner) => $inner->where('company_id', (int) $this->company));

        return view('livewire.admin.payouts-table', [
            'payouts' => (clone $query)->with(['event', 'company'])->orderByRaw("case status when 'valide' then 0 when 'calcule' then 1 else 2 end")->orderBy('due_on')->paginate(25),
            'totals' => [
                'to_validate' => (int) Payout::query()->where('status', PayoutStatus::Calculated)->sum('net'),
                'to_pay' => (int) Payout::query()->where('status', PayoutStatus::Validated)->sum('net'),
                'paid' => (int) Payout::query()->where('status', PayoutStatus::Paid)->sum('net'),
            ],
            'statuses' => PayoutStatus::options(),
            'companies' => Company::query()->orderBy('name')->pluck('name', 'id')->all(),
            'methods' => PayoutMethod::options(),
            'viewing' => $this->viewingId ? Payout::query()->with(['event.venue', 'company', 'validator', 'payer'])->find($this->viewingId) : null,
        ]);
    }
}
