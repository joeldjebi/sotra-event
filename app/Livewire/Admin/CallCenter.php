<?php

namespace App\Livewire\Admin;

use App\Actions\Passes\TransferPass;
use App\Enums\CallReason;
use App\Enums\ComplaintCategory;
use App\Enums\PassStatus;
use App\Enums\Permission;
use App\Enums\RefundReason;
use App\Exceptions\ApiException;
use App\Livewire\Concerns\AuthorizesPermission;
use App\Models\AuditLog;
use App\Models\CallLog;
use App\Models\Customer;
use App\Models\Pass;
use App\Models\User;
use App\Rules\ValidPhoneNumber;
use App\Services\Finance\RefundPolicy;
use App\Services\Finance\RefundService;
use App\Services\Operations\TripTracker;
use App\Services\Quality\ComplaintService;
use App\Services\Sales\PassNotifier;
use App\Support\PhoneNumber;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Console · Centre d'appel ACCES TRANS · Fiche spectateur : recherche par téléphone ou code de Pass, Pass et
 * commandes, bus et arrivée estimée ; renvoi du Pass, transfert, annulation selon les règles (RG-19), réclamation
 * au nom du spectateur ; journal des appels. Jamais les reversements ni les réglages. Le numéro n'est pas mis
 * dans l'adresse de la page (données personnelles).
 */
class CallCenter extends Component
{
    use AuthorizesPermission;

    public string $query = '';

    #[Locked]
    public ?int $customerId = null;

    /** Numéro recherché, même sans compte (pour journaliser l'appel). */
    #[Locked]
    public ?string $phone = null;

    public bool $searched = false;

    /** Pass sur lequel porte l'action en cours (transfert, annulation, réclamation). */
    #[Locked]
    public ?int $actionPassId = null;

    public string $action = '';

    public string $transferPhone = '';

    public string $cancelNote = '';

    public string $complaintCategory = '';

    public string $complaintDetails = '';

    public string $callReason = 'information';

    public string $callNotes = '';

    public ?int $callPassId = null;

    protected function permission(): Permission
    {
        return Permission::CallCenterAccess;
    }

    public function search(): void
    {
        $this->validate(['query' => ['required', 'string', 'min:3', 'max:40']], attributes: ['query' => 'téléphone ou code']);
        $this->reset(['customerId', 'phone', 'actionPassId', 'action', 'callPassId']);
        $term = trim($this->query);

        if (PhoneNumber::isValid($term)) {
            $this->phone = PhoneNumber::parse($term)->e164;
            $this->customerId = Customer::query()->where('phone', $this->phone)->whereNull('anonymized_at')->value('id');
        } else {
            $pass = Pass::query()->with('holder')->where('code', mb_strtoupper($term))->first();
            $this->customerId = $pass?->holder_id;
            $this->phone = $pass?->holder->phone;
        }

        $this->searched = true;
    }

    public function startAction(int $passId, string $action): void
    {
        abort_unless(in_array($action, ['transfer', 'cancel', 'complaint'], true), 400);
        $this->actionPassId = $this->pass($passId)->id;
        $this->action = $action;
        $this->reset(['transferPhone', 'cancelNote', 'complaintCategory', 'complaintDetails']);
        $this->resetValidation();
    }

    public function cancelAction(): void
    {
        $this->reset(['actionPassId', 'action']);
    }

    public function resend(int $passId, PassNotifier $notifier): void
    {
        $pass = $this->pass($passId);
        abort_unless(in_array($pass->status, [PassStatus::Valid, PassStatus::OutboundUsed, PassStatus::ReturnUsed], true), 403, 'Ce Pass n\'est plus utilisable.');

        $notifier->resend($pass->load(['holder', 'event', 'boardingPoint']));
        AuditLog::record('call_center_pass_resent', $pass);
        $this->logAutomatically(CallReason::PassNotReceived, "Pass {$pass->code} renvoyé par SMS et WhatsApp.", $pass);
        $this->notify("Pass {$pass->code} renvoyé par SMS et WhatsApp.");
    }

    public function transfer(TransferPass $transfer): void
    {
        $this->validate(['transferPhone' => ['required', 'string', new ValidPhoneNumber]], attributes: ['transferPhone' => 'numéro du bénéficiaire']);
        $pass = $this->pass((int) $this->actionPassId)->load('holder');

        try {
            $transfer->handle($pass, $pass->holder, $this->transferPhone);
        } catch (ApiException $exception) {
            $this->addError('transferPhone', $exception->getMessage());

            return;
        }

        $this->logAutomatically(CallReason::Transfer, "Pass {$pass->code} offert au ".PhoneNumber::parse($this->transferPhone)->masked().'.', $pass);
        $this->cancelAction();
        $this->notify("Pass {$pass->code} transféré : le bénéficiaire l'a reçu par SMS.");
    }

