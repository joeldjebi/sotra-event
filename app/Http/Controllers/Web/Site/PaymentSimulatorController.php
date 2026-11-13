<?php

namespace App\Http\Controllers\Web\Site;

use App\Actions\Payments\HandlePaymentNotification;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Integrations\Payment\FakeAggregator;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Simulateur de l'agrégateur (pilote « fake » uniquement, jamais en production) : joue le rôle du téléphone
 * du spectateur qui confirme ou refuse le paiement, puis envoie le webhook signé comme le ferait l'agrégateur.
 */
class PaymentSimulatorController extends Controller
{
    public function show(string $reference): View
    {
        return view('site.payment-simulator', ['payment' => $this->payment($reference)]);
    }

    public function respond(Request $request, string $reference, FakeAggregator $aggregator, HandlePaymentNotification $handler): RedirectResponse
    {
        $payment = $this->payment($reference);
        $outcome = $request->input('outcome') === 'accept' ? PaymentStatus::Succeeded : PaymentStatus::Failed;

        $webhook = $aggregator->simulate($payment, $outcome, $outcome === PaymentStatus::Failed ? 'Refusé par le titulaire' : null);

        // Même chemin qu'un vrai webhook : signature vérifiée puis traitement idempotent.
        $notification = $aggregator->parseWebhook(Request::create('/', 'POST', server: ['HTTP_'.str_replace('-', '_', strtoupper(FakeAggregator::SIGNATURE_HEADER)) => $webhook['signature']], content: $webhook['body']));
        $handler->handle($aggregator->name(), $notification);

        return redirect()->route('payments.simulator', $reference)->with('flash', $outcome === PaymentStatus::Succeeded ? 'Paiement confirmé.' : 'Paiement refusé.');
    }

    private function payment(string $reference): Payment
    {
        abort_unless(config('eventtrans.drivers.payment') === 'fake' && ! app()->isProduction(), 404);

        return Payment::query()->with('order.event')->where('provider_reference', $reference)->firstOrFail();
    }
}
