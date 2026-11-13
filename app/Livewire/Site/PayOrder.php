<?php

namespace App\Livewire\Site;

use App\Actions\Payments\InitiatePayment;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\ApiException;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Paiement d'une commande (SP-09 à SP-13) : récapitulatif, moyen de paiement, attente de la confirmation
 * de l'agrégateur, puis succès ou échec avec possibilité de réessayer.
 */
#[Layout('components.layouts.site')]
#[Title('Paiement')]
class PayOrder extends Component
{
    public Order $order;

    public string $method = 'orange_money';

    public string $msisdn = '';

    public ?int $paymentId = null;

    public string $instructions = '';

    public ?string $simulatorUrl = null;

    public function mount(Order $order): void
    {
        $customer = auth('customer')->user();
        abort_unless($customer instanceof Customer && $order->customer_id === $customer->id, 404);

        $this->order = $order;
        $this->msisdn = str_starts_with($customer->phone, '+225') ? substr($customer->phone, 4) : $customer->phone;
    }

    public function pay(InitiatePayment $initiatePayment): Redirector|RedirectResponse|null
    {
        $method = PaymentMethod::tryFrom($this->method);

        $this->validate([
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'msisdn' => [Rule::requiredIf($method?->isMobileMoney() ?? false), 'nullable', 'string'],
        ], attributes: ['method' => 'moyen de paiement', 'msisdn' => 'numéro Mobile Money']);

        try {
            $result = $initiatePayment->handle($this->order, $method ?? PaymentMethod::OrangeMoney, $this->msisdn);
        } catch (ApiException $exception) {
            $this->addError($exception->errorCode === 'INVALID_MSISDN' ? 'msisdn' : 'payment', $exception->getMessage());

            return null;
        }

        if ($result['instructions']->redirectUrl !== null) {
            return $this->redirect($result['instructions']->redirectUrl);
        }

        $this->paymentId = $result['payment']->id;
        $this->instructions = $result['instructions']->message;
        $this->simulatorUrl = $result['instructions']->simulatorUrl;

        return null;
    }

    /**
     * Appelée toutes les 2 secondes pendant l'attente (wire:poll).
     */
    public function checkStatus(): Redirector|RedirectResponse|null
    {
        $this->order->refresh();

        if ($this->order->status === OrderStatus::Paid) {
            $customer = auth('customer')->user();
            $mine = $this->order->passes()->where('holder_id', $customer?->getAuthIdentifier())->orderBy('id')->first();

            session()->flash('flash', $mine ? 'Paiement confirmé. Votre Pass est prêt.' : 'Paiement confirmé. Le Pass a été envoyé à son bénéficiaire.');

            return $mine ? $this->redirectRoute('site.passes.show', $mine) : $this->redirectRoute('site.passes.index');
        }

        return null;
    }

    public function retry(): void
    {
        $this->reset(['paymentId', 'instructions', 'simulatorUrl']);
    }

    public function render(): View
    {
        $payment = $this->paymentId ? Payment::query()->find($this->paymentId) : null;

        return view('livewire.site.pay-order', [
            'methods' => PaymentMethod::cases(),
            'payment' => $payment,
            'failed' => $payment?->status === PaymentStatus::Failed,
            'expired' => ! $this->order->isPayable() && $this->order->status !== OrderStatus::Paid,
            'order' => $this->order->loadMissing(['event', 'boardingPoint']),
        ]);
    }
}
