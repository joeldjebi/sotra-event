API REST d'**EVENT TRANS**, le Pass transport des grands événements (ACCES EVENT × SOTRA). Elle est consommée par trois applications mobiles : **spectateur** (acheter, montrer et suivre son Pass), **convoyeur** (contrôler les Pass à la montée, même sans réseau, et conduire la mission) et **service technique** (prendre en charge les pannes). Toutes les règles métier vivent côté serveur : l'application affiche et envoie, le serveur contrôle.

## Conventions

| Sujet | Règle |
|---|---|
| Adresse | `https://<domaine>/api/v1` (recette et production : adresses communiquées par ACCES EVENT ; en local `http://localhost:8000/api/v1`). Un changement incompatible donnera `/api/v2`. |
| Format | JSON, champs en `snake_case`, dates ISO 8601 avec fuseau (`2026-11-15T18:00:00+00:00`), montants en **FCFA entiers**. |
| En-têtes | `Accept: application/json` sur **toutes** les requêtes (sinon les erreurs reviennent en HTML). `Content-Type: application/json`, sauf l'envoi de photos (`multipart/form-data`). |
| Langue | Messages d'erreur et champs `*_label` en français, prêts à afficher. |
| Enveloppe | Les données sont dans `data`. |
| Identifiants | Un Pass s'identifie par son **code** (`ET-2611-0315-COC`), une commande par sa **référence** (`CMD-57MR68NR`), le reste par un entier. |

## Authentification

Pas de mot de passe : chacun se connecte avec son **numéro de téléphone** et un **code à 6 chiffres** reçu par SMS (fournisseur MTarget). Le code est valable 5 minutes, accepte 5 essais, et un nouveau code peut être demandé après 60 secondes. Si l'opérateur SMS refuse l'envoi, l'API répond **503** `SMS_NOT_SENT` : proposer de réessayer tout de suite.

1. `POST /auth/otp` avec `phone` et `audience` : `customer` (spectateur) ou `staff` (convoyeur, technicien).
2. `POST /auth/verify` avec le même `phone`, l'`audience`, le `code` et `device_name` : la réponse contient le **jeton**, à conserver dans le stockage sécurisé du téléphone (Keychain, Keystore).
3. Envoyer `Authorization: Bearer <jeton>` sur chaque requête protégée (bouton **Authorize** pour essayer ici).

Le compte spectateur est créé à la première connexion. Le jeton n'expire pas ; `POST /auth/logout` le révoque. Une réponse **401** (ou **403** sans `code` métier) signifie qu'il faut revenir à l'écran de connexion.

Le jeton porte une **capacité** selon le profil : `customer`, `conveyor` ou `tech`. Il ne donne accès qu'aux appels de son application. Pour le personnel, la demande de code répond toujours la même chose, que le numéro existe ou non ; les chauffeurs n'ont pas d'accès mobile.

Numéros acceptés : numéro ivoirien à 10 chiffres (`0707070707`, `07 07 07 07 07`) ou format international (`+2250707070707`, `+33 6 12 34 56 78`).

## Terminaux du personnel

Les applications convoyeur et technique envoient, sur **tous** leurs appels, deux en-têtes en plus du jeton :

| En-tête | Exemple | Rôle |
|---|---|---|
| `X-Device-Serial` | `TC21-0042` | Numéro de série du PDA. Un terminal enregistré puis bloqué par l'exploitation reçoit **403** `DEVICE_BLOCKED` : déconnecter l'utilisateur. Un smartphone personnel non enregistré est accepté. |
| `X-App-Version` | `1.4.0` | Version installée, affichée à l'exploitation avec la dernière synchronisation du terminal. |

## Parcours d'achat (app spectateur)

1. `GET /events` puis `GET /events/{event}` : points d'embarquement (zone, heure de convocation), offres, prix par zone (`offers[].prices`), places restantes (`offers[].remaining`), liens des CGV (`terms`).
2. `POST /orders/quote` à chaque changement de point, d'offre, de type ou de quantité : total frais de paiement compris (2 %).
3. Connexion si besoin, en gardant la sélection.
4. `POST /orders` avec `accept_terms: true` (case « J'accepte les CGV et la politique de confidentialité ») : les places sont **bloquées 15 minutes** (`expires_at`) ; afficher le compte à rebours.
5. `POST /orders/{order}/pay` : Mobile Money (afficher `instructions.message`, validation sur le téléphone) ou carte (ouvrir `instructions.redirect_url`, 3-D Secure).
6. `GET /orders/{order}` toutes les 3 secondes, 2 minutes au plus, jusqu'à `payee` (ouvrir « Mes Pass »), `echouee` (autre moyen de paiement) ou `expiree` (recommencer). `a_rembourser` : paiement arrivé trop tard, places revendues, le spectateur est remboursé.
7. `GET /me/passes` : enregistrer chaque `qr_token` sur le téléphone pour afficher le QR **sans réseau**.

Ne jamais considérer un paiement comme réussi à partir du retour de l'écran de paiement : seul le statut de la commande fait foi (webhook signé de l'agrégateur). En recette, le paiement est simulé : `instructions.simulator_url` ouvre une page pour l'accepter ou le refuser.

