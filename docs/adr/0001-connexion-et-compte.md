# ADR-0001 — Connexion, mot de passe oublié, inscription, profil et commandes de compte

- **Statut** : Accepté — 2026-11-13, validé par jul6art (« ok go ») avec les cinq recommandations, puis deux écarts relevés en ouvrant le code (voir « Décisions du 2026-11-13 »)
- **Décideurs** : jul6art
- **Origine** : la remontée dans les bundles du code que le squelette recopiait dans chaque application (cinquième de six lots)
- **Bundles** : admin-bundle (les écrans), auth-bundle (la logique sans écran et les commandes)

## Contexte

admin-bundle porte déjà :

- les gabarits de ces écrans (`@Admin/security/login`, `register`, `reset_password_request`, `check_email`,
  `reset_password`) ;
- le second facteur (`TwoFactorController`) ;
- les routes que la carte de connexion affiche (`admin.routes.login`, `.register`, `.profile`) ;
- `login.remember_me_default`.

Les **contrôleurs**, eux, sont recopiés du squelette dans chaque application, avec leurs formulaires et deux
commandes. auth-bundle porte, sous `Api/Service/`, l'écriture du mot de passe (`PasswordWriter`) et le courriel de
réinitialisation (`PasswordResetMailer`) de l'API. Le web en a sa propre copie dans chaque contrôleur.

La déconnexion en `POST` (le reste de T14) est déjà faite dans les huit applications : ce lot n'a plus à s'en
charger.

Mesure du 2026-11-13 (`superp/docs/analyse/audit_fable_5_2_t20_copies.md`, même méthode) :

| Classe | Copies | Variantes | Référence (copies identiques) |
|---|---|---|---|
| `SecurityController` | 13 | 9 | cereezer (back-office), devinthehood, tradebot, wtg — 46 lignes |
| `ResetPasswordController` | 9 | 6 | devinthehood, tradebot, wtg — 150 lignes |
| `RegistrationController` | 10 | 6 | devinthehood, tradebot, wtg, squelette backoffice — 68 lignes |
| `RegistrationFormType` | 9 | 6 | devinthehood, wtg, squelette backoffice — 58 lignes |
| `AccountProfileController` | 7 | 3 | cereezer, devingame, devinthehood, wtg, squelette backoffice — 87 lignes |
| `ProfileFormType` | 7 | 3 | cegeta, devingame, devinthehood, tradebot, wtg — 73 lignes |
| `DevLoginLinkCommand` | 8 | 4 | cegeta, devingame, devinthehood, wtg, squelette backoffice — 80 lignes |
| `Dev\LoginLinkController` | 8 | 2 | cegeta, cereezer, devingame, devinthehood, tradebot, wtg, squelette backoffice — 32 lignes |
| `CreateUserCommand` | 12 | 4 | cereezer, devinthehood, tradebot, wtg, squelette backoffice — 89 lignes |

### Les écarts, un par un

