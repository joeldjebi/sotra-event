<x-layouts.app title="Suivi de l'événement" :subtitle="$promoter?->name">
    <div class="grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-4">
        <x-ui.kpi label="Spectateurs transportés" value="—" hint="déjà arrivés au lieu" />
        <x-ui.kpi label="Bus arrivés" value="—" hint="sur les bus prévus" />
        <x-ui.kpi label="Prochaine arrivée" value="—" />
        <x-ui.kpi label="Retards signalés" value="—" />
    </div>

    <x-ui.card class="mt-6" padding="p-0">
        <x-ui.empty-state icon="map" title="Aucun événement en cours"
                          description="Le suivi en temps réel des arrivées s'affichera ici dès qu'un de vos événements sera en vente." />
    </x-ui.card>
</x-layouts.app>