**Après l'achat** : offrir le Pass (`POST /passes/{pass}/transfer` ; l'ancien QR devient invalide, le retirer du téléphone), suivre son bus (`GET /passes/{pass}/trip`, toutes les 15 à 30 s), annuler (`POST /passes/{pass}/cancel`, selon `cancellation`), donner son avis (`review.available`), faire une réclamation (`POST /passes/{pass}/complaints`, suivi dans `GET /me/complaints`).

**Annulation** : jusqu'à 24 h avant le départ aller, Pass non scanné, 8 % du total retenus (3 060 FCFA payés → 2 815 remboursés). Événement reporté : remboursement intégral, sans frais. Afficher les montants de `cancellation` et demander confirmation ; le bouton n'apparaît que si `cancellation.allowed`.

## Contrôle hors ligne (app convoyeur)

L'application convoyeur doit tenir **une journée entière sans réseau**.

1. **Avant le départ**, dès qu'il y a du réseau : `GET /me/missions`, puis `GET /missions/{mission}/manifest` pour chaque mission.
2. **Vérifier un QR sans réseau** : signature Ed25519 avec `public_key` du manifeste ; Pass présent dans `passes` avec la même `token_version` ; absent de `revoked` ; trajet pas encore utilisé sur ce terminal.
3. **Décider la couleur** avec les mêmes règles qu'en ligne : `vert` (montée), `orange` (montée, à signaler : prévu dans un autre bus du même point ou autre point), `rouge` (refus, avec motif).
4. **Mettre en file** chaque scan avec un `client_uuid` généré et son heure `scanned_at`.
5. **Au retour du réseau** : `POST /scans/sync` (1 000 scans par lot au plus). Les réponses font foi ; `conflict: true` signale un même Pass accepté sur deux terminaux (le scan le plus ancien l'emporte).

En ligne, `POST /scans` contrôle immédiatement. Rejouer un même `client_uuid` ne compte jamais deux fois.

**Format du QR** : `ET1.<données>.<signature>` en base64url. Les données (JSON) : `p` identifiant du Pass, `e` événement, `b` point d'embarquement, `o` offre, `t` type de Pass, `v` version (augmente quand le Pass est offert : un QR d'une ancienne version est refusé). La signature Ed25519 porte sur la chaîne `ET1.<données>`. Le convoyeur peut aussi saisir le code lisible du Pass (`ET-AAMM-NNNN-COM`), vérifié en ligne.

