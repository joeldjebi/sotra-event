<?php

namespace App\Http\Controllers\Api\V1\Auth;

/**
 * Sections de la documentation de l'API consacrées à la connexion et au compte (attributs #[Group] de Scramble).
 * Register / login par OTP d'abord, puis les autres par ordre alphabétique, avant « Événements ».
 */
final class AuthDocs
{
    public const OTP = 'Register / login par OTP (spectateur)';

    /** Section affichée en premier (les autres sections de connexion ont le poids 1). */
    public const OTP_WEIGHT = 0;

    public const OTP_DESCRIPTION = <<<'MD'
**Parcours principal de l'app spectateur : inscription et connexion par code SMS, sans mot de passe.**

**Spectateur déjà inscrit**
1. `POST /auth/otp` : envoyer le code.
2. `POST /auth/verify` : vérifier le code → `status: logged_in`, `token` → **connecté**, ouvrir l'accueil.

**Nouveau spectateur**
1. `POST /auth/otp` : envoyer le code.
2. `POST /auth/verify` : vérifier le code → `status: registration_required`, `registration_token` (aucun compte créé, pas encore connecté) → ouvrir l'écran « Mes informations ».
3. `POST /auth/register/complete` avec `registration_token`, `first_name`, `last_name`, `email` (facultatif) et `device_name` → compte créé, `status: logged_in`, `token` → **connecté automatiquement**, ouvrir l'accueil.

Après `POST /auth/verify`, l'application n'a qu'un champ à tester : `data.status`. Plus tard, les informations se modifient depuis le profil avec `PUT /me/profile` (section « Mon compte »).
MD;

    public const PASSWORD = 'Connexion par mot de passe';

    public const PASSWORD_DESCRIPTION = <<<'MD'
**Personnel (convoyeur, technicien) et spectateurs qui ont choisi un mot de passe.**

- **Login** : `POST /auth/login` avec `phone`, `audience` (`customer` ou `staff`), `password` et `device_name`.
- **Register avec mot de passe** (spectateur) : `POST /auth/otp` puis `POST /auth/register`.
- **Mot de passe oublié** : `POST /auth/otp` puis `POST /auth/password/reset`.
- **Personnel** : mot de passe initial remis par l'exploitation. Tant que `must_change_password` vaut `true`, ouvrir l'écran de changement (`POST /auth/password`) : les autres appels répondent 403 `PASSWORD_CHANGE_REQUIRED`.
MD;

    public const ACCOUNT = 'Mon compte';

    public const ACCOUNT_DESCRIPTION = 'Utilisateur connecté (jeton `Authorization: Bearer <token>`) : profil, informations personnelles du spectateur, déconnexion, suppression du compte.';
}
