<x-layouts.auth title="Simulateur de paiement">
    <p class="text-[13px] font-semibold tracking-wide text-brand uppercase">Simulation · {{ $payment->method->label() }}</p>
    <h1 class="mt-2 text-[26px] font-semibold tracking-tight">{{ number_format($payment->amount, 0, ',', ' ') }} FCFA</h1>
    <p class="mt-1.5 text-[15px] text-ink-secondary">ACCES EVENT · {{ $payment->order->event->name }}<br>Commande {{ $payment->order->reference }} @if ($payment->msisdn) · {{ $payment->msisdn }} @endif</p>

    @if (session('flash'))
        <x-ui.alert class="mt-6">{{ session('flash') }} Vous pouvez revenir sur l'onglet du site.</x-ui.alert>
    @endif

    @if ($payment->status === \App\Enums\PaymentStatus::Pending)
        <form method="POST" action="{{ route('payments.simulator.respond', $payment->provider_reference) }}" class="mt-8 grid grid-cols-2 gap-3">
            @csrf
            <x-ui.button variant="secondary" size="lg" name="outcome" value="refuse">Refuser</x-ui.button>
            <x-ui.button size="lg" name="outcome" value="accept">Confirmer</x-ui.button>
        </form>
    @else
        <p class="mt-6 text-center text-[15px] font-medium">Statut : {{ $payment->status->label() }}</p>
    @endif

    <p class="mt-6 text-center text-[12px] text-ink-tertiary">Page de démonstration : elle remplace la confirmation sur le téléphone tant que l'agrégateur réel n'est pas branché.</p>
</x-layouts.auth>
