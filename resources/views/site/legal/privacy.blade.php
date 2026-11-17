@php($contact = config('eventtrans.legal.contact_email'))
<x-layouts.site title="Politique de confidentialité">
    <article class="space-y-5 text-[15px] leading-relaxed [&_h2]:mt-9 [&_h2]:text-[19px] [&_h2]:font-semibold [&_h2]:tracking-tight [&_li]:mt-1">
        <h1 class="text-[28px] font-bold tracking-tight">Politique de confidentialité</h1>
        <p class="text-[14px] text-ink-secondary">ACCES TRANS · site accestrans.com et applications mobiles ACCES TRANS (spectateur, convoyeur, service technique) · dernière mise à jour : 17 novembre 2026</p>

        <p>ACCES TRANS est le service de Pass transport en bus des grands événements, proposé par ACCES EVENT avec la compagnie de transport partenaire de chaque événement (par exemple la SOTRA). Cette politique explique quelles données nous collectons, pourquoi, avec qui nous les partageons, combien de temps nous les gardons et comment exercer vos droits. Elle s’applique conformément à la loi ivoirienne n° 2013-450 du 19 juin 2013 relative à la protection des données à caractère personnel ; le traitement est déclaré auprès de l’ARTCI.</p>

        <h2>1. Responsable du traitement</h2>
        <p>ACCES EVENT, éditeur d’ACCES TRANS (Abidjan, Côte d’Ivoire). Contact pour toute question sur vos données : <span class="font-medium">{{ $contact }}</span>.</p>

        <h2>2. Données collectées</h2>
        <p><strong>Application spectateur et site</strong></p>
        <ul class="list-disc pl-5">
            <li><strong>Identité et contact</strong> : numéro de téléphone (obligatoire, il sert à vous connecter par code SMS et à recevoir vos Pass), prénom et nom (facultatifs).</li>
            <li><strong>Achats</strong> : événement, point d’embarquement, offre, Pass, montants, référence du paiement et moyen de paiement utilisé (Orange Money, MTN MoMo, Moov Money, Wave ou carte). <strong>Nous ne recevons ni ne stockons aucune donnée de carte bancaire</strong> : le paiement est saisi chez l’agrégateur de paiement.</li>
            <li><strong>Utilisation des Pass</strong> : heure et bus de chaque contrôle à la montée, transferts de Pass, annulations et remboursements.</li>
            <li><strong>Avis et réclamations</strong> que vous choisissez de déposer.</li>
            <li><strong>Appareil</strong> : nom de l’appareil donné à la connexion et, si vous les acceptez, l’identifiant des notifications push.</li>
            <li>L’application spectateur <strong>ne collecte pas votre position GPS</strong>. « Suivre mon bus » affiche la position du bus, pas la vôtre.</li>
        </ul>
        <p><strong>Applications du personnel (convoyeur, service technique)</strong></p>
        <ul class="list-disc pl-5">
            <li>Nom, téléphone professionnel, rôle et compagnie, renseignés par l’employeur.</li>
            <li><strong>Position GPS</strong> du bus ou de l’équipe technique, <strong>uniquement pendant une mission</strong> (de l’embarquement à l’arrivée) ou une intervention, pour le suivi des bus et la prise en charge des pannes. Aucune position n’est enregistrée en dehors.</li>
            <li><strong>Caméra</strong> : utilisée pour lire le QR code des Pass ; les images ne sont pas enregistrées. Les photos de panne que vous prenez volontairement sont transmises au service technique.</li>
            <li>Numéro de série du terminal (PDA) et version de l’application.</li>
        </ul>

        <h2>3. Pourquoi nous les utilisons</h2>
        <ul class="list-disc pl-5">
            <li>Vendre, émettre, envoyer et contrôler vos Pass ; organiser les bus selon les ventes (exécution du contrat).</li>
            <li>Vous informer de votre trajet : confirmation, rappels, retard, panne, changement de bus, annulation ou report (exécution du contrat).</li>
            <li>Traiter les paiements, remboursements et reversements aux transporteurs (exécution du contrat et obligations comptables).</li>
            <li>Améliorer la qualité du service à partir des avis, réclamations et statistiques de ponctualité (intérêt légitime).</li>
            <li>Assurer la sécurité : détection des fraudes au QR code, journal des actions sensibles (intérêt légitime et obligations légales).</li>
        </ul>
        <p>Nous ne vendons pas vos données et ne les utilisons pas pour de la publicité ciblée.</p>

        <h2>4. Avec qui nous les partageons</h2>
        <ul class="list-disc pl-5">
            <li><strong>La compagnie de transport</strong> de l’événement (ex. SOTRA), pour l’exploitation des bus, le contrôle des Pass et le traitement des réclamations.</li>
            <li><strong>Le promoteur</strong> de l’événement reçoit uniquement des chiffres globaux (spectateurs transportés, arrivées), jamais vos coordonnées. Il peut vous envoyer, par notre intermédiaire, des informations pratiques sur l’événement.</li>
            <li><strong>Nos prestataires techniques</strong>, chacun pour ce qui le concerne : l’agrégateur de paiement, l’opérateur SMS (MTarget), WhatsApp (Meta), le service de notifications push (Google Firebase / Apple) et notre hébergeur.</li>
            <li>Les autorités, sur demande légale.</li>
        </ul>

        <h2>5. Durées de conservation</h2>
        <ul class="list-disc pl-5">
            <li>Compte : jusqu’à sa suppression par vous.</li>
            <li>Commandes, paiements et remboursements : 10 ans (obligations comptables), sans lien avec vous après la suppression de votre compte.</li>
            <li>Positions GPS des bus : {{ config('eventtrans.privacy.positions_retention_days') }} jours après l’événement.</li>
            <li>Codes de connexion : {{ config('eventtrans.privacy.otp_retention_days') }} jours ; journal des SMS envoyés : {{ config('eventtrans.privacy.sms_retention_days') }} jours.</li>
            <li>Sauvegardes : 30 jours.</li>
        </ul>

        <h2>6. Sécurité</h2>
        <p>Connexions chiffrées (HTTPS), QR codes signés, accès aux données limité au personnel habilité avec double authentification, journal non modifiable des actions sensibles, sauvegardes quotidiennes.</p>

        <h2>7. Vos droits</h2>
        <p>Vous pouvez accéder à vos données, les faire corriger, vous opposer à leur traitement ou demander leur suppression en écrivant à <span class="font-medium">{{ $contact }}</span>. Votre nom se modifie dans « Profil ». Vous pouvez aussi saisir l’ARTCI (Autorité de Régulation des Télécommunications/TIC de Côte d’Ivoire).</p>

        <h2>8. Supprimer votre compte</h2>
        <p>Dans l’application : <strong>Profil › Supprimer mon compte</strong>. Sur le web : <a href="{{ route('legal.account-deletion') }}" class="font-medium text-brand-text hover:underline">{{ route('legal.account-deletion') }}</a>. La suppression est immédiate ; le détail de ce qui est effacé et conservé figure sur cette page.</p>

        <h2>9. Mineurs</h2>
        <p>ACCES TRANS s’adresse aux personnes de 16 ans et plus. Un Pass peut être acheté par un adulte pour un mineur qui l’accompagne.</p>

        <h2>10. Modifications</h2>
        <p>Nous pouvons mettre à jour cette politique ; la date en haut de page indique la dernière version. En cas de changement important, nous vous en informons dans l’application ou par SMS.</p>
    </article>
</x-layouts.site>