| Classe | Où | Écart | Classement |
|---|---|---|---|
| `SecurityController` | devingame | les routes traduites (`/fr/connexion`, `/en/login`) | **l'affaire de l'application** : le bundle ne déclare aucune route |
| | squelette backoffice, cegeta | un commentaire, deux lignes | **dérive**, abandonnée |
| | cereezer (portail), superp, squelette web | déjà connecté : redirection vers l'accueil de l'espace | **option** : `redirect_authenticated_to` |
| | cereezer (portail) | son propre gabarit, sous `/portal` | **option** : le gabarit par route |
| | devinlive | `app_login`, son gabarit, une classe non finale | **dérive** : il suit la référence |
| | superp (`Modules/User`, 309 lignes) | sa connexion, à lui | **hors du lot** |
| `ResetPasswordController` | devingame | les routes traduites | **l'affaire de l'application** |
| | squelette backoffice | une version d'août (`null ===` au lieu d'`instanceof`) | **dérive** |
| | cereezer | deux espaces, administration et portail : gabarits et noms de route par espace | **point d'extension** : un espace par route (`_auth_space`), ses gabarits et ses routes en configuration |
| | cegeta | **un plancher de 250 ms** sur la réponse de demande, contre l'énumération des comptes par le temps de réponse | **adopté partout** : c'est une garde de sécurité, pas un goût du produit |
| | cegeta | le courriel dans la langue du compte | **adopté** : la langue du compte quand il en a une |
| `RegistrationController` | la famille | compte créé **inactif**, en attente ; message « pending » | **référence** |
| | devingame | captcha, limiteur `sign_up`, conditions, confirmation de l'adresse | **points d'extension** : le captcha et les conditions dans son formulaire ; le limiteur en option (`limiter`) ; la confirmation en écouteur |
| | cereezer | la langue du compte, l'accusé de réception, l'alerte à la plateforme | **écouteur** de l'événement d'inscription |
| | cegeta | un espace personnel, la vérification de l'adresse, les consentements, compte **actif** d'emblée | **option** `active: true`, et un écouteur |
| | devinlive | son `RegistrationService`, sa route `app_registration_register` | **à confirmer** (voir plus bas) |
| `RegistrationFormType` | cereezer, cegeta | `empty_data => ''` sur prénom, nom et adresse | **adopté** : sans lui, un champ vidé écrit `null` dans une chaîne. Le même défaut est **probable** dans la famille ; il est à reproduire en test au début du lot |
| | tradebot | un captcha | son formulaire, sur la clé `form_type` |
| | devingame | un pseudo à la place des nom et prénom | son formulaire |
| `AccountProfileController` | tradebot | `PROFILE_UPDATE` au lieu de `IS_AUTHENTICATED_FULLY` | **option** `access` |
| | cegeta | deux espaces (`/admin`, `/app`), sa mise en page, le renvoi de vérification | **point d'extension**, le même espace par route que le mot de passe oublié |
| `ProfileFormType` | cereezer | `empty_data` | **adopté** |
| | squelette backoffice | une version d'août | **dérive** |
| `DevLoginLinkCommand` | tradebot | le compte cherché par adresse **normalisée** (`findOneByEmail`) | **adopté** : `Strings::lowerEmail`, comme l'entité |
| | cereezer | deux lignes | **dérive** |
| | superp | commentaires en anglais, autre espace de noms | **dérive** : superp adopte la commande du bundle |
| `Dev\LoginLinkController` | superp | commentaires en anglais | **dérive** : superp l'adopte aussi |
| `CreateUserCommand` | devingame | l'option `--username` | **option générique** `--set <propriété>=<valeur>` |
| | cegeta | l'espace personnel rattaché | **écouteur** de l'événement de création |
| | squelette (api, api-platform, web, admin), devinthecode (démo) | sur l'entité `User` d'auth-bundle (`UserManagerInterface`) | la même commande, par le gestionnaire quand l'application utilise l'entité du bundle |

## Décision

### 1. admin-bundle livre les écrans (1.42.0)

Sous `Controller/Security/`, **sans aucune route déclarée**, comme `RolePermissionController` (lot 2). Les noms de
route restent ceux des applications : `admin_security_login`, `admin_security_logout`,
`admin_reset_password_request`, `_check_email`, `_reset`, `admin_security_register` et `admin_account_profile_edit`.

- **`LoginController`** : `login()` et `logout()`. `logout()` est interceptée par le pare-feu, en `POST` seulement.
- **`ResetPasswordController`** :
  - s'appuie sur `symfonycasts/reset-password-bundle` (en `suggest`) ;
  - prend le plancher de temps de cegeta ;
  - écrit le mot de passe et envoie le courriel par les services d'auth-bundle (§ 2), plus par sa copie.
- **`RegistrationController`** et **`RegistrationFormType`** (avec `empty_data`) :
  - l'événement `RegistrationSubmittedEvent` est émis avant l'écriture, `UserRegisteredEvent` après ;
  - le limiteur est optionnel ;
  - le formulaire est remplaçable (`form_type`).
- **`AccountProfileController`** et **`ProfileFormType`** (avec `empty_data`) : la photo, son téléversement et son
  message d'échec, comme aujourd'hui.

Un **espace** est un jeu de gabarits et de noms de route, choisi par l'attribut `_auth_space` de la route. C'est le
point d'extension de cereezer (portail) et de cegeta (`/app`). Sans attribut, l'espace est `admin`.

```yaml
admin:
    account:
        enabled: true
        user_class: App\Entity\User
        registration: { open: true, active: false, form_type: ~, limiter: ~, sign_in: ~, flash: security.register.flash.pending }
        reset_password: { enabled: true, response_floor_ms: 250, email: { from: '%env(MAILER_FROM)%' } }
        profile: { access: IS_AUTHENTICATED_FULLY, show_attribute: ~ }
        spaces:
            portal: { templates: { login: 'portal/security/login.html.twig' }, routes: { login: portal_security_login } }
```

