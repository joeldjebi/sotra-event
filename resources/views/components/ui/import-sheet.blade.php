@props(['title', 'columns'])
{{-- Feuille d'import Excel/CSV liée au trait HandlesImport. --}}
<x-ui.sheet model="showImport" :title="$title" subtitle="Fichier Excel (.xlsx) ou CSV, première ligne = en-têtes.">
    <div class="space-y-6">
        <div class="rounded-2xl bg-canvas p-4">
            <p class="text-[14px] font-medium">Colonnes attendues</p>
            <p class="mt-1 font-mono text-[13px] text-ink-secondary">{{ $columns }}</p>
            <x-ui.button variant="tinted" size="sm" type="button" class="mt-3" wire:click="downloadTemplate">Télécharger le modèle</x-ui.button>
        </div>

        <form id="import-form" wire:submit="import" class="space-y-3">
            <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-hairline px-6 py-10 text-center hover:border-brand/40">
                <x-ui.icon name="document" class="size-8 text-ink-tertiary" />
                <span class="text-[15px] font-medium" wire:loading.remove wire:target="importFile">
                    {{ $this->importFile ? $this->importFile->getClientOriginalName() : 'Choisir un fichier' }}
                </span>
                <span class="text-[15px] text-ink-secondary" wire:loading wire:target="importFile">Envoi…</span>
                <span class="text-[13px] text-ink-tertiary">5 Mo maximum</span>
                <input type="file" class="sr-only" wire:model="importFile" accept=".xlsx,.xls,.csv">
            </label>
            @error('importFile') <p class="text-[13px] text-danger">{{ $message }}</p> @enderror
        </form>

        @if ($this->importErrors !== [])
            <div class="rounded-2xl bg-danger-soft p-4">
                <p class="text-[14px] font-medium text-danger">Rien n'a été importé. Corrigez ces lignes puis réessayez :</p>
                <ul class="mt-2 max-h-60 space-y-1 overflow-y-auto text-[13px] text-danger">
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
