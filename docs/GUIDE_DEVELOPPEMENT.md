# EVENT TRANS — Guide de développement (web, backoffice, API)

> Destiné aux développeurs de l'équipe web et API.
> Références : *Cahier des charges développeurs* (CDC) et [SPECIFICATIONS.md](SPECIFICATIONS.md).
> Règle d'or : **back et front 100 % Laravel.** Pas de framework JavaScript (ni React, ni Vue, ni Inertia).

---

## 1. Stack technique

| Couche | Choix |
|---|---|
| Langage / framework | PHP 8.3, **Laravel 13** |
| Front (site, console, espaces) | **Blade** + **Livewire 3** (+ Alpine.js, livré avec Livewire) + **Tailwind CSS** |
| API mobile | Laravel REST `/api/v1`, API Resources, **Sanctum** |
| Auth web | **Fortify** : mot de passe + 2FA TOTP |
| Auth mobile | OTP par SMS → jeton Sanctum |
| Base de données | **PostgreSQL 16 + PostGIS** |
| Cache, verrous, files | **Redis** + **Horizon** |
| Temps réel | **Reverb** + Laravel Echo ; repli `wire:poll` (≤ 30 s) |
| Tâches planifiées | Laravel Scheduler |
| Tests / qualité | **Pest**, **Larastan** (niveau 6+), **Pint** |
| Documentation API | **Scramble** (OpenAPI généré à partir du code) |
| PDF / Excel | `barryvdh/laravel-dompdf`, `maatwebsite/excel` |
| QR code | `bacon/bacon-qr-code` (SVG) ; signature Ed25519 avec `sodium` (natif PHP) |
| Carte | Leaflet + OpenStreetMap, avec un petit composant Blade/Alpine (seul JavaScript tiers autorisé, avec Chart.js pour les graphiques) |

Node.js sert uniquement à **compiler les assets** (Vite + Tailwind). Aucune logique métier en JavaScript.

### Packages à installer
```bash
composer require laravel/fortify laravel/sanctum laravel/reverb laravel/horizon \
  livewire/livewire spatie/laravel-permission maatwebsite/excel \
  barryvdh/laravel-dompdf bacon/bacon-qr-code clickbar/laravel-magellan dedoc/scramble
composer require --dev pestphp/pest pestphp/pest-plugin-laravel larastan/larastan laravel/pint laravel/boost
```
`clickbar/laravel-magellan` sert à PostGIS : types géographiques, `ST_Within`, `ST_Distance`.

---

## 2. Architecture du code

### 2.1 Principes
1. **Une seule couche métier.** Les règles (prix, quotas, statuts, scan, commission…) vivent dans des **Actions** et des **Services**. Les contrôleurs web, les composants Livewire et les contrôleurs API ne font que les appeler.
2. **Code en anglais, interface en français.** Les libellés passent par `lang/fr/*.php`.
3. **Statuts = Enums PHP** avec leurs transitions autorisées (`canTransitionTo()`).
4. **Services externes derrière une interface** (`Contracts`), avec une implémentation **Fake** active en local et en recette.
5. **Cloisonnement** : scopes globaux par compagnie et par promoteur, et Policies sur chaque modèle.
6. **Montants en entiers** (FCFA) ; jamais de `float`.
7. Les exigences du CDC sont citées dans les tests : `it('RG-13 calcule frais et commission', …)`.

