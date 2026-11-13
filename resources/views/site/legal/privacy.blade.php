<x-layouts.site title="Politique de confidentialité">
    <article class="space-y-5 text-[15px] leading-relaxed [&_h2]:mt-8 [&_h2]:text-[19px] [&_h2]:font-semibold [&_h2]:tracking-tight">
        <h1 class="text-[28px] font-bold tracking-tight">Politique de confidentialité</h1>
        @include('site.legal.partials.draft')

        <p>EVENT TRANS respecte la loi ivoirienne n° 2013-450 du 19 juin 2013 relative à la protection des données à caractère personnel. Le traitement fait l'objet d'une déclaration auprès de l'ARTCI.</p>

        <h2>Données collectées et finalités</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li><strong>Spectateurs</strong> : numéro de téléphone (connexion par code SMS, envoi des Pass), nom facultatif, achats, scans de montée, avis et réclamations. Finalités : vente et contrôle des Pass, information sur le trajet, service client, statistiques.</li>
            <li><strong>Paiement</strong> : seule la référence de l'opération est conservée ; aucune donnée de carte n'est stockée.</li>
            <li><strong>Convoyeurs</strong> : position GPS du bus, collectée uniquement pendant les missions (de l'embarquement à l'arrivée), pour le suivi des bus et l'information des spectateurs.</li>
        </ul>

        <h2>Durées de conservation</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>Positions GPS : {{ config('eventtrans.privacy.positions_retention_days') }} jours après l'événement.</li>
            <li>Codes de connexion : {{ config('eventtrans.privacy.otp_retention_days') }} jours ; journal des SMS : {{ config('eventtrans.privacy.sms_retention_days') }} jours.</li>
            <li>Commandes, paiements et remboursements : durée légale de conservation des pièces comptables.</li>
        </ul>

        <h2>Destinataires</h2>
        <p>ACCES EVENT, la compagnie de transport de l'événement (pour l'exploitation et la qualité de service), l'agrégateur de paiement et l'opérateur SMS, chacun pour ce qui le concerne. Le promoteur ne reçoit que des données agrégées (nombre de spectateurs transportés, arrivées).</p>

        <h2>Vos droits</h2>
        <p>Vous pouvez accéder à vos données, les faire rectifier ou supprimer et vous opposer à leur traitement en écrivant à ACCES EVENT. Vous pouvez aussi saisir l'ARTCI.</p>
    </article>
</x-layouts.site>
