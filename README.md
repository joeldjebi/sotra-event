# EVENT TRANS — « Le Pass en route »

Plateforme ACCES EVENT × compagnies de transport (SOTRA) : vente et contrôle de Pass transport pour les grands événements.
Ce dépôt contient l'**API**, le **site spectateur**, la **console ACCES EVENT** et les **espaces compagnie et promoteur**. Tout est en Laravel (Blade + Livewire).

- Cadrage technique : [docs/SPECIFICATIONS.md](docs/SPECIFICATIONS.md)
- Étapes et règles de développement : [docs/GUIDE_DEVELOPPEMENT.md](docs/GUIDE_DEVELOPPEMENT.md)

## Prérequis
- PHP 8.3 avec les extensions `pdo_pgsql`, `sodium`, `intl`, `gd`, `pcntl`
- Composer 2
- Node.js 20 (`nvm use` lit `.nvmrc`), uniquement pour compiler CSS et JS
- Docker (PostgreSQL 17 + PostGIS 3.5 et Redis 7, voir `docker-compose.yml`)

## Installation
```bash
git clone https://github.com/joeldjebi/sotra-event.git && cd sotra-event
nvm use
composer setup     # dépendances, .env, clé, conteneurs Docker, migrations, build front
```
Les conteneurs exposent PostgreSQL sur le port **5450** et Redis sur le port **6395**, pour ne pas gêner d'autres projets. La base de tests `eventtrans_testing` est créée automatiquement.

## Lancer en local
```bash
composer dev       # serveur web, Horizon (files), Reverb (temps réel), Vite
```

### Comptes de démonstration (local)
`php artisan migrate:fresh --seed` crée les comptes suivants (mot de passe : `password`) :

| Profil | E-mail |
|---|---|
| Super admin ACCES EVENT | admin@eventtrans.test |
| Opérateur ACCES EVENT | operateur@eventtrans.test |
| Direction commerciale SOTRA | commercial@sotra.test |
| Direction qualité SOTRA | qualite@sotra.test |
| Exploitation SOTRA | exploitation@sotra.test |
| Chef de projet transport | chef.projet@promoteur.test |

La double authentification est obligatoire : à la première connexion, scannez le QR code avec une application d'authentification. En local, on peut la désactiver avec `TWO_FACTOR_ENFORCED=false`.

Personnel terrain SOTRA (connexion mobile par OTP) : convoyeurs `0700000001` et `0700000002`, technicien `0700000003`. Les codes OTP simulés s'affichent sur **/dev/sms**.

## Qualité
```bash
composer check     # Pint (style) + Larastan niveau 6 (analyse) + Pest (tests)
```
La même commande tourne dans GitHub Actions à chaque push et à chaque pull request.

## Services externes
Pendant le développement, les fournisseurs externes sont simulés (variables `*_DRIVER` du `.env`) :
`PAYMENT_DRIVER=fake`, `SMS_DRIVER=log`, `WHATSAPP_DRIVER=log`, `PUSH_DRIVER=log`.
