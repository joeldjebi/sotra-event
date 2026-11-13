# EVENT TRANS — Document de référence technique (équipe web & API)

> **Le Pass en route** · Partenariat ACCES EVENT × compagnies de transport (première compagnie : SOTRA)
> Ce document **complète** le *Cahier des charges développeurs* (CDC, 12 nov. 2026). Il ne le remplace pas.
> Il traduit le CDC en choix techniques, modèle de données, API et algorithmes pour notre équipe.

| Version | Date | Source | Contenu |
|---|---|---|---|
| 0.1 → 0.4 | 2026-11-12 | Présentation, réponses, maquettes mobiles et web | Construction progressive du cadrage |
| **1.0** | 2026-11-13 | **Cahier des charges développeurs** | Alignement complet sur le CDC ; référence pour le développement |

## 0. Ordre de priorité des sources
1. **CDC développeurs** (exigences SP-, CV-, ST-, AD-, CO-, QU-, PR-, WA-, RG-, PA-, NF-) : il fait foi.
2. **Maquettes** (16 écrans) : elles font foi pour la disposition et les libellés (NF-15). Leurs chiffres sont des exemples.
3. **Ce document** : choix techniques et précisions là où le CDC est muet.
4. **Journal des décisions** (§ 12).

Les identifiants du CDC (ex. `RG-08`, `CV-08`) sont repris dans le code (tests, commentaires) pour garder la traçabilité.

---

## 1. Périmètre de notre équipe

