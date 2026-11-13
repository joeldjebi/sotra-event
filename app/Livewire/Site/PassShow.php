<?php

namespace App\Livewire\Site;

use App\Actions\Passes\TransferPass;
use App\Enums\ComplaintCategory;
use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Pass;
use App\Models\Review;
use App\Rules\ValidPhoneNumber;
use App\Services\Finance\RefundPolicy;
use App\Services\Finance\RefundService;
use App\Services\Quality\ComplaintService;
use App\Services\Quality\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Mon Pass (SP-14 à SP-19, SP-22, SP-23) : QR signé, montée, retour, « Offrir ce Pass », avis et réclamations.
 */
#[Layout('components.layouts.site')]
#[Title('Mon Pass')]
class PassShow extends Component
{
    public Pass $pass;

    public bool $showGift = false;

    public string $phone = '';

    public ?string $notice = null;

    public bool $showCancel = false;

    public bool $showReview = false;

    /** @var array<string, int> note de 1 à 5 par critère (SP-22) */
    public array $ratings = [];

    public string $comment = '';

    public bool $showComplaint = false;

    public string $category = '';

    public string $details = '';

    public function mount(Pass $pass): void
    {
        $customer = auth('customer')->user();
        abort_unless($customer instanceof Customer && $pass->holder_id === $customer->id, 404);
        $this->pass = $pass;
    }

    public function offer(TransferPass $transfer): Redirector|RedirectResponse|null
    {
        $this->validate(['phone' => ['required', 'string', new ValidPhoneNumber]], attributes: ['phone' => 'numéro']);

        /** @var Customer $customer */
        $customer = auth('customer')->user();

        try {
            $transfer->handle($this->pass, $customer, $this->phone);
        } catch (ApiException $exception) {
            $this->addError('phone', $exception->getMessage());

            return null;
        }

        session()->flash('flash', "Pass {$this->pass->code} offert : il a été envoyé au bénéficiaire par SMS et WhatsApp.");

        return $this->redirectRoute('site.passes.index');
    }

    /**
     * Annuler le Pass et être remboursé (SP-25, RG-18, RG-19).
     */
    public function cancelPass(RefundPolicy $policy, RefundService $refunds): void
    {
        $quote = $policy->quote($this->pass);

        if (! $quote['allowed'] || $quote['reason'] === null) {
            $this->showCancel = false;
            $this->notice = $quote['message'];

            return;
        }

        $refund = $refunds->cancel($this->pass, $quote['reason']);
        $this->pass->refresh();
        $this->showCancel = false;
        $this->notice = 'Pass annulé. Remboursement de '.number_format($refund->amount, 0, ',', ' ').' FCFA en cours sur votre moyen de paiement.';
    }

    /**
     * Avis après le trajet (SP-22).
     */
    public function sendReview(ReviewService $reviews): void
    {
        $rules = array_fill_keys(array_map(fn (string $key): string => "ratings.{$key}", array_keys(Review::CRITERIA)), ['required', 'integer', 'between:1,5']);
        $this->validate($rules + ['comment' => ['nullable', 'string', 'max:1000']], ['ratings.*.required' => 'Donnez une note.']);

        try {
            /** @var array{punctuality: int, comfort: int, courtesy: int, driving: int, information: int} $ratings */
            $ratings = array_map('intval', array_intersect_key($this->ratings, Review::CRITERIA));
            $reviews->submit($this->pass, $ratings, $this->comment);
        } catch (ApiException $exception) {
            $this->addError('comment', $exception->getMessage());

            return;
        }

        $this->reset(['showReview', 'ratings', 'comment']);
        $this->notice = 'Merci pour votre avis !';
    }

    /**
     * Réclamation liée au Pass (SP-23).
     */
    public function sendComplaint(ComplaintService $complaints): void
    {
        $this->validate([
            'category' => ['required', Rule::enum(ComplaintCategory::class)],
            'details' => ['required', 'string', 'min:10', 'max:2000'],
        ], attributes: ['category' => 'motif', 'details' => 'description']);

        try {
            $complaint = $complaints->open($this->pass, ComplaintCategory::from($this->category), $this->details);
        } catch (ApiException $exception) {
            $this->addError('details', $exception->getMessage());

            return;
        }

        $this->reset(['showComplaint', 'category', 'details']);
        $this->notice = "Réclamation {$complaint->number} enregistrée. Vous recevrez la réponse par SMS.";
    }

    public function render(): View
    {
        return view('livewire.site.pass-show', [
            'pass' => $this->pass->load(['event.venue', 'event.dropZones', 'boardingPoint']),
            'canReview' => app(ReviewService::class)->canReview($this->pass),
            'cancellation' => app(RefundPolicy::class)->quote($this->pass),
            'refund' => $this->pass->refund,
            'review' => $this->pass->review,
            'complaints' => $this->pass->complaints()->withoutGlobalScopes()->latest('id')->get(),
            'criteria' => Review::CRITERIA,
            'categories' => ComplaintCategory::options(),
        ]);
    }
}
