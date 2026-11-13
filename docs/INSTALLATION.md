# EVENT TRANS — Manuel d'installation et d'exploitation

Pour l'équipe technique qui installe, déploie et surveille la plateforme (recette et production).
Le développement local est décrit dans le `README.md`.

---

## 1. Architecture déployée

| Service | Rôle | Commande du conteneur |
|---|---|---|
| `web` | Site spectateur, espaces web, API `/api/v1` (nginx + PHP-FPM, port 8080) | `web` |
| `horizon` | Files Redis : remboursements, notifications de masse | `horizon` |
| `scheduler` | Tâches planifiées (voir § 5) | `scheduler` |
| `reverb` | Temps réel (WebSocket) des écrans jour J | `reverb` |
| `pgsql` | PostgreSQL 17 + PostGIS 3.5 | — |
| `redis` | Cache, sessions, verrous, files | — |

Une seule image (`Dockerfile`) sert tous les rôles. PostgreSQL et Redis peuvent être des services
managés : il suffit de retirer leurs blocs de `docker-compose.prod.yml` et de renseigner `DB_HOST` et `REDIS_HOST`.

Le TLS (HTTPS) est terminé devant les conteneurs (répartiteur de charge ou proxy de l'hébergeur).
L'hébergement reste à choisir (NF-13 : cloud local ou international).

---

## 2. Prérequis

- Un hôte Linux avec Docker 24+ et Docker Compose v2 : 4 vCPU, 8 Go de RAM et 80 Go de disque pour le pilote.
- Un nom de domaine et un certificat TLS, pour le site et pour Reverb (par exemple `ws.` + le domaine).
- Les comptes des fournisseurs :
  - agrégateur de paiement (pilote simulé `fake` en attendant sa documentation) ;
  - MTarget pour les SMS ;
  - WhatsApp Business et Firebase (push), plus tard.

---

## 3. Première installation

```bash
git clone https://github.com/joeldjebi/sotra-event.git eventtrans && cd eventtrans
cp .env.example .env              # puis renseigner les variables du § 4
docker compose -f docker-compose.prod.yml build
docker compose -f docker-compose.prod.yml run --rm web php artisan key:generate --show
#   → copier la clé affichée dans APP_KEY
docker compose -f docker-compose.prod.yml run --rm web php artisan passes:generate-signing-key
#   → copier PASS_SIGNING_SECRET_KEY dans .env et la conserver hors du serveur (NF-01)
docker compose -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml exec web php artisan db:seed --class=RolesAndPermissionsSeeder --force
docker compose -f docker-compose.prod.yml exec web php artisan users:create-admin admin@<domaine> "Nom Prénom"
```

- Au démarrage, `web` applique les migrations (`RUN_MIGRATIONS=1`) et reconstruit les caches de configuration, de routes et de vues.
- Le premier super administrateur, créé par `users:create-admin` (mot de passe saisi au clavier), active sa double authentification à sa première connexion. Il crée ensuite les autres comptes dans « Utilisateurs & rôles ».

Vérifications :
```bash
curl -s https://<domaine>/up                 # 200 : l'application démarre
curl -s https://<domaine>/api/v1/health      # base, cache, stockage, file, planificateur
```

---

## 4. Variables d'environnement

Elles sont lues au démarrage et ne sont jamais incluses dans l'image. Ne jamais versionner le fichier `.env`.

| Variable | Production | Commentaire |
|---|---|---|
| `APP_ENV` / `APP_DEBUG` | `production` / `false` | Indispensable : active HTTPS forcé, CSP, caches |
| `APP_URL` | `https://…` | Liens signés des Pass, CGV |
| `APP_KEY` | générée | Chiffre les sessions et les coordonnées de reversement |
| `TRUSTED_PROXIES` | `*` ou IP du proxy | Variable d'environnement réelle (pas seulement `.env`) : nécessaire pour reconnaître le HTTPS |
| `SESSION_SECURE_COOKIE` | `true` | Cookies uniquement en HTTPS |
| `TWO_FACTOR_ENFORCED` | `true` | 2FA obligatoire sur les espaces web (NF-03) |
| `DB_*`, `REDIS_*` | — | Base et Redis |
| `QUEUE_CONNECTION` | `redis` | Files traitées par Horizon |
| `REVERB_*` | — | Temps réel ; `REVERB_HOST` est autorisé dans la CSP |
| `SMS_DRIVER` | `mtarget` | Puis `MTARGET_URL`, `MTARGET_USERNAME`, `MTARGET_PASSWORD`, `MTARGET_SENDER`, `MTARGET_TIMEOUT`, `MTARGET_VERIFY_SSL` |
| `PAYMENT_DRIVER` | `fake` | En attendant l'agrégateur ; `PAYMENT_WEBHOOK_SECRET` |
| `SMS_INBOUND_SECRET` | aléatoire | SMS entrants « PANNE » (CV-19) |
| `OPS_ALERT_EMAIL`, `OPS_SLACK_WEBHOOK` | — | Alertes Horizon (files bloquées, échecs) |
| `PASS_SIGNING_SECRET_KEY` | générée | Clé Ed25519 des QR (`passes:generate-signing-key`) ; à défaut, dérivée de `APP_KEY` |
| `API_DOCS_PUBLIC` | `false` | `true` en recette pour ouvrir `/docs/api` et `/docs/swagger` à l'équipe mobile sans compte ; sinon réservées au super administrateur |
| `SECURITY_CSP` | (auto) | Forcer ou désactiver la CSP ; active par défaut en production |

---

## 5. Tâches planifiées (service `scheduler`)

| Tâche | Fréquence | Rôle |
|---|---|---|
| `orders:expire` | chaque minute | Libère les places des commandes non payées (15 min) |
| `payments:reconcile` | 5 min | Interroge l'agrégateur pour les paiements en attente (PA-03) |
| `passes:remind` | 15 min | Rappels J-1 et H-2 (SP-21) |
| `missions:check-delays` | 5 min | Réclamation collective si un départ a plus de 20 min de retard |
| `model:prune` | 2 h 30 | Purge : positions GPS (90 j après l'événement), codes OTP (30 j), SMS (180 j) (NF-12) |
| `payouts:generate` | 3 h | Clôt les événements terminés, calcule les relevés de reversement (RG-14) |
| battement | chaque minute | Contrôlé par `/api/v1/health` (`checks.scheduler`) |

---

## 6. Supervision (NF-08)

- **Sonde** : `GET /api/v1/health`, à interroger chaque minute par l'outil de surveillance.
  - Elle répond `503` si la base, le cache ou le stockage est en panne.
  - Elle signale aussi un retard de file (plus de 1 000 tâches) et un planificateur arrêté.
- **Horizon** : `/horizon`, réservé au super administrateur. Il alerte par e-mail ou Slack (`OPS_*`) quand une file attend trop longtemps.
- **Journaux** : sortie standard des conteneurs (`docker compose logs -f web horizon`).
  - Les numéros de téléphone y sont masqués.
  - Les identifiants des fournisseurs n'y apparaissent jamais.
- **Jours d'événement** :
  - surveillance renforcée ;
  - vérifier `/api/v1/health` et Horizon avant l'ouverture des portes ;
  - garder un accès à « Exploitation en direct ».

---

## 7. Sauvegardes et restauration (NF-08)

- **Sauvegarde quotidienne**, conservée 30 jours :
  ```bash
  0 1 * * * cd /srv/eventtrans && docker compose -f docker-compose.prod.yml run --rm -e BACKUP_DIR=/var/www/html/storage/backups web scripts/backup-database.sh
  ```
  - Le format est `pg_dump` personnalisé, avec une empreinte SHA-256 pour chaque fichier.
  - Le dossier `storage/backups` doit être copié hors de l'hôte : stockage objet ou second site.
- **Restauration testée**, au moins une fois par mois et avant chaque pilote :
  ```bash
  docker compose -f docker-compose.prod.yml run --rm web scripts/restore-test.sh
  ```
  - Le script restaure la dernière sauvegarde dans une base temporaire et compte les lignes des tables clés (événements, Pass, commandes, paiements, journal d'audit), puis supprime la base.
- **Restauration réelle** :
  1. arrêter `web`, `horizon` et `scheduler` ;
  2. lancer `pg_restore --clean --no-owner -d <base> <fichier>` ;
  3. redémarrer les services.
- **Fichiers téléversés** : sauvegarder aussi le volume `app-storage` (photos de panne, justificatifs de reversement).

---

## 8. Mise à jour

```bash
git pull
docker compose -f docker-compose.prod.yml up -d --build   # migrations appliquées au démarrage de web
docker compose -f docker-compose.prod.yml exec horizon php artisan horizon:terminate
```

- Avant une mise à jour importante, faire une sauvegarde manuelle (`scripts/backup-database.sh`).
- Éviter les mises à jour le jour d'un événement.

---

## 9. Sécurité : liste de contrôle avant mise en production

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, HTTPS actif, `SESSION_SECURE_COOKIE=true`.
- [ ] `TRUSTED_PROXIES` renseigné ; l'en-tête `Strict-Transport-Security` est présent.
- [ ] 2FA obligatoire (`TWO_FACTOR_ENFORCED=true`) ; comptes de démonstration supprimés.
- [ ] Secrets uniques et forts : `PAYMENT_WEBHOOK_SECRET`, `SMS_INBOUND_SECRET`, mots de passe de base et Redis.
- [ ] Ports `pgsql` et `redis` non exposés sur Internet.
- [ ] Clés Ed25519 des QR générées et sauvegardées hors du serveur (leur perte invalide tous les QR).
- [ ] Sauvegarde et restauration testées ; sonde `/api/v1/health` branchée sur la surveillance.
- [ ] Traitement déclaré à l'ARTCI ; CGV, politique de confidentialité et mentions légales validées par le juriste (NF-12, NF-14).

En-têtes envoyés par l'application :
- `X-Content-Type-Options`, `X-Frame-Options: DENY`, `Referrer-Policy` et `Permissions-Policy` ;
- `Strict-Transport-Security` en HTTPS ;
- `Content-Security-Policy` en production.

Limites de requêtes :
- API : 300 par minute par compte, 600 par minute par adresse IP ;
- limites plus strictes pour les codes OTP, la connexion, les commandes et les paiements.

---

## 10. Test de charge (NF-05, NF-06)

À relancer sur l'environnement de recette, jamais en production :

```bash
php artisan loadtest:orders --url=https://recette.<domaine> --orders=300 --capacity=250 --concurrency=25
```

- La commande crée un événement de test, envoie les commandes en parallèle par l'API, puis :
  - vérifie qu'aucune place n'est vendue deux fois ;
  - affiche le débit et les temps de réponse (p50, p95) ;
  - supprime les données créées.
- Mesure sur un poste de développement (13/11/2026, serveur PHP de développement à 16 processus) :
  - 1 289 commandes par minute ;
  - 250 places vendues sur 250, et 50 refus propres (409) ;
  - p95 de 375 ms pour les commandes et de 284 ms pour les lectures ;
  - aucune erreur serveur.
