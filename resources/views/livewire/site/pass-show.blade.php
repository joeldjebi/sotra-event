<div class="-mx-4 -my-6 min-h-[calc(100vh-3.5rem)] bg-marine-900 px-4 py-6 sm:mx-0 sm:my-0 sm:rounded-[32px] sm:p-8">
    <div class="mb-5 flex items-center justify-between">
        <h1 class="text-[28px] font-bold tracking-tight text-white">Mon Pass</h1>
        <x-ui.badge :tone="$pass->status->tone()" class="px-3 py-1 text-[13px]">{{ $pass->pass_type->label() }} · {{ mb_strtolower($pass->status->label()) }}</x-ui.badge>
    </div>

    <div class="[&_p.mt-4]:text-white/70">
        @include('site.partials.pass-card', ['pass' => $pass])
    </div>

    <div class="mt-6 grid grid-cols-2 gap-3">
        <x-ui.button size="lg" :href="route('site.trip', ['pass' => $pass->code])">Suivre mon bus</x-ui.button>
        @if ($pass->isTransferable())
            <button type="button" wire:click="$set('showGift', true)" class="h-12 rounded-full text-[15px] font-medium text-white ring-1 ring-white/30 hover:bg-white/10">Offrir ce Pass</button>
        @endif
    </div>

    <x-ui.sheet model="showGift" title="Offrir ce Pass" subtitle="Le bénéficiaire le reçoit par SMS et WhatsApp. Votre QR cessera de fonctionner.">
        <form id="gift-form" wire:submit="offer" class="space-y-4">
            <x-ui.input label="Numéro du bénéficiaire" name="phone" type="tel" wire:model="phone" placeholder="07 00 00 00 00 ou +33 6 12 34 56 78" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="gift-form">Offrir</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>
</div>
