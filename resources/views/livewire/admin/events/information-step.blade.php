<x-ui.card>
    <form wire:submit="save">
        <fieldset @disabled($locked) class="space-y-8">
            @if ($locked)
                <x-ui.alert tone="info">Événement proposé à la compagnie : les informations sont verrouillées. Elles redeviennent modifiables si la compagnie ouvre une négociation.</x-ui.alert>
            @endif

            <section class="space-y-4">
                <x-ui.section-title title="Événement" />
                <x-ui.input label="Nom de l'événement" name="name" wire:model="name" placeholder="Concert au stade d'Ebimpé" />
                <x-ui.textarea label="Description (affichée aux spectateurs)" name="description" wire:model="description" />
                <x-ui.select label="Lieu" name="venue_id" wire:model="venue_id" :options="$venues" placeholder="Choisir un lieu"
                             hint="Absent de la liste ? Ajoutez-le dans Lieux." />
                <div class="grid gap-4 sm:grid-cols-4">
                    <x-ui.input label="Date" name="date" type="date" wire:model="date" />
                    <x-ui.input label="Heure de début" name="start_time" type="time" wire:model="start_time" />
                    <x-ui.input label="Fin prévue" name="end_time" type="time" wire:model="end_time" hint="Le lendemain si plus tôt que le début." />
                    <x-ui.input label="Ouverture des portes" name="doors_time" type="time" wire:model="doors_time" />
                </div>
                <x-ui.input label="Pass estimés" name="estimated_passes" type="number" min="1" wire:model="estimated_passes" hint="Indiqué à la compagnie avec la proposition." />
            </section>

            <section class="space-y-4">
                <x-ui.section-title title="Partenaires" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.select label="Promoteur" name="promoter_id" wire:model.live="promoter_id" :options="$promoters" placeholder="Choisir un promoteur" />
                    <x-ui.select label="Chef de projet transport" name="transport_lead_id" wire:model="transport_lead_id" :options="$transportLeads"
                                 :placeholder="$promoter_id ? 'Choisir' : 'Choisissez d\'abord le promoteur'" />
                </div>
                <x-ui.select label="Compagnie de transport" name="company_id" wire:model.live="company_id" :options="$companies" placeholder="Choisir une compagnie" />
            </section>

            <section class="space-y-4">
                <x-ui.section-title title="Taux" description="Figés sur chaque vente : un changement ne s'applique qu'aux ventes suivantes (RG-15)." />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-ui.input label="Commission ACCES EVENT (%)" name="commission_rate" type="number" step="0.01" wire:model="commission_rate" />
                    <x-ui.input label="Frais de paiement client (%)" name="fee_rate" type="number" step="0.01" wire:model="fee_rate" />
                </div>
            </section>
        </fieldset>

        @unless ($locked)
            <div class="mt-8 flex justify-end">
                <x-ui.button>{{ $event ? 'Enregistrer' : 'Créer le brouillon' }}</x-ui.button>
            </div>
        @endunless
    </form>
</x-ui.card>
