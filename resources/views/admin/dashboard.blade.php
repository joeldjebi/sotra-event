<x-layouts.app title="Tableau de bord" :subtitle="now()->translatedFormat('l j F Y')">
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-ui.kpi label="Compagnies" :value="$stats['companies']" hint="partenaires de transport" />
        <x-ui.kpi label="Promoteurs" :value="$stats['promoters']" hint="organisateurs d'événements" />
        <x-ui.kpi label="Utilisateurs" :value="$stats['users']" hint="comptes des espaces web" />
        <x-ui.kpi label="Spectateurs" :value="$stats['customers']" hint="comptes clients" />
    </div>

    <x-ui.card class="mt-6" padding="p-0">
        <x-ui.empty-state icon="calendar" title="Aucun événement pour l'instant"
                          description="La création d'événements en 4 étapes arrive à l'étape 3. La vue jour J (ventes, bus, pannes) s'affichera ici." />
    </x-ui.card>
</x-layouts.app>