### 2.2 Arborescence
```
app/
├── Actions/                 # Un cas d'usage = une classe (handle())
│   ├── Events/              # CreateEvent, ProposeToCompany, AcceptEvent, OpenSale, CancelEvent…
│   ├── Orders/              # CreateOrder, ConfirmPayment, ExpireOrder…
│   ├── Passes/              # IssuePass, TransferPass, CancelPass, RefundPass…
│   ├── Missions/            # GenerateMissions, ConfirmDeparture, ConfirmArrival…
│   ├── Scans/               # ValidateScan, SyncScans…
│   └── Breakdowns/          # ReportBreakdown, TakeAlert, AdvanceIntervention, CloseIntervention…
├── Services/                # PricingService, CapacityService, PassTokenService, PassCodeGenerator,
│                            # ZoneResolver, PunctualityService, PayoutCalculator, RefundCalculator
├── Contracts/               # PaymentGateway, SmsSender, WhatsAppSender, PushSender, RoutingProvider
├── Integrations/            # Fake/…, Payment/…, Sms/…, Osrm/…
├── Enums/                   # EventStatus, PassStatus, PassType, Offer, Segment, MissionStatus, ScanResult…
├── Models/
├── Policies/
├── Http/
│   ├── Controllers/Api/V1/{Customer,Conveyor,Tech,Webhooks}/
│   ├── Requests/            # Validation (web + API)
│   ├── Resources/Api/V1/
│   └── Middleware/          # EnsureCompanyScope, EnsureRole, EnsureDeviceNotRevoked…
├── Livewire/
│   ├── Admin/               # Console ACCES EVENT
│   ├── Company/{Commercial,Quality,Operations}/
│   ├── Promoter/
│   └── Site/                # Site spectateur
├── Jobs/  Events/  Listeners/  Notifications/
resources/views/
├── components/              # Design system : bouton, carte KPI, badge statut, tableau, carte (map)…
├── layouts/                 # site, admin, company, promoter
└── livewire/…
routes/
├── web.php                  # inclut site.php, admin.php, company.php, promoter.php
├── api.php                  # préfixe /api/v1
└── channels.php             # canaux Reverb
```

### 2.3 URLs des espaces
| Espace | Préfixe | Garde |
|---|---|---|
| Site spectateur | `/` | session (OTP) |
| Console ACCES EVENT | `/admin` | web + 2FA + rôle `super_admin` / `operateur` |
| Espace compagnie | `/compagnie` | web + 2FA + rôle `commercial` / `qualite` / `exploitation` + scope compagnie |
| Espace promoteur | `/promoteur` | web + 2FA + rôle `chef_projet` + scope promoteur |
| API | `/api/v1` | Sanctum (abilities : `customer`, `conveyor`, `tech`) |

