<div>
    <h1 class="text-[34px] leading-tight font-bold tracking-tight">Profil</h1>

    <form wire:submit="save" class="mt-6 space-y-4 rounded-[22px] bg-surface p-5 ring-1 ring-hairline">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-ui.input label="Prénom" name="first_name" wire:model="first_name" />
            <x-ui.input label="Nom" name="last_name" wire:model="last_name" />
        </div>
        <div>
            <p class="text-[13px] font-medium text-ink-secondary">Téléphone</p>
            <p class="mt-1 text-[17px] font-medium">{{ $customer->phone }}</p>
            <p class="text-[13px] text-ink-tertiary">Votre numéro sert à vous connecter et à recevoir vos Pass.</p>
        </div>
        <div class="flex justify-end"><x-ui.button>Enregistrer</x-ui.button></div>
    </form>

    <x-ui.button variant="ghost" type="button" class="mt-6 w-full text-danger" wire:click="logout">Se déconnecter</x-ui.button>
</div>