**Mission** : `POST /missions/{mission}/boarding-started`, puis `…/depart` (l'heure réelle sert à la ponctualité : à l'heure jusqu'à +10 min ; au-delà de +20 min, une réclamation collective est ouverte automatiquement), positions toutes les 15 s par `POST /missions/{mission}/positions` (en file sans réseau), arrivée détectée à l'entrée de la zone de dépose ou confirmée par `…/arrive`. N'envoyer des positions que pendant une mission (loi ivoirienne n° 2013-450) : celles reçues hors mission sont ignorées.

## Pannes (apps convoyeur et technique)

1. Le convoyeur envoie `POST /breakdowns` (jusqu'à 4 photos). L'alerte est **urgente** d'office s'il y a des passagers à bord, ou en cas d'accident. Sans data : SMS `PANNE <n° du bus> <TYPE> [précisions]` au numéro de secours, par exemple `PANNE 27 MOTEUR fumée`.
2. Les techniciens des équipes mobilisées reçoivent une notification push (sonnerie si urgente) ; `GET /tech/alerts` liste les alertes, les urgentes d'abord, avec distance et temps estimé (après `POST /tech/position`).
3. Une seule équipe prend l'alerte : `POST /tech/alerts/{alert}/take` (sinon **409** `ALERT_ALREADY_TAKEN`, avec le code de l'équipe dans le message).
4. Étapes horodatées, sans retour en arrière : `POST /tech/interventions/{intervention}/steps` avec `en_route`, `sur_place`, `diagnostic`.
5. Clôture : `repare` (le bus repart), `remplace` (avec `replacement_bus_id`, choisi dans `GET /tech/reserve-buses?alert=…` : les Pass passent sur le bus de réserve, les passagers sont prévenus, le convoyeur fait un scan de contrôle à la montée) ou `remorquage`.

## Erreurs

Une erreur a toujours un `message` lisible, à afficher tel quel.

- **Erreur métier** : `message` + `code` stable. Testez le `code`, jamais le texte.
- **Champ invalide (422)** : `message` + `errors` (pour chaque champ, la liste des messages à placer sous le champ).
- **401** non connecté · **403** pas le droit · **404** introuvable ou appartenant à un autre compte · **429** trop de requêtes (attendre l'en-tête `Retry-After`) · **5xx** réessayer plus tard.

| Code | HTTP | Signification et conduite à tenir |
|---|---|---|
| `OTP_RESEND_TOO_SOON` | 429 | Un code vient d'être envoyé : attendre `resend_in` secondes. |
| `OTP_NOT_FOUND` | 422 | Aucun code en cours pour ce numéro. |
| `OTP_INVALID` | 422 | Code incorrect. |
| `OTP_EXPIRED` | 422 | Code expiré : en redemander un. |
| `OTP_TOO_MANY_ATTEMPTS` | 429 | 5 essais ratés : en redemander un. |
| `SMS_NOT_SENT` | 503 | L'opérateur SMS a refusé le message : réessayer. |
| `ACCOUNT_NOT_ALLOWED` | 403 | Compte de personnel inactif ou sans accès mobile. |
| `EVENT_NOT_ON_SALE` | 422 | L'événement n'est plus en vente. |
| `CHANNEL_CLOSED` | 422 | Vente par l'application fermée pour cet événement. |
| `BOARDING_POINT_NOT_FOUND` | 404 | Point d'embarquement inconnu pour cet événement. |
| `DEPARTURE_CANCELLED` | 409 | Départ annulé : proposer un autre point. |
| `OFFER_UNAVAILABLE` | 422 | Offre non proposée pour cet événement. |
| `PRICE_UNAVAILABLE` | 422 | Ce type de Pass n'est pas vendu depuis ce point. |
| `QUANTITY_OUT_OF_RANGE` | 422 | De 1 à 10 Pass par commande. |
| `CAPACITY_REACHED` | 409 | Plus assez de places dans cette offre : afficher « complet ». |
| `ORDER_NOT_PAYABLE` | 422 | Commande expirée ou déjà payée : recommencer la réservation. |
| `INVALID_MSISDN` | 422 | Numéro Mobile Money invalide. |
| `PASS_NOT_OWNED` | 403 | Ce Pass n'appartient pas au spectateur connecté. |
| `PASS_NOT_TRANSFERABLE` | 422 | Pass déjà utilisé à l'aller, annulé ou terminé. |
| `PASS_SAME_HOLDER` | 422 | Le numéro est déjà celui du détenteur. |
| `PASS_NOT_CANCELLABLE` | 409 | Annulation impossible ; le message dit pourquoi (scanné, moins de 24 h, déjà annulé). |
| `PAYMENT_NOT_FOUND` | 409 | Paiement d'origine introuvable : contacter le support. |
| `REVIEW_NOT_AVAILABLE` | 422 | Avis possible seulement après un trajet scanné. |
| `REVIEW_ALREADY_SENT` | 409 | Un avis existe déjà pour ce Pass. |
| `COMPLAINT_NOT_ALLOWED` | 422 | Ce Pass ne permet pas de réclamation. |
| `COMPLAINT_LIMIT` | 429 | Déjà 3 réclamations en cours pour ce Pass. |
| `DEVICE_BLOCKED` | 403 | Terminal bloqué par l'exploitation : déconnecter l'utilisateur. |
| `MISSION_NOT_ASSIGNED` | 403 | Mission non affectée à ce convoyeur. |
| `MISSION_STATUS_INVALID` | 409 | Action impossible dans le statut actuel de la mission. |
| `NO_TEAM` | 403 | Technicien rattaché à aucune équipe. |
| `TEAM_NOT_ASSIGNED` | 403 | Équipe non mobilisée sur l'événement de cette panne. |
| `ALERT_ALREADY_TAKEN` | 409 | Une autre équipe a déjà pris l'alerte. |
| `INTERVENTION_OTHER_TEAM` | 403 | Intervention d'une autre équipe. |
| `INTERVENTION_STEP_INVALID` | 409 | Étape hors ordre. |
| `INTERVENTION_CLOSED` | 409 | Intervention déjà clôturée. |

## Limites de requêtes

Au-delà, réponse **429** avec l'en-tête `Retry-After` (secondes). Limite générale : **300 appels par minute par compte**, 600 par adresse IP sans connexion. Les limites propres à certaines routes sont indiquées dans leur réponse 429.

## Changements

| Date | Changement |
|---|---|
| 13/11/2026 | **Obligatoire** : `POST /orders` exige `accept_terms: true` (CGV et politique de confidentialité) ; liens dans `terms` du détail d'un événement. |
| 13/11/2026 | Annulation et remboursement (`POST /passes/{pass}/cancel`, champs `cancellation` et `refund`), avis et réclamations, API du service technique, réponse 503 `SMS_NOT_SENT`. |
