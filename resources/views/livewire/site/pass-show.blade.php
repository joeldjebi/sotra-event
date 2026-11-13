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

    @if ($pass->event->postponed_at && $pass->status->value === 'valide')
        <p class="mt-5 rounded-2xl bg-warning/20 px-4 py-3 text-[14px] text-white">
            Événement reporté au {{ $pass->event->starts_at->translatedFormat('l j F à H\hi') }}. Votre Pass reste valable ; vous pouvez aussi demander le remboursement intégral.
        </p>
    @endif

    @if ($refund)
        <p class="mt-5 rounded-2xl bg-white/10 px-4 py-3 text-[14px] text-white">
            Remboursement {{ $refund->reference }} · {{ number_format($refund->amount, 0, ',', ' ') }} FCFA · {{ mb_strtolower($refund->status->label()) }}
        </p>
    @endif

    @if ($notice)
        <p class="mt-5 rounded-2xl bg-white/10 px-4 py-3 text-[14px] text-white" role="status">{{ $notice }}</p>
    @endif

    <div class="mt-6 rounded-[22px] bg-white/[0.06] p-5 text-white ring-1 ring-white/10">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-[17px] font-semibold">Votre avis et vos réclamations</h2>
                @if ($review)
                    <p class="text-[14px] text-white/70">Avis envoyé · {{ number_format($review->average(), 1, ',', ' ') }}/5. Merci !</p>
                @elseif ($canReview)
                    <p class="text-[14px] text-white/70">Comment s'est passé votre trajet ?</p>
                @else
                    <p class="text-[14px] text-white/70">Vous pourrez donner votre avis après votre trajet.</p>
                @endif
            </div>
            <div class="flex shrink-0 gap-2">
                @if ($canReview)
                    <button type="button" wire:click="$set('showReview', true)" class="h-9 rounded-full bg-white px-4 text-[14px] font-medium text-marine-900">Donner mon avis</button>
                @endif
                <button type="button" wire:click="$set('showComplaint', true)" class="h-9 rounded-full px-4 text-[14px] font-medium text-white ring-1 ring-white/30 hover:bg-white/10">Réclamation</button>
            </div>
        </div>

        @if ($complaints->isNotEmpty())
            <ul class="mt-4 divide-y divide-white/10 border-t border-white/10">
                @foreach ($complaints as $complaint)
                    <li wire:key="complaint-{{ $complaint->id }}" class="py-3 text-[14px]">
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium">{{ $complaint->number }} · {{ $complaint->category->label() }}</span>
                            <x-ui.badge :tone="$complaint->status->tone()">{{ $complaint->status->label() }}</x-ui.badge>
                        </div>
                        @if ($complaint->resolution)
                            <p class="mt-1 text-white/70">Réponse : {{ $complaint->resolution }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($cancellation['allowed'])
        <div class="mt-4 text-center">
            <button type="button" wire:click="$set('showCancel', true)" class="text-[14px] font-medium text-white/70 underline-offset-4 hover:text-white hover:underline">Annuler mon Pass et être remboursé</button>
        </div>
    @elseif ($pass->status->value === 'valide')
        <p class="mt-4 text-center text-[13px] text-white/50">{{ $cancellation['message'] }}</p>
    @endif

    <x-ui.sheet model="showCancel" title="Annuler mon Pass" :subtitle="$cancellation['message']">
        <dl class="space-y-3 text-[15px]">
            <div class="flex justify-between"><dt class="text-ink-secondary">Total payé</dt><dd class="tabular-nums">{{ number_format($cancellation['paid'], 0, ',', ' ') }} FCFA</dd></div>
            <div class="flex justify-between"><dt class="text-ink-secondary">Frais d'annulation (8 %)</dt><dd class="tabular-nums">− {{ number_format($cancellation['retained'], 0, ',', ' ') }} FCFA</dd></div>
            <div class="flex justify-between border-t border-hairline pt-3 font-semibold"><dt>Remboursé</dt><dd class="tabular-nums">{{ number_format($cancellation['amount'], 0, ',', ' ') }} FCFA</dd></div>
        </dl>
        @if ($cancellation['deadline'])
            <p class="mt-4 text-[13px] text-ink-secondary">Possible jusqu'au {{ $cancellation['deadline']->translatedFormat('j F à H\hi') }}. Votre QR cessera de fonctionner.</p>
        @endif
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Garder mon Pass</x-ui.button>
            <x-ui.button variant="danger" type="button" wire:click="cancelPass">Annuler le Pass</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>

    <x-ui.sheet model="showReview" title="Donner mon avis" subtitle="Une note de 1 à 5 par critère.">
        <form id="review-form" wire:submit="sendReview" class="space-y-4">
            @foreach ($criteria as $key => $label)
                <div wire:key="criterion-{{ $key }}">
                    <p class="text-[14px] font-medium">{{ $label }}</p>
                    <div class="mt-1.5 flex gap-1.5" role="radiogroup" aria-label="{{ $label }}">
                        @for ($star = 1; $star <= 5; $star++)
                            <button type="button" role="radio" aria-checked="{{ ($ratings[$key] ?? 0) === $star ? 'true' : 'false' }}" aria-label="{{ $star }} sur 5"
                                    wire:click="$set('ratings.{{ $key }}', {{ $star }})"
                                    @class(['grid size-10 place-items-center rounded-xl text-[20px] ring-1 ring-inset ring-hairline', 'bg-brand text-white' => ($ratings[$key] ?? 0) >= $star, 'text-ink-tertiary' => ($ratings[$key] ?? 0) < $star])>★</button>
                        @endfor
                    </div>
                    @error("ratings.{$key}")<p class="mt-1 text-[13px] text-danger">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <x-ui.textarea label="Commentaire (facultatif)" name="comment" wire:model="comment" rows="3" />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="review-form">Envoyer mon avis</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>

    <x-ui.sheet model="showComplaint" title="Faire une réclamation" subtitle="Elle est transmise à la compagnie ; la réponse vous est envoyée par SMS.">
        <form id="complaint-form" wire:submit="sendComplaint" class="space-y-4">
            <x-ui.select label="Motif" name="category" wire:model="category" :options="$categories" placeholder="Choisir" />
            <x-ui.textarea label="Que s'est-il passé ?" name="details" wire:model="details" rows="4" placeholder="Ex. : le bus retour est parti à 23 h 05 alors que j'étais au point de reprise." />
        </form>
        <x-slot:footer>
            <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
            <x-ui.button form="complaint-form">Envoyer</x-ui.button>
        </x-slot:footer>
    </x-ui.sheet>

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
