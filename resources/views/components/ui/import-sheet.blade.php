@props(['title', 'columns'])
{{-- Feuille d'import Excel/CSV liée au trait HandlesImport. --}}
<x-ui.sheet model="showImport" :title="$title" subtitle="Fichier Excel (.xlsx) ou CSV, première ligne = en-têtes.">
    <div class="space-y-6">
        <div class="rounded-[var(--radius-card)] bg-surface p-4 shadow-[var(--shadow-card)] ring-1 ring-hairline">
            <p class="text-[15px] font-semibold tracking-[-0.01em]">Colonnes attendues</p>
            <p class="mt-1 font-mono text-[13px] text-ink-secondary">{{ $columns }}</p>
            <x-ui.button variant="tinted" size="sm" type="button" class="mt-3" icon="document" wire:click="downloadTemplate">Télécharger le modèle</x-ui.button>
        </div>

        <form id="import-form" wire:submit="import" class="space-y-3">
            <label class="group flex cursor-pointer flex-col items-center justify-center gap-2 rounded-[var(--radius-card)] border-[1.5px] border-dashed border-line-strong bg-surface/60 px-6 py-10 text-center transition-colors duration-200 hover:border-brand/60 hover:bg-brand-soft/40">
                <span class="grid size-12 place-items-center rounded-full bg-fill text-ink-secondary transition-colors group-hover:bg-brand-soft group-hover:text-brand-text">
                    <x-ui.icon name="document" class="size-6" />
                </span>
                <span class="text-[15px] font-semibold tracking-[-0.01em] text-brand-text" wire:loading.remove wire:target="importFile">
                    {{ $this->importFile ? $this->importFile->getClientOriginalName() : 'Choisir un fichier' }}
                </span>
                <span class="text-[15px] text-ink-secondary" wire:loading wire:target="importFile">Envoi…</span>
                <span class="text-footnote text-ink-tertiary">5 Mo maximum</span>
                <input type="file" class="sr-only" wire:model="importFile" accept=".xlsx,.xls,.csv">
            </label>
            @error('importFile') <p class="px-1 text-footnote text-danger">{{ $message }}</p> @enderror
        </form>

        @if ($this->importErrors !== [])
            <div class="rounded-[var(--radius-card)] bg-danger-soft p-4">
                <p class="flex items-center gap-2 text-[14px] font-semibold text-danger"><x-ui.icon name="exclamation" class="size-[18px] shrink-0" />Rien n'a été importé. Corrigez ces lignes puis réessayez :</p>
                <ul class="mt-2 max-h-60 space-y-1 overflow-y-auto pl-[26px] text-[13px] text-danger">
                    @foreach ($this->importErrors as $error)
                        <li wire:key="import-error-{{ $loop->index }}">{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
    <x-slot:footer>
        <x-ui.button variant="secondary" type="button" x-on:click="open = false">Annuler</x-ui.button>
        <x-ui.button form="import-form" wire:loading.attr="disabled" wire:target="import,importFile">Importer</x-ui.button>
    </x-slot:footer>
</x-ui.sheet>