Désactivé par défaut : une application garde ses contrôleurs tant qu'elle n'active pas la clé.

### 2. auth-bundle livre la logique et les commandes (2.8.0)

- **`PasswordWriter` et `PasswordResetMailer`** sortent d'`Api/Service/` vers `Account/`. L'API et le web
  appellent les mêmes services, et les anciens noms restent des alias le temps d'une majeure.
- **`DevLoginLinkCommand`** (`app:dev:login-link`) et **`Dev\LoginLinkController`**, `#[When('dev')]`. Le compte est
  cherché par adresse normalisée.
- **`CreateUserCommand`** (`app:user:create`) :
  - les options `--admin`, `--first-name`, `--last-name`, et `--set` pour les autres propriétés ;
  - l'événement `UserCreatedEvent` ;
  - l'entité de l'application (`account.user_class`), ou celle du bundle par son gestionnaire.

Les noms de commande ne changent pas : les procédures et les hooks de déploiement non plus.

### 3. Ce qui reste dans chaque application

| Application | Après le lot |
|---|---|
| devinthehood, wtg, tradebot, squelette (backoffice) | `admin.account` ; les routes ; tradebot garde son captcha (formulaire) et `profile.access` |
| devingame | ses routes traduites ; son formulaire (pseudo, captcha, conditions) ; `registration.limiter: limiter.sign_up` ; sa confirmation d'adresse en écouteur |
| cereezer | deux espaces, `admin` et `portal` ; ses courriels d'inscription en écouteur |
| cegeta | deux espaces, `admin` et `app` ; `registration.active: true` ; l'espace personnel, la vérification et les consentements en écouteurs |
| devinlive | **à confirmer** (voir plus bas) |
| superp | `app:dev:login-link` et son contrôleur seulement ; sa connexion reste la sienne |
| squelette (web, admin, api, api-platform) | **à confirmer** (voir plus bas) |
| devinthecode | non concerné (pas de back-office Twig) |

Partout : les neuf classes recopiées disparaissent, avec leurs définitions de service et leurs tests devenus
redondants. Les gabarits de l'application restent ceux qu'elle a remplacés.

## Comportements qui changent (assumés)

- **Le plancher de 250 ms** sur la demande de réinitialisation, partout : une réponse ne dit plus, par sa durée,
  si l'adresse existe.
- **`empty_data`** : un champ vidé à l'inscription ou dans le profil donne un formulaire en erreur, plus un 500.
- **Le lien de connexion de développement** retrouve un compte saisi avec des majuscules.
- **Le courriel de réinitialisation part dans la langue du compte**, là où il partait dans celle de la page.
- **Une réinitialisation sur le web ferme les sessions mobiles du compte** (cereezer, cegeta).
- **Une soumission invalide du profil répond 422**, plus 200.
- Partout ailleurs : aucun changement. Ce sont les mêmes routes, les mêmes gabarits, les mêmes jetons et les mêmes
  messages.

## Décisions du 2026-11-13

Les cinq points, tranchés comme recommandé :

1. **Les écrans dans admin-bundle.** Les modes `web` et `admin` du squelette, qui n'avaient qu'auth-bundle,
   l'installent : leurs gabarits restent les leurs, par un espace.
2. **Le plancher de 250 ms pour tout le monde.**
3. **L'inscription par événements** ; le contrôleur du bundle reste final.
4. **devinlive** se range sur la référence en **gardant ses noms de route** (`app_login`,
   `app_registration_register`) : la configuration des routes les nomme.
5. **Les espaces de noms** `Controller/Security/` (admin-bundle) et `Account/` (auth-bundle).

Deux écarts relevés en ouvrant le code, validés le même jour :

- **`NewPasswordFormType` et `ResetPasswordRequestFormType`** (9 copies chacun), absents du relevé, partent dans
  admin-bundle avec le contrôleur du mot de passe oublié, remplaçables (`request_form_type`, `form_type`).
- **Les modes `web` et `admin` du squelette** reposent sur l'entité `User` d'auth-bundle (ni « actif », ni nom, ni
  photo), connectent le compte juste après l'inscription et nomment leurs routes `app_*`. Le bundle prend
  `registration.sign_in` (l'authentificateur qui connecte le nouveau compte) et `registration.active: ~` (une
  entité sans drapeau) ; la fabrique d'auth-bundle ne faisant que `new User()`, le bundle instancie `user_class`.
  Leur `ProfileController` (fiche et changement de mot de passe), hors du relevé, reste dans les modes.