### 2.4 Charte graphique
Les couleurs (marine `#0F1B2D`, orange `#C2410C`, vert de validation, rouge d'alerte) et la typographie sont définies une fois dans la configuration Tailwind. Tous les écrans passent par les composants Blade de `resources/views/components`. Les maquettes font foi (NF-15).

---

## 3. Mise en place de l'environnement (étape 0)

1. **Git** : `git init`, puis branche `main` protégée et branches `feat/…`. Le dépôt appartient à ACCES EVENT (livrable du CDC).
2. **Laravel Boost** (exigé par `CLAUDE.md`) : `composer require laravel/boost --dev && php artisan boost:install`.
3. **PostgreSQL + PostGIS + Redis** : avec Docker (Laravel Sail ou `docker-compose.yml`) ou en local.
   ```env
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=eventtrans
   REDIS_HOST=127.0.0.1
   QUEUE_CONNECTION=redis
   CACHE_STORE=redis
   BROADCAST_CONNECTION=reverb
   PAYMENT_DRIVER=fake
   SMS_DRIVER=log
   WHATSAPP_DRIVER=log
   PUSH_DRIVER=log
   ```
   Migration initiale : `CREATE EXTENSION IF NOT EXISTS postgis;`
4. Installation des packages (§ 1), puis `php artisan install:api`, `reverb:install` et `horizon:install`.
5. **Qualité** : `pint.json`, `phpstan.neon` (Larastan), Pest. Script `composer check` = Pint + Larastan + Pest.
6. **CI** (GitHub Actions ou GitLab CI) : `composer check` sur chaque merge request, avec PostgreSQL/PostGIS en service.
7. **Seeder de démo** `DemoEventSeeder` : concert d'Ebimpé du 15/11/2026, 5 points, grille Z1/Z2/Z3, bus, équipages, équipes T1 et T3, 2 640 Pass.

**Terminé quand** : `composer check` passe, `php artisan migrate --seed` fonctionne sur PostgreSQL, et la CI est verte.

> **Réalisé le 13/11/2026.**
> - Image Docker `imresamu/postgis:17-3.5`, une version multi-architecture qui fonctionne aussi sur les Mac Apple Silicon. Ports 5450 (PostgreSQL) et 6395 (Redis).
> - Client Redis : `predis`, car l'extension phpredis n'est pas requise.
> - Node 20 imposé par `.nvmrc`, car Vite 8 l'exige.
> - Le seeder de démo `DemoEventSeeder` est reporté aux étapes 3 et 4 : il a besoin des tables métier, qui n'existent pas encore.

---

## 4. Étapes de développement

Chaque étape se termine par une démo et la validation de sa condition de sortie. Les étapes 1 à 3 = **lot 1 du CDC**, 4 = **lot 2**, 5 et 6 = **lot 3**, 7 et 8 = **lot 4**, 9 et 10 = **lot 5**.

### Étape 1 — Socle : authentification, rôles, cloisonnement, audit
- Modèles `User`, `Customer`, `Staff` ; rôles et permissions (spatie) ; seeder des rôles.
- **Web** : Fortify (connexion, mot de passe oublié, 2FA obligatoire pour les rôles backoffice).
- **API** : `POST /auth/otp`, `POST /auth/verify` (OTP à 6 chiffres, valable 5 min, 5 essais, limitation par numéro et par IP), jeton Sanctum avec ses abilities.
- Middleware de scope compagnie et promoteur ; Policies de base.
- `AuditLog` en ajout seul, via un trait `Auditable` (NF-04).
- Layouts Blade des 4 espaces, menu selon le rôle, sélecteur d'événement (espace compagnie).
- Adaptateurs `SmsSender` (log) et écran « Messages envoyés » en local pour lire les OTP.

**Tests** : un commercial SOTRA ne voit pas une autre compagnie ; l'OTP expire ; la 2FA est exigée.

> **Réalisé le 13/11/2026** (63 tests verts).
> - **Modèles** : `User` (web), `Customer` et `Staff` (jetons Sanctum), `Company`, `Promoter`, `OtpCode`, `SmsMessage`, `AuditLog`.
> - **Enums** : `Role`, `Permission`, `Space`, `StaffRole`, `TokenAbility`, `OtpAudience`. Le seeder `RolesAndPermissionsSeeder` les reprend et peut être relancé sans risque.
> - **Web** : Fortify sans inscription publique ni passkeys. Mot de passe de 12 caractères minimum (lettres et chiffres). 2FA obligatoire, désactivable en local avec `TWO_FACTOR_ENFORCED=false`. Une fois connecté, `/espace` redirige vers `/admin`, `/compagnie` ou `/promoteur`.
> - **API** : `POST /api/v1/auth/otp`, `POST /api/v1/auth/verify`, `POST /api/v1/auth/logout`, `GET /api/v1/me`. Les erreurs renvoient `{message, code}` avec les codes `OTP_INVALID`, `OTP_EXPIRED`, `OTP_TOO_MANY_ATTEMPTS`, `OTP_NOT_FOUND`, `OTP_RESEND_TOO_SOON` et `ACCOUNT_NOT_ALLOWED`.
> - **Cloisonnement** : trait `BelongsToCompany` (scope global + rattachement automatique à la création) et middleware `space:*`.
> - **Audit** : trait `Auditable` ; table `audit_logs` protégée par un déclencheur PostgreSQL qui interdit UPDATE et DELETE.
> - **Design** : inspiré d'Apple (police système, fond `#f5f5f7`, cartes blanches très arrondies, barre latérale translucide). Composants dans `resources/views/components/ui`.
> - **Local** : comptes de démonstration créés par `DemoSeeder`, voir le README ; boîte d'envoi des SMS simulés sur `/dev/sms`.
> - Les éléments de menu des étapes suivantes sont affichés avec la mention « Bientôt ».

### Étape 2 — Référentiels (console + espace Exploitation)
- Console : **Compagnies** (contrat, commission par défaut, compte de reversement), **Promoteurs** (contacts, chefs de projet), **Lieux** (position), **Utilisateurs & rôles** (AD-08, AD-09, AD-12).
- Exploitation : **Bus** (numéro, immatriculation, capacité, statut), **Chauffeurs & convoyeurs**, **Équipes techniques** (code, type léger / lourd, membres), **Dépôts**, **PDA & terminaux** (inventaire, attribution, blocage).
- **Import Excel** des bus et du personnel.

**Terminé quand** : la SOTRA, ses bus et son personnel sont saisis ou importés, et chaque profil se connecte.

> **Réalisé le 13/11/2026** (106 tests verts).
> - **Console** (`/admin/...`) :
>   - Compagnies : contrat, commission par défaut, compte de reversement chiffré, masqué dans l'audit.
>   - Promoteurs, avec la liste de leurs chefs de projet.
>   - Lieux, avec position GPS.
>   - Utilisateurs & rôles : invitation par lien, rôles par espace, activation, réinitialisation de la 2FA.
>   - Journal d'audit en lecture seule.
> - **Exploitation** (`/compagnie/...`) :
>   - Bus : gamme, statut, dépôt, filtres par statut.
>   - Dépôts, avec position GPS.
>   - Chauffeurs, convoyeurs et techniciens. Désactiver un membre révoque ses jetons mobiles.
>   - Équipes techniques : code, type, techniciens, disponibilité.
>   - PDA et terminaux : attribution, blocage avec motif, et déconnexion de la personne à qui le terminal est attribué.
> - **Import Excel / CSV « tout ou rien »** pour les bus et le personnel :
>   - modèle téléchargeable ;
>   - erreurs signalées ligne par ligne ;
>   - mise à jour par immatriculation ou par téléphone ;
>   - correction du 0 initial que supprime Excel.
> - La désactivation de la 2FA par l'utilisateur (route Fortify) est neutralisée tant que la 2FA est obligatoire.
> - **Ajouts au menu** par rapport aux maquettes : « Lieux » et « Journal d'audit » (console), « Dépôts » (exploitation).
> - **Reporté** :
>   - le contrôle du terminal à chaque appel API arrive à l'étape 6 ;
>   - le secteur d'une équipe se choisit par événement à l'étape 7 ;
>   - les affectations des bus aux missions arrivent à l'étape 5.

### Étape 3 — Événement : création, négociation, ouverture de la vente
- Assistant Livewire en 4 étapes (AD-01 à AD-04), avec enregistrement du brouillon à tout moment :
  1. Informations (commission reprise de la fiche compagnie, modifiable) ;
  2. Points d'embarquement : la **zone est calculée** par `ZoneResolver` (distance PostGIS ou itinéraire), avec forçage tracé ; durée de trajet calculée + 30 % ;
  3. Grille : zone × offre × type de Pass (aller-retour, aller simple, retour simple) ;
  4. Canaux (points de vente désactivés), **zones de dépose et de reprise** (dessin du périmètre sur la carte), visuel, mise en avant.
- Statuts : `brouillon → propose → en_negociation ⇄ propose → accepte → en_vente → termine` (+ `annule`, `reporte`).
- Espace commercial : **Événements proposés** avec Accepter et Négocier. Le fil de négociation peut contenir une contre-proposition de prix ou de volume ; seule la console l'applique.
- Exploitation : **Plan de transport**, partie capacité : bus × places **par offre** (RG-08).
- `OpenSale` vérifie AD-05 et AD-06 : 4 étapes complètes, événement accepté, capacités saisies, prix pour chaque zone et offre utilisées, départ retour après la fin de l'événement. Ensuite, grille et points sont verrouillés.
- Kit promoteur : lien de vente et QR (PR-06).

**Terminé quand** (sortie du lot 1 CDC, avec l'étape 4) : un événement est configuré en 4 étapes, accepté par la compagnie, puis mis en vente.

> **Réalisé le 13/11/2026** (136 tests verts).
> - **Console** : liste des événements, configuration en 4 onglets et panneau latéral (statut, liste de contrôle, proposer, ouvrir la vente, fil d'échanges).
> - **Informations** :
>   - la commission est reprise de la compagnie ;
>   - les chefs de projet proposés sont ceux du promoteur choisi ;
>   - une fin prévue plus tôt que le début passe au lendemain ;
>   - un changement de date décale les horaires des points.
> - **Points** :
>   - la distance au lieu est calculée par PostGIS (à vol d'oiseau), la zone en découle (RG-06 : 25 km = zone 2) ;
>   - forçage de la zone avec justification ;
>   - convocation = départ − 15 min ;
>   - un retour plus tôt que l'aller passe au lendemain ;
>   - contrôles AD-06 (aller avant le début, retour après la fin prévue) ;
>   - pas de quota par point ;
>   - durée de trajet estimée à 30 km/h, modifiable.
> - **Grille** : zone × offre × type de Pass, préremplie avec l'exemple du CDC. L'aller-retour est obligatoire pour chaque zone utilisée. RG-07 est appliquée aux prix simples. La grille est journalisée en bloc (`pricing_updated`).
> - **Publication** :
>   - canaux (les points de vente physiques sont grisés, V2) ;
>   - visuel et mise en avant ;
>   - zones de dépose, reprise et accueil, chacune en cercle (centre + rayon). Une zone de dépose et une zone de reprise sont obligatoires.
> - **Cycle de vie** (`EventWorkflow`) : brouillon → proposé → (en négociation ⇄ proposé) → accepté → en vente. Informations, points et grille sont verrouillés sauf en brouillon et en négociation.
> - **Espace compagnie** :
>   - Événements proposés (Direction commerciale) : détail, grille, Accepter, Négocier avec objet et message ;
>   - Plan de transport, volet capacité (Exploitation) : bus × places par offre, flotte disponible affichée ;
>   - accueil : propositions à valider et prochains événements.
> - **Cloisonnement** : la compagnie ne voit pas les brouillons ; un promoteur ne voit que ses événements (`PromoterScope`).
> - **Données de démo** : concert d'Ebimpé accepté et prêt à ouvrir, match FIF proposé, brunch en brouillon.
> - **Reporté** :
>   - kit de promotion (lien de vente et QR), à l'étape 4 avec le site public ;
>   - notifications par e-mail des propositions et réponses ;
>   - génération des missions, à l'étape 5.

### Étape 4 — Vente : site spectateur, commande, paiement, Pass
- `PricingService` : prix par zone, offre et type ; frais 2 % ; commission 10 % ; **taux figés sur la commande** ; arrondi à l'entier (RG-11 à RG-16).
- `CapacityService` : capacité **par offre et par segment** ; blocage de 15 min avec un verrou Redis et une contrainte en base ; libération à l'expiration (tâche chaque minute) ; pas de surréservation (RG-08, NF-05).
- `CreateOrder` (10 Pass maximum, bénéficiaire possible pour un cadeau), puis paiement par l'adaptateur `PaymentGateway`.
- **FakeAggregator** : écran de simulation « Confirmer sur votre téléphone » (succès / échec / sans réponse), webhook signé, traitement **idempotent** (PA-02), réconciliation toutes les 5 minutes (PA-03).
- `IssuePass` : code `ET-AAMM-NNNN-COM` (séquence par événement et par commune), **jeton Ed25519**, QR en SVG.
- Notifications (log) : push, SMS, WhatsApp. Le Pass est envoyé avec un lien web.
- **Site spectateur** (Livewire, responsive, mobile d'abord) : Accueil, Choisir mon Pass, Paiement, Mon Pass, Mes Pass, Profil (SP-01 à SP-17, SP-20, SP-24).
- **API spectateur** : `/events`, `/events/{id}/offers`, `/orders`, `/orders/{id}/pay`, `/me/passes`, `/passes/{id}`, `/passes/{id}/transfer`.
- Transfert de Pass (SP-19, RG-04) : nouvelle version du jeton, l'ancienne est mise sur liste noire.
- Documentation API Scramble publiée pour l'équipe mobile.

**Terminé quand** (sortie du lot 2 CDC) : achat complet d'un Pass avec 2 % de frais, reçu sur le web, par l'API et dans les journaux SMS/WhatsApp ; aucun Pass en double sous des paiements concurrents (test).

> **Réalisé le 13/11/2026** (175 tests verts).
> - **Vente** :
>   - `CreateOrder` : contrôles, places bloquées 15 min, montants figés ;
>   - `PricingService` : RG-11 à RG-16, arrondi entier sans flottant ;
>   - `CapacityService` : places par offre **et par segment** (un aller simple ne consomme pas le retour).
> - **Concurrence** : les réservations sont sérialisées par un verrou de ligne PostgreSQL sur `event_offers`, plutôt que par Redis. C'est plus simple et tout aussi sûr.
> - **Paiement** :
>   - interface `PaymentGateway` et agrégateur simulé `FakeAggregator`, avec un webhook signé HMAC ;
>   - le webhook est enregistré dans `payment_events`, unique par (fournisseur, id) : traitement idempotent (PA-02) ;
>   - tâches planifiées `orders:expire` (chaque minute) et `payments:reconcile` (toutes les 5 min, PA-03) ;
>   - un paiement tardif sans places restantes passe la commande au statut « à rembourser » ; un double paiement est tracé.
> - **Pass** :
>   - code `ET-AAMM-NNNN-COM`, compteur par événement et par commune ;
>   - jeton **Ed25519** `ET1.<charge>.<signature>` (~170 caractères), clé dérivée de APP_KEY sauf si `PASS_SIGNING_SECRET_KEY` est défini ;
>   - QR en SVG ;
>   - « Offrir » : la version est incrémentée, ce qui invalide l'ancien QR et l'ancien lien.
> - **Envois** : SMS, WhatsApp et push simulés. Le lien du Pass est signé et contient sa version. Boîte d'envoi locale : `/dev/sms`.
> - **Site spectateur** (Livewire, session `customer` par OTP) :
>   - Accueil, Choisir mon Pass (sélection gardée dans l'URL après connexion), Paiement (attente avec `wire:poll`) ;
>   - Mes Pass, Mon Pass (QR), Offrir, Profil, Mon trajet (en attente de l'étape 6) ;
>   - lien public du Pass et simulateur de paiement `/simulateur-paiement/{référence}`.
> - **API spectateur** : 14 routes documentées par Scramble sur `/docs/api`. Les prix sont exposés en liste `{zone, pass_type, price}`.
> - **Kit de promotion du promoteur** (PR-06) : lien de vente, copier, QR à télécharger.
> - **Données de démo** : le concert d'Ebimpé est maintenant **en vente**.

### Étape 5 — Plan de transport et missions
- `GenerateMissions` : pour chaque point × offre × sens, ⌈vendus ÷ places⌉ missions. Affectation des Pass aller dans l'ordre d'achat, en gardant ensemble les Pass d'une même commande. Les ventes suivantes vont dans une mission qui a de la place.
- Écran **Bus & affectations** : bus, chauffeur, convoyeur, PDA par mission ; ajustement manuel ; bus de réserve et leur position d'attente.
- Écran commercial **Ventes** (CO-01 à CO-03, CO-06) : indicateurs, répartition par offre et par canal, tableau par point, export Excel.

**Terminé quand** : le plan de transport se génère à partir des ventes et chaque mission a son équipage.

### Étape 6 — Jour J : API convoyeur, scan hors ligne, suivi en direct
- API convoyeur : `/me/missions`, `/missions/{id}/manifest` (instantané versionné : Pass attendus, Pass acceptables au retour, liste noire, clé publique), `/scans/sync`, `/missions/{id}/positions`, `/depart`, `/arrive`.
- `ValidateScan` (même logique en ligne et à la synchronisation) : vert, orange ou rouge, avec motif (CV-08) ; bus plein (CV-09) ; `client_uuid` pour l'idempotence ; conflit = le scan le plus ancien gagne et l'autre est signalé (NF-01).
- Positions toutes les 15 s, stockées en PostGIS. **Arrivée** détectée quand le bus entre dans le périmètre de la zone de dépose.
- Diffusion Reverb : `event.{id}.ops`, `event.{id}.arrivals`.
- **Exploitation en direct** : compteurs, carte Leaflet des bus, tableau des affectations.
- « Suivre mon bus » : `/missions/{id}/live` et page web (SP-18).
- Notifications J-1 et H-2 (SP-21).

**Terminé quand** (sortie du lot 3 CDC) : **500 scans simulés hors ligne** sont synchronisés sans perte ni doublon (test automatisé), et un bus est suivi de bout en bout sur la carte.

### Étape 7 — Pannes et interventions
- `/breakdowns` (avec photos) ; **urgence automatique** (passagers à bord → urgent) ; entrée SMS de secours (CV-19).
- API technique : alertes de l'équipe et du secteur, triées ; `take` avec verrou (409 si déjà prise) ; étapes horodatées ; bus de réserve proches (distance PostGIS, temps OSRM) ; push et sonnerie (ST-06).
- `CloseIntervention` : réparé, remplacé ou remorquage. En cas de remplacement : nouvelle mission sur le bus de réserve, **transfert des Pass**, scan de contrôle attendu, notification des passagers (RG-20).
- Exploitation : carte « Panne signalée » avec **Envoyer l'équipe technique** et **Affecter un bus de remplacement** ; écran Pannes & interventions.
- Réclamation collective créée automatiquement si le retard dépasse 20 minutes.

**Terminé quand** : une panne simulée est traitée jusqu'au remplacement du bus, et l'alerte arrive en moins de 30 s (NF-07).

### Étape 8 — Espaces partenaires, avis, réclamations
- **Promoteur** : suivi en direct (PR-01 à PR-03, graphique du flux prévu et réel avec Chart.js), messages aux spectateurs (ciblage tous / point / bus / offre, journal et plafond, PR-04, PR-08), zones de dépose, fiche événement, bilan PDF.
- **Qualité** : indicateurs QU-01 (à l'heure ≤ +10 min), notes par critère, réclamations (Traiter), équipages, ponctualité, **rapport PDF**, bilans du pilote.
- **Spectateur** : avis sur 5 critères (SP-22), réclamation et suivi (SP-23).
- Console : **tableau de bord jour J** (AD-07), **Pass & ventes** : recherche, historique des scans, transfert, annulation (AD-10).

**Terminé quand** (sortie du lot 4 CDC) : les indicateurs du jeu d'essai correspondent aux calculs du CDC (tests).

### Étape 9 — Finance : annulations, remboursements, reversements
- `CancelPass` par le spectateur (RG-19 : 24 h avant le départ, 8 % retenus, 3 060 → 2 815) ; report (RG-18) ; annulation d'un événement ou d'un départ avec notification de masse (RG-17, AD-13).
- Remboursements : par API de l'agrégateur, ou file de traitement manuel tracée (PA-04).
- **Reversements** : relevé automatique à J + 5 jours ouvrés (jours fériés paramétrables), validation, paiement, justificatif (PA-05, AD-11, CO-05).

**Terminé quand** : le reversement du jeu d'essai donne exactement 90 % de la recette brute hors frais, moins les remboursements.

### Étape 10 — WhatsApp, intégrations, pilote (lot 5 CDC)
- Bot WhatsApp sur l'API WhatsApp Business : menus, paiement, envoi du Pass, « Mes Pass » (WA-01 à WA-05).
- Branchement des **vrais fournisseurs** : agrégateur, SMS, WhatsApp, FCM. Il suffit de changer les variables `*_DRIVER`.
- ACCES TICKETS et BIG ACCESS (exigences souhaitables).

### Étape 11 — Durcissement et mise en production (en continu, puis avant le pilote)
- **Test de charge** : 300 commandes par minute sans double vente ; API < 500 ms pour 95 % des requêtes (NF-05, NF-06).
- Sécurité : HTTPS, en-têtes, limitation des tentatives, revue des Policies, aucune donnée de carte stockée (PA-06).
- Données personnelles : positions collectées seulement pendant les missions ; mentions légales ; CGV acceptées à l'achat (NF-12, NF-14).
- Supervision (Horizon, logs, alertes), sauvegardes quotidiennes conservées 30 jours avec restauration testée (NF-08).
- Docker, environnements recette et production, déploiement automatisé.
- Livrables : documentation API, manuel d'installation, guides utilisateurs, cahier de recette.

---

## 5. Règles de travail

### Une fonctionnalité est terminée quand
- [ ] Les exigences du CDC concernées sont couvertes, et leurs identifiants sont cités dans la PR et dans les tests.
- [ ] La logique est dans une Action ou un Service, et non dans un contrôleur ou un composant Livewire.
- [ ] Les tests Pest passent (cas nominal + cas de refus), et `composer check` est vert.
- [ ] Les Policies sont vérifiées : une autre compagnie ou un autre promoteur n'a pas accès.
- [ ] Les écrans respectent la maquette (libellés, disposition) et fonctionnent sur mobile.
- [ ] Les endpoints API sont documentés (Scramble) et testés avec leurs codes d'erreur.
- [ ] Les actions sensibles sont auditées.

### Conventions
- Branches : `feat/etape-04-paiement`, `fix/…`. Commits courts, en français ou en anglais, mais de façon homogène dans le dépôt.
- API : réponses `{ "data": … }` ; erreurs `{ "message", "code", "errors" }` ; codes métier stables (`PASS_ALREADY_USED`, `CAPACITY_REACHED`…), traduits côté application.
- Dates en UTC en base ; affichage `Africa/Abidjan` (UTC+0) ; ISO 8601 dans l'API.
- Toute nouvelle règle ou hypothèse est ajoutée au journal des décisions de [SPECIFICATIONS.md](SPECIFICATIONS.md).

### Tests indispensables (jeu d'essai du CDC)
| Test | Attendu |
|---|---|
| RG-13 | 3 000 → frais 60, total 3 060, commission 300, compagnie 2 700 |
| RG-19 | 3 060 → retenue 245, remboursement 2 815 ; refus à moins de 24 h ou si le Pass est scanné |
| RG-08 | Vente refusée quand bus × places de l'offre est atteint, même sous concurrence |
| RG-03 | Second scan du même segment → rouge ; retour accepté sans aller |
| RG-09 | Pass Premium dans un bus Standard → rouge « offre différente » |
| CV-08 | Autre bus du même point → orange |
| Synchronisation | 500 scans hors ligne, deux appareils, doublons → aucune perte, conflits signalés |
| PA-02 | Webhook reçu 3 fois → un seul Pass |
| QU-01 | Départ à +10 min → à l'heure ; à +11 min → en retard |
