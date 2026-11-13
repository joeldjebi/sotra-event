<?php

namespace App\Livewire\Admin;

use App\Actions\Passes\TransferPass;
use App\Enums\PassStatus;
use App\Enums\Permission;
use App\Exceptions\ApiException;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\Pass;
use App\Rules\ValidPhoneNumber;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Console ACCES EVENT · Pass & ventes (AD-10) : recherche par code ou téléphone, historique des scans
 * et des transferts, réclamations, transfert à un autre numéro (support). L'annulation et le remboursement
 * suivent les règles de la section 11 (étape 9).
 */
class PassesTable extends Component
{
    use AuthorizesPermission, WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'statut', except: '')]
    public string $status = '';

    public ?int $viewingId = null;

    public bool $showDetails = false;

    public string $transferPhone = '';

    protected function permission(): Permission
    {
        return Permission::PassesManage;
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function show(int $passId): void
    {
        $this->viewingId = Pass::query()->findOrFail($passId)->id;
        $this->transferPhone = '';
        $this->resetErrorBag();
        $this->showDetails = true;
    }

    /**
     * Transfert par le support, à la demande du détenteur : l'ancien QR devient invalide.
     */
    public function transfer(TransferPass $transfer): void
    {
        $this->validate(['transferPhone' => ['required', 'string', new ValidPhoneNumber]], attributes: ['transferPhone' => 'numéro']);
        $pass = Pass::query()->with('holder')->findOrFail($this->viewingId);

        try {
            $transfer->handle($pass, $pass->holder, $this->transferPhone);
        } catch (ApiException $exception) {
            $this->addError('transferPhone', $exception->getMessage());

            return;
        }

        $this->transferPhone = '';
        $this->notify("Pass {$pass->code} transféré : le nouveau détenteur l'a reçu par SMS et WhatsApp.");
    }

    /**
     * @return Builder<Pass>
     */
    private function query(): Builder
    {
        $term = trim($this->search);

        return Pass::query()
            ->with(['event', 'holder', 'boardingPoint'])
            ->whereNotNull('code')
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->when($term !== '', function ($query) use ($term) {
                $phone = PhoneNumber::isValid($term) ? PhoneNumber::parse($term)->e164 : null;

                $query->where(fn ($inner) => $inner
                    ->where('code', 'ilike', '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%')
                    ->when($phone, fn ($byPhone) => $byPhone->orWhereHas('holder', fn ($holder) => $holder->where('phone', $phone))));
            });
    }

    public function render(): View
    {
        $viewing = $this->viewingId ? Pass::query()->with([
            'event', 'holder', 'boardingPoint', 'order.customer', 'outboundMission.bus', 'returnMission.bus',
            'scans' => fn ($query) => $query->with(['mission.bus', 'mission.boardingPoint'])->orderBy('scanned_at'),
            'transfers' => fn ($query) => $query->with(['from', 'to'])->orderBy('id'),
            'complaints' => fn ($query) => $query->withoutGlobalScopes()->latest('id'),
            'review',
        ])->find($this->viewingId) : null;

        return view('livewire.admin.passes-table', [
            'passes' => $this->query()->latest('id')->paginate(25),
            'statuses' => collect(PassStatus::cases())->mapWithKeys(fn (PassStatus $status) => [$status->value => $status->label()])->all(),
            'viewing' => $viewing,
        ]);
    }
}