Ce que l'écriture a précisé :

- **Les formulaires demandent ce que l'entité garde** : les noms si elle a `setFirstName()`/`setLastName()`, la
  photo avec `setAvatarPath()`. Une application qui demande plus (captcha, conditions, pseudo) écrit un type dont
  le parent est celui du bundle (`getParent()`), plus une copie.
- **Trois événements, pas deux** : `RegistrationFormEvent` s'ajoute avant la construction du formulaire. Il sert
  l'espace personnel de cegeta, que la validation lit, et la question du captcha de devingame.
- **La fiche où revient le profil est une route de l'espace** (`profile_show`), pas un réglage global : l'espace
  client de cegeta n'a pas de fiche de compte. Avant le lot, sa copie y renvoyait un client qui avait `user:read`
  dans son locataire : un 403 après un enregistrement réussi, relevé dans Chrome le 2026-11-13.
- **Le limiteur** refuse par une erreur sur le formulaire, rendu en 429, plutôt que par une page d'erreur.
- **Une soumission invalide répond 422** sur les quatre écrans, profil compris.
- **auth-bundle** : `PasswordResetMailer::deliver(PasswordResetEmail)` envoie le courriel décrit en entier, et
  `send()` construit celui de l'API à partir du domaine. L'événement de `app:user:create` s'appelle
  `AccountCreationEvent` : il part avant l'écriture, et un nom au passé aurait menti.
- **Le mot de passe réinitialisé sur le web met fin aux sessions de l'API** du compte quand gesdinet est là
  (cereezer, cegeta) : c'est le même `PasswordWriter`.

## Tests exigés

- **admin-bundle**, la connexion : déjà connecté et `redirect_authenticated_to` ; l'erreur et le dernier
  identifiant rendus.
- **admin-bundle**, le mot de passe oublié :
  - une adresse inconnue et une adresse connue, de même durée à 50 ms près, au moins 250 ms ;
  - un jeton invalide ;
  - le mot de passe changé ;
  - le courriel dans la langue du compte.
- **admin-bundle**, l'inscription :
  - fermée, puis 404 ;
  - un compte inactif, et actif avec `active: true` ;
  - les deux événements ;
  - le limiteur (429) ;
  - `empty_data`, qui donne 422 et plus 500.
- **admin-bundle**, le reste :
  - le profil : `access`, la photo, l'échec du téléversement ;
  - les espaces : gabarits et redirections par `_auth_space` ;
  - la configuration (désactivée par défaut) ;
  - dépendances hautes et basses ; PHPStan, Rector, style.
- **auth-bundle** :
  - les deux commandes, dont l'adresse normalisée, `--admin`, `--set` et l'événement ;
  - les services sortis d'`Api/` et leurs anciens noms ;
  - le contrôleur de développement, absent hors `dev`.
- **Chaque application** : ses tests existants joués avant et après (`ResetPasswordFlowTest`, les tests de
  connexion, d'inscription et de profil) ; porte complète.
- **Dans Chrome, avant et après** :
  - se connecter, puis se déconnecter ;
  - le mot de passe oublié jusqu'au courriel (Mailpit), puis le nouveau mot de passe ;
  - s'inscrire (devingame : avec son captcha) ;
  - modifier son profil et sa photo ;
  - chez cereezer, le portail ; chez cegeta, `/app`.
- **Squelette** : les cinq modes générés, leur porte jouée, et le parcours Chrome sur un backoffice généré.

## Conséquences

- admin-bundle 1.42.0, dont le badge n'écrit que la majeure. auth-bundle 2.8.0, dont le badge passe à v2.8.0. Les
  applications montent leur plancher. **Livré** : admin-bundle 1.42.0, 1.42.1 (`registration.active: ~` lu comme
  `false`), 1.43.0 (la page de vérification reçoit `resetToken`) et 1.43.1 (le compte connecté dès l'inscription
  reçoit aussi le message de bienvenue) ; auth-bundle 2.8.0.
- Démonstrations d'abord (superp, cereezer, tradebot, wtg, devinthehood), puis devingame, cegeta et devinlive.
- Le lot 6 (import), qui vient après celui-ci dans l'ordre validé, ajoute ensuite `admin.user_import` en 1.44.0.