    public function cancel(RefundService $refunds): void
    {
        $this->validate(['cancelNote' => ['required', 'string', 'min:5', 'max:300']], attributes: ['cancelNote' => 'motif']);
        $pass = $this->pass((int) $this->actionPassId)->load('event');
        /** @var User $agent */
        $agent = auth()->user();

        try {
            // Règles du spectateur (RG-19) : pas de geste commercial depuis le centre d'appel.
            $refund = $refunds->cancel($pass, RefundReason::Customer, $agent, 'Centre d\'appel : '.trim($this->cancelNote));
        } catch (ApiException $exception) {
            $this->addError('cancelNote', $exception->getMessage());

            return;
        }

        $this->logAutomatically(CallReason::Cancellation, "Pass {$pass->code} annulé, remboursement {$refund->reference}.", $pass);
        $this->cancelAction();
        $this->notify("Pass {$pass->code} annulé · remboursement de ".number_format($refund->amount, 0, ',', ' ').' FCFA.');
    }

    public function openComplaint(ComplaintService $complaints): void
    {
        $this->validate([
            'complaintCategory' => ['required', Rule::enum(ComplaintCategory::class)],
            'complaintDetails' => ['required', 'string', 'min:10', 'max:1000'],
        ], attributes: ['complaintCategory' => 'motif', 'complaintDetails' => 'description']);

        $pass = $this->pass((int) $this->actionPassId);
        $complaint = $complaints->open($pass, ComplaintCategory::from($this->complaintCategory), trim($this->complaintDetails));

        $this->logAutomatically(CallReason::Complaint, "Réclamation {$complaint->number} ouverte pour le Pass {$pass->code}.", $pass);
        $this->cancelAction();
        $this->notify("Réclamation {$complaint->number} ouverte : la compagnie la traite et répond par SMS.");
    }

    public function logCall(): void
    {
        abort_if($this->phone === null, 400);

        $this->validate([
            'callReason' => ['required', Rule::enum(CallReason::class)],
            'callNotes' => ['nullable', 'string', 'max:1000'],
            'callPassId' => ['nullable', 'integer'],
        ], attributes: ['callReason' => 'motif', 'callNotes' => 'notes']);

        CallLog::query()->create([
            'customer_id' => $this->customerId,
            'phone' => $this->phone,
            'user_id' => auth()->id(),
            'pass_id' => $this->callPassId !== null ? $this->pass($this->callPassId)->id : null,
            'reason' => $this->callReason,
            'notes' => trim($this->callNotes) ?: null,
        ]);

        $this->reset(['callNotes', 'callPassId']);
        $this->callReason = CallReason::Information->value;
        $this->notify('Appel enregistré.');
    }

    /**
     * Les actions de l'agent sont aussi notées dans le journal des appels du spectateur.
     */
    private function logAutomatically(CallReason $reason, string $notes, Pass $pass): void
    {
        CallLog::query()->create([
            'customer_id' => $this->customerId,
            'phone' => (string) $this->phone,
            'user_id' => auth()->id(),
            'pass_id' => $pass->id,
            'reason' => $reason,
            'notes' => $notes,
        ]);
    }

    /**
     * Pass du spectateur affiché, et de lui seul.
     */
    private function pass(int $passId): Pass
    {
        abort_if($this->customerId === null, 404);

        return Pass::query()->where('holder_id', $this->customerId)->findOrFail($passId);
    }

    public function render(RefundPolicy $policy, TripTracker $tracker): View
    {
        $customer = $this->customerId ? Customer::query()->find($this->customerId) : null;

        $passes = $customer === null ? collect() : Pass::query()
            ->with(['event', 'boardingPoint', 'outboundMission.bus', 'returnMission.bus', 'refund'])
            ->where('holder_id', $customer->id)
            ->whereIn('status', PassStatus::owned())
            ->latest('id')
            ->limit(30)
            ->get();

        return view('livewire.admin.call-center', [
            'customer' => $customer,
            'passes' => $passes->map(function (Pass $pass) use ($policy, $tracker): array {
                // Le prochain trajet du spectateur : l'aller s'il n'est pas encore fait, sinon le retour.
                $mission = match ($pass->status) {
                    PassStatus::Valid => $pass->includesOutbound() ? $pass->outboundMission : $pass->returnMission,
                    PassStatus::OutboundUsed => $pass->returnMission,
                    default => null,
                };

                return [
                    'pass' => $pass,
                    'quote' => in_array($pass->status, [PassStatus::Valid], true) ? $policy->quote($pass) : null,
                    'mission' => $mission,
                    'eta' => $mission !== null ? $tracker->etaMinutes($mission) : null,
                ];
            }),
            'orders' => $customer === null ? collect() : $customer->orders()->with('event')->latest()->limit(10)->get(),
            'calls' => $this->phone === null
                ? CallLog::query()->with(['agent', 'pass'])->where('user_id', auth()->id())->latest()->limit(10)->get()
                : CallLog::query()->with(['agent', 'pass'])->where('phone', $this->phone)->latest()->limit(20)->get(),
            'reasons' => CallReason::options(),
            'categories' => ComplaintCategory::options(),
        ]);
    }
}
