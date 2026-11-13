<x-layouts.site title="Conditions générales de vente">
    <article class="space-y-5 text-[15px] leading-relaxed [&_h2]:mt-8 [&_h2]:text-[19px] [&_h2]:font-semibold [&_h2]:tracking-tight">
        <h1 class="text-[28px] font-bold tracking-tight">Conditions générales de vente</h1>
        @include('site.legal.partials.draft')

        <h2>1. Objet</h2>
        <p>EVENT TRANS, service d'ACCES EVENT opéré avec la compagnie de transport partenaire de chaque événement, vend des Pass de transport en bus vers et depuis le lieu de l'événement. Un Pass est nominatif pour un événement, un point d'embarquement, une offre (Standard, Premium ou Gold) et un ou deux trajets.</p>

        <h2>2. Prix et paiement</h2>
        <p>Les prix sont affichés en francs CFA (FCFA), selon la zone du point d'embarquement et l'offre choisie. Des frais de paiement de 2 % du prix s'ajoutent au total ; ils restent acquis à ACCES EVENT. Le paiement s'effectue par Mobile Money (Orange Money, MTN MoMo, Moov Money, Wave) ou par carte bancaire. Aucune donnée de carte n'est conservée par EVENT TRANS. Le Pass n'est émis qu'après la confirmation du paiement par l'opérateur.</p>

        <h2>3. Utilisation du Pass</h2>
        <p>Le Pass est présenté au convoyeur à la montée (QR code). Il n'est valable qu'une fois par trajet. Le Pass peut être offert à un autre numéro tant que le trajet aller n'est pas utilisé ; l'ancien QR cesse alors de fonctionner. Le spectateur se présente au point d'embarquement à l'heure de convocation indiquée.</p>

        <h2>4. Annulation et remboursement</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>Événement ou départ annulé : remboursement intégral, frais compris, sur le moyen de paiement d'origine.</li>
            <li>Événement reporté : le Pass reste valable à la nouvelle date ; à défaut, remboursement intégral sur demande, sous 48 h.</li>
            <li>Annulation à l'initiative du spectateur : possible jusqu'à 24 h avant l'heure du départ aller, si le Pass n'a pas été scanné ; remboursement sous 72 h, moins 8 % du total payé retenus au titre des frais d'annulation (exemple : 3 060 FCFA payés, 2 815 FCFA remboursés).</li>
            <li>Bus remplacé après une panne : le Pass est transféré automatiquement sur le bus de remplacement.</li>
        </ul>

        <h2>5. Réclamations</h2>
        <p>Une réclamation peut être déposée depuis « Mon Pass » ou l'application. La réponse est envoyée par SMS. En cas de retard de plus de 20 minutes d'un bus, une réclamation collective est ouverte automatiquement pour ses passagers.</p>

        <h2>6. Données personnelles</h2>
        <p>Le traitement des données est décrit dans la <a href="{{ route('legal.privacy') }}" class="font-medium text-brand-text hover:underline">politique de confidentialité</a>.</p>

        <h2>7. Droit applicable</h2>
        <p>Les présentes conditions sont soumises au droit ivoirien.</p>
    </article>
</x-layouts.site>
