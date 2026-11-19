<div class="space-y-6">
    @php($status = $promoter->status)
    @unless ($promoter->isApproved())
        <div class="text-center">
            <span @class([
                'mx-auto grid size-12 place-items-center rounded-full',
                'bg-warning-soft text-warning' => $status === \App\Enums\PromoterStatus::Pending,
                'bg-danger-soft text-danger' => $status !== \App\Enums\PromoterStatus::Pending,
            ])><x-ui.icon :name="$status === \App\Enums\PromoterStatus::Pending ? 'clock' : 'exclamation'" class="size-6" /></span>
            <h1 class="mt-3 text-[22px] font-semibold tracking-tight">{{ $promoter->name }}</h1>
            <p class="mt-1"><x-ui.badge :tone="$status->tone()">{{ $status->label() }}</x-ui.badge></p>
            <p class="mt-3 text-[15px] text-ink-secondary">
                @switch($status)
                    @case(\App\Enums\PromoterStatus::Pending)
                        ACCES EVENT examine votre demande. Vous recevrez un SMS dès qu'elle sera validée ; votre espace s'ouvrira alors.
                        @break
                    @case(\App\Enums\PromoterStatus::Rejected)
                        Votre demande n'a pas été retenue.
                        @break
                    @default
                        Votre compte promoteur est suspendu. Contactez ACCES EVENT pour le réactiver.
                @endswitch
            </p>
            @if ($promoter->review_note)
                <p class="mt-2 rounded-[10px] bg-fill px-3 py-2 text-[14px]"><span class="font-medium">Motif :</span> {{ $promoter->review_note }}</p>
            @endif
        </div>
    @endunless

    <div>
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-[17px] font-semibold tracking-tight">Votre dossier</h2>
            <x-ui.badge :tone="$promoter->isFileComplete() ? 'success' : 'warning'">{{ $promoter->isFileComplete() ? 'Complet' : 'Dossier incomplet' }}</x-ui.badge>
        </div>
        @unless ($promoter->isFileComplete())
            <p class="mt-1 text-[14px] text-ink-secondary">Manque : {{ implode(', ', $promoter->missingItems()) }}.</p>
        @endunless

        @if ($canEdit && $status !== \App\Enums\PromoterStatus::Rejected)
            <form wire:submit="save" class="mt-4 space-y-4">
                <x-ui.input label="Numéro RCCM" name="rccm_number" wire:model="rccm_number" />
                @foreach (['rccm_document' => ['Extrait RCCM', $promoter->rccm_document_path], 'identity_document' => ['Pièce d\'identité du responsable', $promoter->id_document_path]] as $field => [$label, $current])
                    <div wire:key="org-upload-{{ $field }}">
                        <label for="{{ $field }}" class="block px-0.5 text-footnote font-medium text-ink-secondary">
                            {{ $label }} @if ($current)<span class="text-success">· reçu</span>@endif
                        </label>
                        <input id="{{ $field }}" type="file" wire:model="{{ $field }}" accept=".pdf,image/jpeg,image/png" class="mt-1.5 block w-full text-[14px]">
                        @error($field)<p class="mt-1 text-footnote text-danger">{{ $message }}</p>@enderror
                    </div>
                @endforeach
                <x-ui.button class="w-full" wire:loading.attr="disabled">Enregistrer</x-ui.button>
            </form>
        @endif
    </div>
</div>