| Nous livrons | Hors de notre périmètre |
|---|---|
| **API EVENT TRANS** unique, qui porte toutes les règles de gestion | Apps mobiles spectateur, convoyeur et technique (équipe mobile) |
| **Site web spectateur** responsive (mêmes écrans que l'app, § 4 CDC) | Transport régulier, billetterie d'accès (ACCES TICKETS) |
| **Console ACCES EVENT** (§ 7 CDC) | Points de vente physiques (désactivés en V1, AD-04) |
| **Espace compagnie** : commercial, qualité, exploitation / technique (§ 8 CDC) | |
| **Espace promoteur** (§ 9 CDC) | |
| Intégrations : agrégateur, SMS, push (FCM), cartographie | |
| Bot WhatsApp (§ 10 CDC, **lot 5**) : **reporté sur décision du 12/11, à reconfirmer** (Q-A1) | |

---

## 2. Profils, espaces et authentification

| Profil | Application | Authentification | Portée des données |
|---|---|---|---|
| Spectateur | Site web, app (API), WhatsApp | Téléphone + OTP | Ses commandes et ses Pass |
| Convoyeur | App convoyeur (API) | Téléphone + OTP, appareil enregistré | Ses missions du jour |
| Technicien | App technique (API) | Téléphone + OTP | Alertes de son équipe et de son secteur |
| Super Admin / opérateur ACCES EVENT | Console | Identifiant + mot de passe + **2FA** | Tout |
| Direction commerciale | Espace compagnie | Mot de passe + 2FA | Sa compagnie |
| Direction qualité | Espace compagnie | Mot de passe + 2FA | Sa compagnie |
| Exploitation / Direction technique | Espace compagnie | Mot de passe + 2FA | Sa compagnie |
| Chef de projet transport | Espace promoteur | Mot de passe + 2FA | Ses événements |

- L'**espace compagnie** est unique : son menu dépend du rôle (§ 8 CDC). Le sélecteur d'événement en haut à droite filtre tous les écrans.
- Le profil « Exploitation compagnie » du CDC (écran « non maquetté ») et la maquette web « Direction technique » (Exploitation en direct, Plan de transport, Bus & affectations, Chauffeurs & convoyeurs, Pannes & interventions, Équipes service technique, PDA & terminaux) sont traités comme **un seul profil** (Q-A2).
- Le chauffeur n'a **pas de compte** : c'est une fiche Personnel (rôle chauffeur).
- Commission, frais et grille tarifaire ne sont **modifiables que dans la console**, et chaque modification est journalisée (règles d'accès § 2 CDC).

---

## 3. Règles de gestion — synthèse et précisions d'implémentation

### 3.1 Pass (RG-01 à RG-05)
- Types : `aller_retour`, `aller_simple`, `retour_simple`. Un Pass = 1 passager + 1 événement + 1 point + 1 offre + 1 ou 2 **segments**.
- Code `ET-AAMM-NNNN-COM` : AAMM = année et mois **de l'événement** ; NNNN = séquence (par événement, à confirmer, Q-B1) ; COM = code commune sur 3 lettres.
- QR = jeton signé **Ed25519** (`sodium_crypto_sign`) qui contient l'identifiant du Pass, l'événement, les segments et une version. L'app convoyeur reçoit la **clé publique** de vérification.
- Chaque segment n'est scanné qu'une fois. Le segment retour reste valable même si l'aller n'a pas été scanné.
- Transfert (« Offrir ») possible tant que l'aller n'est pas scanné. Il crée une **nouvelle version** du jeton ; l'ancien est mis sur liste noire (diffusée dans les manifestes).
- Au retour : n'importe quel bus **du même point et de la même offre**, dans la limite des places.

**Statuts** (machine à états, transitions interdites rejetées) :
`en_attente_paiement` → `valide` | `expire` ; `valide` → `aller_utilise` | `retour_utilise` | `annule` ; `aller_utilise` / `retour_utilise` → `termine` ; `annule` → `rembourse`.
Pour un Pass aller simple, le scan aller mène directement à `termine` (idem pour un retour simple).
L'annulation par le spectateur (RG-19) mène à `annule` → `rembourse`.

### 3.2 Capacité, quotas et bus (RG-08 à RG-10)
- Avant l'ouverture des ventes, la compagnie saisit **par offre** : nombre de bus × places par bus. Capacité de l'offre = bus × places.
- Le **quota est global par offre** (pas par point). La vente d'une offre s'arrête quand sa capacité est atteinte. Pas de surréservation, pas d'heure limite de vente.
- **Blocage de places** pendant 15 min au statut `en_attente_paiement` : verrou atomique (Redis + contrainte en base) pour empêcher toute double vente (NF-05).
- Aller simple et retour simple : vendus seulement s'il reste de la place sur le segment concerné. La capacité est donc suivie **par segment** (aller / retour).
- Après les ventes : **bus nécessaires** par point et par offre = ⌈Pass vendus ÷ places par bus⌉. Ce calcul sert à générer les **missions**.
- Premium et Gold : **bus dédiés**, une seule offre par bus. Un Pass ne monte que dans un bus de son offre.

### 3.3 Prix, frais, commission (RG-06, RG-07, RG-11 à RG-16)
- La zone est **calculée** à partir de la distance du point au lieu : Z1 < 15 km, Z2 de 15 à 25 km, Z3 > 25 km. Le calcul est automatique, avec forçage possible et tracé (Q-B2).
- Prix = grille de l'événement (zone × offre × type de Pass), en FCFA hors frais. Le prix « dès » est le minimum de la grille.
- Frais client = 2 % du prix, acquis à ACCES EVENT. Commission = 10 % du prix hors frais. Part compagnie = 90 %.
- **Taux figés sur chaque vente** : chaque ligne de commande garde prix, taux de frais et taux de commission.
- Montants en **entiers FCFA**, arrondis à l'unité. Exemple de contrôle : 3 000 → frais 60 → total 3 060 → commission 300 → compagnie 2 700.
- Reversement = 90 % de la recette brute hors frais − remboursements, à J + 5 jours ouvrés (calendrier des jours fériés ivoiriens à paramétrer).

### 3.4 Annulation, report, panne (RG-17 à RG-20)
| Cas | Règle | Calcul |
|---|---|---|
| Événement ou départ annulé | Remboursement intégral, frais compris | 100 % du total payé |
| Événement reporté | Pass valable à la nouvelle date ; remboursement sur demande sous 48 h, sans retenue | 100 % |
| Annulation par le spectateur | Jusqu'à 24 h avant le départ aller, Pass non scanné ; remboursement sous 72 h | total − 8 % du total (3 060 → 245 retenus → 2 815) |
| Bus remplacé | Pass transférés automatiquement sur le bus de réserve ; retard tracé | — |

### 3.5 Résultats du scan (CV-08)
| Couleur | Cas |
|---|---|
| **Vert** | Pass valide pour cet événement, ce sens, cette offre, ce bus |
| **Orange** (montée autorisée, tracée) | Valide mais **autre bus** du même point, ou **autre point** |
| **Rouge** + motif | Déjà utilisé · autre événement · offre différente de celle du bus · segment non acheté · annulé / remboursé · falsifié (signature invalide) · ancien QR après transfert |
| Blocage | Bus plein (CV-09) |

### 3.6 Indicateurs (calculs du CDC)
- **À l'heure** : départ réel confirmé (CV-14) ≤ départ prévu + 10 min.
- **Note d'un chauffeur ou d'un convoyeur** : moyenne des avis des passagers de son bus.
- **Bus arrivé** : position GPS dans le périmètre de la zone de dépose, ou arrivée confirmée par le convoyeur. Ses passagers scannés comptent alors comme arrivés.
- **Flux prévu** : départ prévu + **durée de trajet estimée par point**, un champ à saisir par point (Q-B5).
- **Pass refusés** : scans rouges + oranges « mauvais bus » (QU-01).

---

## 4. Architecture et choix techniques

Le CDC recommande « Node.js (NestJS) ou Laravel » et accepte une alternative argumentée pour le web.

| Couche | Choix | Justification |
|---|---|---|
| API | **Laravel 13** (PHP 8.3), REST JSON versionnée `/api/v1`, OpenAPI généré (Scramble) | Proposé par le CDC ; projet déjà initialisé |
| Web (site, console, espaces) | **Laravel uniquement : Blade + Livewire 3 (+ Alpine) + Tailwind**. Pas de framework JavaScript | Décision du 13/11 ; une seule base de code, rendu serveur adapté à la 3G |
| Base de données | **PostgreSQL + PostGIS** (positions, périmètres, distances) | Recommandé par le CDC ; validé le 13/11 |
| Cache, verrous, files | **Redis** + **Laravel Horizon** | Blocage des places, tâches (notifications, réconciliation, reversements) |
| Temps réel | **Laravel Reverb** (WebSocket) | Tableaux de bord jour J, carte, alertes ; repli sur un rafraîchissement ≤ 30 s (NF-06) |
| Authentification | **Sanctum** (jetons mobiles), **Fortify** (web + 2FA TOTP) | NF-03 |
| Rôles | `spatie/laravel-permission` + cloisonnement par compagnie et par promoteur (policies, scopes globaux) | Règles d'accès § 2 CDC |
| Audit | Table d'audit en ajout seul (tarifs, commissions, transferts, remboursements, reversements, droits) | NF-04 |
| PDF / Excel | DomPDF ou Browsershot ; Laravel Excel | QU-05, CO-06, PA-05 |
| Hébergement | Conteneurs Docker, environnements **recette** et **production** séparés | § 13 CDC ; fournisseur à décider (NF-13) |

**Adaptateurs remplaçables** (interface + implémentation simulée en dev et en recette) :
| Service | Implémentation provisoire | Implémentation cible |
|---|---|---|
| Paiement | `FakeAggregator` : succès / échec / attente, webhook signé simulé, réconciliation | Agrégateur ACCES EVENT (documentation à recevoir, PA-01) |
| SMS | Journal + écran de consultation | Passerelle SMS (à choisir) |
| WhatsApp | Journal | API WhatsApp Business / BSP |
| Push | Journal | Firebase Cloud Messaging |
| Cartographie | OpenStreetMap / OSRM (distance, temps) | Google Maps Platform si nécessaire |

---

## 5. Modèle de données (tables principales)

```
companies            id, name, contract_ref, default_commission_rate, payout_account (json), logo
users                id, name, email, phone, password, 2fa, company_id?, promoter_id?, type(web|staff|customer)
staff                id, company_id, role(chauffeur|convoyeur|technicien), name, phone, matricule, team_id?
devices              id, company_id, serial, label, staff_id?, app_version, last_sync_at, revoked_at
tech_teams           id, company_id, code(T1…), type(léger|lourd), sector_id?, status(disponible|en_intervention), last_position (geography)
depots               id, company_id, name, address, position, phone
buses                id, company_id, number, plate, capacity, status(en_service|reserve|en_panne)

promoters            id, name, contacts (json)
venues               id, name, address, position (geography)

events               id, name, venue_id, promoter_id, company_id, transport_lead (name, phone),
                     starts_at, ends_at, doors_open_at, commission_rate, fee_rate, channels (json),
                     status(brouillon|propose|en_negociation|accepte|en_vente|termine|annule|reporte),
                     visual, featured
event_negotiations   id, event_id, author_id, subject(tarifs|volume|autre), message, created_at
event_offer_capacity id, event_id, offer(standard|premium|gold), bus_count, seats_per_bus     ← RG-08
boarding_points      id, event_id, commune, commune_code, place, position, distance_km, zone, zone_forced,
                     depart_aller_at, depart_retour_at, travel_minutes
price_grid           id, event_id, zone, offer, pass_type, price
drop_zones           id, event_id, name, usage(depose|reprise|accueil), time?, perimeter (geography polygon)
sectors              id, event_id, name

missions             id, event_id, bus_id, offer, direction(aller|retour), boarding_point_id,
                     driver_id, conveyor_id, device_id, planned_departure_at, actual_departure_at,
                     arrived_at, status(planifiee|embarquement|en_route|arrivee|incident|terminee),
                     replaced_by_mission_id?
positions            id, mission_id | tech_team_id, recorded_at, location (geography), accuracy   (partitionnée)

customers            id, phone, first_name, last_name, locale
orders               id, customer_id, event_id, channel(web|app|whatsapp|pdv), subtotal, fee_amount, total,
                     fee_rate, commission_rate, status, expires_at
passes               id, order_id, holder_customer_id, passenger_name, offer, pass_type, boarding_point_id,
                     code, token_version, status, price, commission_amount, company_amount,
                     outbound_mission_id?, return_mission_id?
pass_transfers       id, pass_id, from_customer_id, to_phone, created_at
scans                id, pass_id, mission_id, segment(aller|retour|controle), staff_id, device_id,
                     scanned_at, offline(bool), result(vert|orange|rouge), reason, client_uuid (unique)

payments             id, order_id, method(orange|mtn|moov|wave|carte), msisdn, provider_ref, amount, status, raw (json)
refunds              id, pass_id, reason(annulation_evenement|report|client|reclamation), amount, retained_fee, status, mode(api|manuel)
payouts              id, company_id, event_id, gross, refunds, commission, net, due_date, status(calcule|valide|paye), proof

breakdown_alerts     id, mission_id, type, details, photos, location, accuracy, place_label,
                     passengers_on_board, urgency(urgent|normal), status, tech_team_id?, channel(app|sms)
interventions        id, alert_id, number(PN-NNNN), tech_team_id, steps (horodatages), result(repare|remplace|remorquage),
                     diagnosis, parts, duration_min, photos, replacement_bus_id?

reviews              id, pass_id, mission_id, punctuality, comfort, conveyor_courtesy, driver_driving, information, comment
complaints           id, pass_id?, mission_id?, alert_id?, title, description, status(ouverte|en_cours|traitee), handled_by, resolution
messages             id, event_id, author_id, target (json : tous|point|bus|offre), body, channels, recipients_count, status
notifications_log    id, customer_id, channel(push|sms|whatsapp), template, payload, status
audit_logs           id, actor_id, action, subject, before, after, created_at
```

---

## 6. API (`/api/v1`, JSON, Sanctum)

Les chemins reprennent ceux du CDC (§ 13), complétés.

**Spectateur** (site web et app)
| Méthode | Chemin | Exigences |
|---|---|---|
| POST | `/auth/otp`, `/auth/verify`, `/auth/logout` | Connexion OTP (avec limitation des tentatives) |
| GET / PATCH | `/me` | SP-01, SP-24 |
| GET | `/events?featured=&q=` | SP-01 à SP-03 |
| GET | `/events/{id}`, `/events/{id}/offers` | SP-04 à SP-05 : points, horaires, types, offres, prix par zone, disponibilité |
| POST | `/orders` | SP-06 à SP-08 : bloque les places 15 min ; `beneficiary_phone` pour un cadeau |
| POST | `/orders/{id}/pay` · GET `/orders/{id}` | SP-09 à SP-13 |
| POST | `/payments/webhook/{provider}` | PA-02 : signature vérifiée, traitement idempotent |
| GET | `/me/passes`, `/passes/{id}` | SP-14 à SP-17, SP-20 |
| POST | `/passes/{id}/transfer` | SP-19, RG-04 |
| GET | `/missions/{id}/live` | SP-18 : position, heure d'arrivée estimée, statut |
| POST | `/passes/{id}/review` · `/passes/{id}/complaints` · `/passes/{id}/cancel` | SP-22, SP-23, SP-25 |
| GET | `/me/complaints` (suivi des réclamations) | SP-23 |
| — | `cancellation` et `refund` dans le détail d'un Pass (montants, date limite, statut du remboursement) | SP-25 |
| GET | `/me/notifications` · POST `/me/devices` | SP-21, jeton FCM |

**Convoyeur**
| Méthode | Chemin | Exigences |
|---|---|---|
| GET | `/me/missions?date=` | CV-01 à CV-03 |
| GET | `/missions/{id}/manifest` | Pass attendus, Pass acceptables au retour (même point et même offre), liste noire, clé publique, version |
| POST | `/scans/sync` | Lot de scans (avec `client_uuid` pour l'idempotence) → résultats et conflits |
| POST | `/missions/{id}/positions` | Lot de positions (une toutes les 15 s) |
| POST | `/missions/{id}/depart` · `/missions/{id}/arrive` · `/missions/{id}/status` | CV-05, CV-14 |
| POST | `/breakdowns` | CV-15 à CV-19 (multipart avec photos) |
| POST | `/sms/inbound/{provider}` | CV-19 : alerte panne en mode dégradé, reçue par SMS au format codé |

**Technicien**
| Méthode | Chemin | Exigences |
|---|---|---|
| GET / PATCH | `/me/team` | ST-01 : disponibilité |
| GET | `/breakdowns?status=open` | ST-02, ST-03 : triées par urgence puis ancienneté, avec distance et temps |
| POST | `/breakdowns/{id}/take` | ST-04 : verrou, réponse 409 si déjà prise |
| GET | `/reserve-buses?near=` | ST-05 |
| GET | `/interventions/{id}` · POST `/interventions/{id}/steps` | ST-08 à ST-11 |
| POST | `/interventions/{id}/close` | ST-12 à ST-16 : en cas de remplacement, transfert de la mission et des Pass |

**Espaces web** : ils passent par des contrôleurs Inertia qui partagent les mêmes services métier. Les rapports sont aussi exposés en API (`GET /reports/sales`, `/reports/quality`, `/reports/arrivals`) pour de futures intégrations (BIG ACCESS).

**Temps réel (Reverb)** — canaux privés :
- `event.{id}.ops` : missions, positions, alertes ;
- `event.{id}.arrivals` : promoteur ;
- `team.{id}` : nouvelles alertes (ST-06).

---

## 7. Algorithmes clés

1. **Réservation** : transaction et verrou Redis `capacity:{event}:{offer}:{segment}`. Le compteur est décrémenté à la création de la commande et relâché à l'expiration (tâche planifiée chaque minute), ou confirmé par le webhook.
2. **Génération des missions** (bouton « Générer le plan » + ajustement manuel) : pour chaque point × offre × sens, ⌈vendus ÷ places⌉ missions, puis affectation des Pass aller dans l'ordre d'achat, en gardant ensemble les Pass d'une même commande. Les achats faits après la génération vont dans la première mission qui a de la place, ou dans une nouvelle mission si la capacité de l'offre le permet.
3. **Manifeste hors ligne** : instantané versionné. L'app télécharge les différences. Au retour, le manifeste contient tous les Pass du point et de l'offre.
4. **Synchronisation des scans** : traitement idempotent par `client_uuid`. Si deux scans du même segment arrivent de deux appareils, le **plus ancien** est retenu et l'autre est marqué en conflit (copie suspectée, NF-01), avec une alerte au tableau de bord.
5. **Transfert après panne** : la mission d'origine passe en `incident`, puis une mission de remplacement est créée (bus de réserve, même convoyeur). Les Pass y sont rattachés, un scan de `controle` est attendu, et les passagers sont notifiés.
6. **Arrivée** : à chaque position reçue, test d'appartenance au périmètre de la zone de dépose (`ST_Within`).
7. **Reversement** : à la clôture de l'événement, calcul du relevé, puis validation dans la console, paiement et dépôt du justificatif.
8. **Réconciliation** : interrogation de l'agrégateur toutes les 5 minutes pour les paiements en attente depuis plus de 2 minutes.

---

## 8. Correspondance avec les lots du CDC

| Lot CDC | Notre contenu (web + API) | Condition de sortie |
|---|---|---|
| 1 — Socle et console | Socle Laravel, authentification (OTP, 2FA), rôles, audit ; console : événements (4 étapes), compagnies, promoteurs, utilisateurs ; paiement simulé + webhook | Événement configuré, vente ouverte, paiement test confirmé par webhook |
| 2 — Vente | Site web spectateur ; API spectateur ; Pass + QR signé ; SMS et notifications (adaptateurs) | Achat complet avec frais de 2 %, Pass reçu |
| 3 — Exploitation jour J | API convoyeur (missions, manifeste, synchronisation, positions, départ) ; génération des missions ; « Suivre mon bus » ; espace Exploitation (plan, affectations, carte en direct) | 500 scans hors ligne synchronisés sans perte ni doublon |
| 4 — Incidents et espaces partenaires | API technique, pannes et remplacement ; espace commercial et espace qualité ; espace promoteur ; avis, réclamations, PDF | Panne simulée traitée jusqu'au remplacement du bus ; indicateurs conformes |
| 5 — WhatsApp et pilote | Bot WhatsApp ; ACCES TICKETS ; reversements complets ; pilote | Bilan J+1 produit |

---

## 9. Écrans à concevoir (non maquettés, livrable « maquettes complémentaires »)
- **Console** : tableau de bord jour J, compagnies, promoteurs, Pass & ventes, reversements, utilisateurs & rôles, annulation / report.
- **Commercial** : événements proposés (fil de négociation), offres & grille (en lecture seule, car modifiables uniquement dans la console), demande par commune, reversements, rapports.
- **Qualité** : avis, réclamations, ponctualité, équipages, bilans du pilote.
- **Exploitation** : plan de transport (capacité par offre, génération des missions), bus & affectations, chauffeurs & convoyeurs, pannes & interventions, équipes, PDA & terminaux.
- **Promoteur** : fiche événement, zones de dépose, historique des messages, bilan.
- **Site spectateur** : Mes Pass, Mon trajet, avis, réclamation, profil, annulation.

---

## 10. Points ouverts

### A. Périmètre
| # | Question |
|---|---|
| Q-A1 | Le **bot WhatsApp** est obligatoire (M) dans le CDC (lot 5), mais il a été reporté le 12/11. Faut-il le garder en lot 5 ? |
| Q-A2 | Profil « Exploitation compagnie » (CDC) = maquette « Direction technique » ? Un seul profil, ou deux (coordination / dépôt) ? |

### B. Règles à préciser
| # | Question |
|---|---|
| Q-B1 | Le numéro NNNN du code Pass est-il séquentiel par événement, par mois ou global ? (4 chiffres = 9 999 au maximum) |
| Q-B2 | **Contradiction CDC** : § 2 « la Direction commerciale gère offres et grille tarifaire », mais les règles d'accès disent « grille modifiable uniquement dans la console ». Proposition : la compagnie propose (négociation), et seule la console enregistre. |
| Q-B3 | **Limite de Pass par commande** (SP-06) : quelle valeur ? Proposition : 10. |
| Q-B4 | Prix aller simple / retour simple : seul Standard Z1 = 2 000 est connu. Les autres prix seront saisis dans la console : est-ce bien le cas ? |
| Q-B5 | Durées de trajet par point (flux prévu) : saisies par l'admin ou calculées par l'outil d'itinéraire ? |
| Q-B6 | Commission « par défaut » de la compagnie (AD-08) reprise automatiquement à la création de l'événement : à confirmer. |

### C. Technique et intégrations
| # | Question |
|---|---|
| Q-C1 | Passer de MySQL à **PostgreSQL + PostGIS**, comme le recommande le CDC ? |
| Q-C2 | Nom de l'**agrégateur de paiement** et date de remise de sa documentation (PA-01). Simulation en attendant. |
| Q-C3 | Fournisseurs **SMS** et **WhatsApp** (BSP) ; numéro court ou long pour le mode dégradé panne par SMS (CV-19). |
| Q-C4 | Pic de commandes par minute à tenir (NF-05). |
| Q-C5 | Hébergement : cloud local ou international (NF-13) ? |
| Q-C6 | QR qui change régulièrement dans l'app (NF-02) : retenu ou non ? Notre jeton prévoit une version, donc c'est possible. |
| Q-C7 | Système SOTRA existant à connecter (flotte) ? Sinon, saisie manuelle et import Excel des bus et du personnel. |
| Q-C8 | ACCES TICKETS / BIG ACCESS (S, lot 5) : documentation d'API disponible ? |

---

## 11. Données d'essai
Un seeder reproduit l'exemple du CDC : Concert au stade d'Ebimpé, 15/11/2026, 5 points (Yopougon, Abobo, Cocody, Marcory, Plateau), grille Z1/Z2/Z3, bus 12, 27, 08, 33, 19, 41 (réserve), 52 (réserve), équipes T1 et T3. Il sert à la démo et aux tests des calculs (RG-13, RG-19, ponctualité, notes).

---

## 12. Journal des décisions
| Date | Décision |
|---|---|
| 2026-11-12 | Périmètre de l'équipe : web, backoffice et API. Les apps mobiles sont hors périmètre. |
| 2026-11-12 | Paiement et envois (SMS, WhatsApp) **simulés** derrière des adaptateurs, en attendant les fournisseurs. |
| 2026-11-12 | Suivi des bus : GPS (téléphone ou PDA du convoyeur) + statuts manuels. Scan hors ligne obligatoire. |
| 2026-11-13 | **Le CDC développeurs devient la référence fonctionnelle.** Il remplace les propositions faites le 12/11 là où il statue (offres en bus dédiés, quotas par offre, types de Pass, annulation à 24 h avec 8 % de frais, OTP pour le personnel, 2FA sur le web). |
| 2026-11-13 | Stack : **back et front 100 % Laravel** (Blade + Livewire + Tailwind), PostgreSQL/PostGIS, Redis, Reverb, Sanctum, Fortify. Plan d'exécution : [GUIDE_DEVELOPPEMENT.md](GUIDE_DEVELOPPEMENT.md). |
| 2026-11-13 | Profils Exploitation et Direction technique fusionnés en **un seul profil « Exploitation »**. Chauffeur sans compte. |
| 2026-11-13 | Grille : la compagnie **propose** via la négociation, seule la console **enregistre** ; grille verrouillée à l'ouverture des ventes (Q-B2 résolue). |
| 2026-11-13 | WhatsApp maintenu en **lot 5** ; envois WhatsApp simulés dès le lot 2 ; demande d'accès à WhatsApp Business à lancer par ACCES EVENT (Q-A1 résolue). |
| 2026-11-13 | 10 Pass maximum par commande (réglable) ; NNNN = séquence par événement et par commune ; durée de trajet calculée + 30 %, modifiable ; objectif de charge 300 commandes par minute ; commission par défaut reprise de la fiche compagnie (Q-B1, B3, B5, B6, C4 résolues). |
| 2026-11-13 | Pilote : agrégateur, SMS et WhatsApp simulés jusqu'à réception des accès ; cloud international (Europe de l'Ouest) ; flotte SOTRA importée par Excel ; QR fixe signé, sans rafraîchissement (Q-C5, C6, C7). |
| 2026-11-13 | Zones (RG-06) : calculées sur la distance **à vol d'oiseau** (PostGIS) en attendant l'outil d'itinéraire ; bornes : < 15 km, 15 à 25 km inclus, > 25 km ; forçage justifié possible. |
| 2026-11-13 | Événement verrouillé (informations, points, grille, taux) dès sa proposition à la compagnie ; à nouveau modifiable pendant une négociation. La publication (canaux, zones de dépose, visuel) reste modifiable. |
| 2026-11-13 | Zones de dépose et de reprise : cercle centre + rayon (150 m par défaut) ; au moins une zone de dépose et une de reprise avant l'ouverture des ventes. |
| 2026-11-13 | La compagnie peut fixer bus × places par offre dès la proposition, et jusqu'à l'ouverture des ventes. |
