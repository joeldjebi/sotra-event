# EVENT TRANS — Cahier de recette (web, backoffice, API)

Pour ACCES EVENT et la SOTRA, qui recettent la plateforme lot par lot (§ 15 du CDC).
Chaque scénario se joue à la main sur l'environnement de recette. La colonne « Test » renvoie au test
automatisé qui vérifie la même règle à chaque modification (`composer check`, 282 tests au 13/11/2026).

**Comptes de démonstration** (recette uniquement, mot de passe `password`) :
- `admin@eventtrans.test` (console) ;
- `commercial@sotra.test`, `qualite@sotra.test`, `exploitation@sotra.test` (espace compagnie) ;
- `chef.projet@promoteur.test` (espace promoteur).

Spectateur : n'importe quel numéro. Le code SMS s'affiche sur `/dev/sms` en local ; en recette, il arrive par SMS réel avec MTarget.

**Statut** : ✅ conforme · ❌ non conforme (décrire l'écart) · — non joué.

---

## Lot 1 — Socle et console

| # | Scénario | Résultat attendu | Test | Statut |
|---|---|---|---|---|
| 1.1 | Se connecter à la console sans 2FA activée | Activation de la 2FA obligatoire avant tout accès (NF-03) | `Auth/TwoFactorEnrollmentTest` | |
| 1.2 | Créer une compagnie avec un compte de reversement Mobile Money | Coordonnées chiffrées, absentes du journal d'audit (AD-08) | `Admin/CompaniesTableTest` | |
| 1.3 | Créer un événement : informations, points, grille, canaux | Zone calculée selon la distance (< 15 km, 15–25, > 25), grille préremplie 3 000 / 5 000 / 10 000 (AD-01 à AD-04, RG-06) | `Events/EventConfigurationTest` | |
| 1.4 | Saisir un aller simple à 1 400 pour un aller-retour à 3 000 | Refus : plus de la moitié de l'aller-retour exigée (RG-07) | `Events/EventConfigurationTest` | |
| 1.5 | Proposer l'événement, l'accepter côté compagnie, ouvrir la vente | Parcours complet ; la vente reste fermée sans zones de dépose ni de reprise | `Events/EventWorkflowTest` | |
| 1.6 | Payer un Pass ; le webhook arrive 3 fois | Un seul jeu de Pass émis (PA-02) | `Sales/PaymentApiTest` | |
| 1.7 | Payer sans que le webhook n'arrive | Paiement confirmé par la réconciliation (PA-03) | `Sales/PaymentApiTest` | |
| 1.8 | Créer un utilisateur, changer ses rôles, le désactiver | Invitation, rôles journalisés, session coupée (AD-12) | `Admin/UsersTableTest`, `Auth/WebLoginTest` | |

## Lot 2 — Vente

| # | Scénario | Résultat attendu | Test | Statut |
|---|---|---|---|---|
| 2.1 | Acheter 2 Pass Standard Zone 1 sur le site | 6 000 + 120 de frais = 6 120 FCFA ; CGV à cocher (NF-14) ; Pass reçus par SMS | `Site/SiteJourneyTest` | |
| 2.2 | Vérifier les montants d'un Pass | 3 000 → frais 60 → total 3 060 → commission 300 → compagnie 2 700 (RG-13) | `Sales/OrderCapacityTest` | |
| 2.3 | Remplir l'offre puis tenter un Pass de plus | Refus « capacité atteinte », aucune survente (RG-08) | `Sales/OrderCapacityTest` | |
| 2.4 | Commencer un paiement sans le finir pendant 15 min | Places libérées | `Sales/OrderCapacityTest` | |
| 2.5 | Offrir un Pass à un autre numéro | Nouveau QR envoyé ; l'ancien refusé au scan (SP-19, RG-04) | `Sales/PassApiTest` | |
| 2.6 | Ouvrir « Mon Pass » sans réseau (application) | QR affiché, vérifiable hors ligne (NF-10, NF-01) | `Sales/PassApiTest` | |
| 2.7 | Charge : 300 commandes simultanées pour 250 places | Exactement 250 vendues, 50 refus, aucune erreur ; p95 < 500 ms (NF-05, NF-06) | `php artisan loadtest:orders` | |

## Lot 3 — Exploitation jour J

| # | Scénario | Résultat attendu | Test | Statut |
|---|---|---|---|---|
| 3.1 | Générer le plan de transport après les ventes | Bus = Pass ÷ places, arrondi au supérieur, par point, offre et sens (RG-08) | `Operations/MissionPlannerTest` | |
| 3.2 | Affecter bus, chauffeur, convoyeur, terminal | Refus d'un bus en panne, trop petit ou déjà pris | `Operations/AssignmentsTest` | |
| 3.3 | Scanner un Pass valide, puis le même | Vert puis rouge « déjà utilisé » (CV-08, RG-03) | `Operations/ConveyorApiTest` | |
| 3.4 | Scanner un Pass d'un autre bus du même point | Orange, montée autorisée et tracée | `Operations/ConveyorApiTest` | |
| 3.5 | 500 scans hors ligne sur deux terminaux, puis synchronisation | Aucune perte, aucun doublon, conflits signalés (sortie du lot 3) | `Operations/ConveyorApiTest` | |
| 3.6 | Confirmer le départ, suivre le bus, entrer dans la zone de dépose | Arrivée détectée ; « Suivre mon bus » à jour (CV-14, SP-18) | `Operations/ConveyorApiTest`, `Operations/LiveScreensTest` | |
| 3.7 | Départ confirmé 25 min après l'heure prévue | Réclamation collective et SMS aux passagers | `Quality/FeedbackTest` | |

## Lot 4 — Incidents et espaces partenaires

| # | Scénario | Résultat attendu | Test | Statut |
|---|---|---|---|---|
| 4.1 | Signaler une panne avec des passagers à bord | Alerte urgente ; techniciens prévenus (CV-15 à CV-18, ST-06) | `Operations/BreakdownTest` | |
| 4.2 | Deux équipes prennent la même alerte | La seconde reçoit « déjà pris en charge par l'équipe T3 » | `Operations/BreakdownTest` | |
| 4.3 | Clôturer « remplacé » avec un bus de réserve | Pass transférés, scan de contrôle vert, passagers prévenus (RG-20, sortie du lot 4) | `Operations/BreakdownTest` | |
| 4.4 | Envoyer « PANNE 27 MOTEUR » par SMS | Alerte créée en mode dégradé (CV-19) | `Operations/BreakdownTest` | |
| 4.5 | Qualité : départs à +10 et +11 min | 50 % à l'heure ; notes du chauffeur et du convoyeur = moyenne des avis de leurs bus (QU-01, QU-04) | `Quality/QualitySpaceTest` | |
| 4.6 | Traiter une réclamation | Ouverte → En cours → Traitée ; réponse envoyée par SMS (QU-03) | `Quality/QualitySpaceTest` | |
| 4.7 | Générer le rapport qualité PDF | PDF téléchargé ; une autre compagnie n'y a pas accès (QU-05) | `Quality/QualitySpaceTest` | |
| 4.8 | Promoteur : suivre les arrivées | Spectateurs arrivés, bus, retards, flux prévu et réel par 15 min (PR-02, PR-03) | `Quality/PromoterSpaceTest` | |
| 4.9 | Promoteur : message aux passagers d'un bus | Seuls ses passagers le reçoivent ; message journalisé ; plafond respecté (PR-04, PR-08) | `Quality/PromoterSpaceTest` | |
| 4.10 | Spectateur : avis et réclamation depuis « Mon Pass » | Avis seulement après le trajet, une fois ; réclamation suivie (SP-22, SP-23) | `Quality/FeedbackTest` | |

## Lot 5 — Finance et pilote (partie web et API)

| # | Scénario | Résultat attendu | Test | Statut |
|---|---|---|---|---|
| 5.1 | Annuler son Pass 3 jours avant | 3 060 payés, 245 retenus, 2 815 remboursés (RG-19) | `Finance/RefundsTest` | |
| 5.2 | Annuler à moins de 24 h du départ, ou après le scan | Bouton désactivé, motif affiché | `Finance/RefundsTest` | |
| 5.3 | Rembourser un paiement Moov Money | Le remboursement passe en traitement manuel ; « Marquer remboursé » avec la référence (PA-04) | `Finance/RefundsTest` | |
| 5.4 | Reporter l'événement d'une semaine | Horaires décalés, détenteurs prévenus, remboursement sans frais possible (RG-18) | `Finance/RefundsTest` | |
| 5.5 | Annuler l'événement | Tous les Pass remboursés à 100 %, frais compris (RG-17, AD-13) | `Finance/RefundsTest` | |
| 5.6 | Relevé de reversement : 4 Pass vendus, 1 remboursé | (12 000 − 3 000) × 90 % = 8 100 FCFA ; échéance J + 5 jours ouvrés (RG-14) | `Finance/PayoutsTest` | |
| 5.7 | Valider puis payer le reversement avec justificatif | La compagnie voit le relevé et le justificatif ; une autre compagnie, non (AD-11, CO-05) | `Finance/PayoutsTest` | |
| 5.8 | SMS réel par MTarget (`php artisan sms:test`) | SMS reçu ; ticket enregistré | `Integrations/MtargetSmsTest` | |

## Exigences transverses

| # | Scénario | Résultat attendu | Test | Statut |
|---|---|---|---|---|
| T.1 | Compagnie A ouvre une URL de la compagnie B | Erreur 403 ou 404, jamais de données | `Security/CompanyScopeTest`, `Company/FleetTest` | |
| T.2 | Journal d'audit : tenter une modification en base | Refusée (table en ajout seul, NF-04) | `Security/AuditLogTest` | |
| T.3 | En-têtes HTTPS et CSP en production | `Strict-Transport-Security`, `Content-Security-Policy`, `X-Frame-Options` présents (NF-03) | `Hardening/HardeningTest` | |
| T.4 | Positions GPS hors mission | Ignorées ; purgées 90 jours après l'événement (NF-12) | `Hardening/HardeningTest` | |
| T.5 | Sauvegarde puis restauration | `scripts/restore-test.sh` restaure et compte les tables clés (NF-08) | manuel, voir `docs/INSTALLATION.md` § 7 | |
| T.6 | Sonde `/api/v1/health` | 200 si tout va bien, 503 si la base tombe (NF-08) | `Hardening/HardeningTest` | |

---

**Hors de ce cahier** :
- les applications mobiles (spectateur, convoyeur, technique), à recetter par l'équipe mobile sur les mêmes API ;
- le bot WhatsApp ;
- le vrai agrégateur de paiement, simulé en attendant sa documentation.
