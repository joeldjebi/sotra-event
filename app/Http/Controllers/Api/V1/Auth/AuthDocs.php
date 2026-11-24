<?php

namespace App\Http\Controllers\Api\V1\Auth;

/**
 * Sections de la documentation de l'API consacrées à la connexion et au compte (attributs #[Group] de Scramble).
 * Même poids : elles s'affichent dans l'ordre alphabétique, avant « Événements ».
 */
final class AuthDocs
{
    public const OTP = 'Connexion par code SMS (spectateur)';

    public const OTP_DESCRIPTION = <<<'MD'
**Parcours principal de l'app spectateur : inscription et connexion en un seul parcours, sans mot de passe.**

1. **Écran « Numéro »** : `POST /auth/otp` avec `phone` et `audience: customer`. Un code à 6 chiffres part par SMS (valable 5 min, 5 essais, renvoi possible après `resend_in` secondes).
2. **Écran « Code »** : `POST /auth/verify` avec `phone`, `audience: customer`, `code` et `device_name`. La réponse contient le jeton et indique l'écran suivant :

| `next_step` | Cas | Écran à ouvrir |
|---|---|---|
| `complete_profile` | Nouveau compte (`is_new_account: true`), ou compte existant sans nom (`profile_completed: false`) | « Mes informations » |
| `home` | Compte existant au profil complet | Accueil |

3. **Écran « Mes informations »** (si `complete_profile`) : `PUT /me/profile` avec `first_name`, `last_name` et, facultatif, `email`, en envoyant le jeton. La réponse renvoie `profile_completed: true` : ouvrir l'accueil.
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
