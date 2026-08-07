# Console d'administration native dans `mobile/provider`

**Date :** 2026-08-03
**Statut :** validé (choix utilisateur : 100 % natif RN, moteur à descripteurs, 5 sous-projets enchaînés en autonomie)

## Le problème

La plateforme expose **91 pages d'administration** sur le web (97 composants Livewire sous
`app/Livewire/Admin`). Sur mobile, un administrateur n'a rien : `mobile/provider` ne connaît
pas la notion d'admin, et l'application cliente ne sert l'admin qu'en vue embarquée.

Trois faits établis par la reconnaissance :

1. **Le serveur laisse déjà entrer l'admin dans l'APK prestataire.** `AppAudience::allows()`
   contient une règle explicite : un administrateur est accepté partout, précisément pour ne pas
   l'enfermer dehors des deux applications. Ce qui manque est entièrement côté mobile.
2. **L'application prestataire renvoie pourtant l'admin dans le mur.** `RootNavigator` fait
   passer tout compte authentifié par le gate d'onboarding prestataire (`useOnboardingProgress`),
   puis par un `TabNavigator` dont les quatre écrans appellent des routes gardées `role:employe`.
   Un admin qui se connecte obtient un jeton valide et une application qui échoue partout.
3. **Il manque l'API pour la grande majorité des domaines.** `routes/api/admin.php` couvre
   ~20 familles de domaines sur les ~91 pages admin. Le chantier est donc autant backend que mobile.

## Le trou d'autorisation à fermer d'abord

`routes/api/admin.php` place tout son contenu derrière `auth:sanctum` puis `api_scope:...`.
**Aucun `role:admin` nulle part.** Or :

- `ApiAuthController::login()` émet le jeton mobile via `$user->createToken($deviceName)` — sans
  liste d'abilities, donc Sanctum inscrit `['*']` ;
- `EnforceTokenScope` (ligne 77) laisse passer tout jeton portant `'*'` ;
- les contrôleurs ne compensent pas : `AccountingV2Controller`, `FleetV2Controller`,
  `WebhooksV2Controller`, `ApiTokensV2Controller`, `SubscriptionsV2Controller`, `AuditController`,
  `RiskController`, `MarketingCampaignController`, `DisputeAdminController`… ne contiennent
  **aucune** vérification d'administrateur.

Conséquence actuelle : **un client connecté depuis l'application mobile peut poster des écritures
comptables, révoquer des jetons d'API, forcer l'annulation d'abonnements ou modérer le chat.**
Seuls `MatchingSimulationController`, `KybV2Controller` et `CancellationV2Controller` se gardent
eux-mêmes.

Ce correctif est un prérequis, pas une option : la console mobile consomme précisément ces routes.

## Architecture

### Vue d'ensemble

```
mobile/provider
├─ RootNavigator ─┬─ compte prestataire  → TabNavigator (existant, inchangé)
│                 ├─ compte admin        → AdminNavigator (nouveau)
│                 └─ double casquette    → SpaceSwitcher (nouveau)
└─ AdminNavigator ─┬─ Accueil (KPI)
                   ├─ Opérations   → écrans sur-mesure (4 domaines profonds)
                   ├─ Annuaire     → les 91 modules, groupés
                   ├─ Recherche    → recherche transverse
                   └─ Moi          → profil, espace, déconnexion
```

Aucune WebView. Le rendu est intégralement React Native.

### 1. Coquille admin native

- `AdminNavigator` monté quand `user.is_admin` (champ déjà servi par `/api/auth/login`,
  `/api/profile` et `AuthMeController`).
- **Le gate d'onboarding prestataire est contourné pour l'admin.** Il n'a pas de dossier
  prestataire à compléter ; l'y soumettre l'enfermerait dehors.
- `usePresenceHeartbeat` **n'est pas** monté dans l'espace admin : le battement de présence est un
  signal de terrain prestataire, l'émettre pour un admin fausserait `presence_v2`.
- Un compte portant les deux casquettes (admin **et** prestataire) choisit son espace et peut en
  changer sans se déconnecter.
- **Câblage de résolution à corriger** : `mobile/provider/babel.config.js` ne déclare ni
  `@/parity`, ni `@/webview`, ni `@/finance`, ni `@brio/shared` — alors que `tsconfig.json`
  les déclare. Le typage passe, l'exécution échoue. Les alias manquants dont l'espace admin a
  besoin sont ajoutés au résolveur Babel.

### 2. Moteur de console à descripteurs

Le cœur de la solution. Les ~76 domaines dont l'écran se résume à « liste filtrable → détail →
actions → formulaire » ne sont pas écrits un par un : ils sont **décrits** côté serveur et
**rendus** nativement côté mobile.

**Côté serveur** — un `AdminResource` par domaine, exposant un contrat uniforme :

| Champ | Rôle |
|---|---|
| `key`, `title`, `group`, `icon` | identité et rangement dans l'annuaire |
| `columns` | colonnes de liste : clé, libellé, type (`text`, `money`, `date`, `badge`, `bool`) |
| `filters` | filtres exposés : clé, libellé, type (`select`, `search`, `date_range`, `bool`), options |
| `sorts` | tris autorisés |
| `kpis` | compteurs affichés en tête de liste |
| `actions` | actions de ligne et de masse : clé, libellé, méthode, destructif oui/non, confirmation |
| `form` | champs de création/édition + règles de validation, dérivées des `FormRequest` existantes |
| `permissions` | ce que le compte courant a le droit de faire ici |

**Endpoints génériques**, sous `/api/admin/console` :

```
GET    /resources                      annuaire complet, filtré par permissions
GET    /{resource}                     descripteur + page de résultats (filtres, tri, pagination)
GET    /{resource}/{id}                détail
POST   /{resource}                     création
PATCH  /{resource}/{id}                mise à jour
DELETE /{resource}/{id}                suppression
POST   /{resource}/{id}/actions/{key}  action métier nommée
```

**Règle d'or : le moteur ne réimplémente aucune logique métier.** Chaque descripteur délègue aux
services existants (`CancellationV2`, `PricingV2`, `KybV2`, `RiskEngine`, `AccountingV2`…). Un
descripteur qui aurait besoin d'une règle nouvelle est le signe qu'il faut un écran sur-mesure,
pas une règle dupliquée dans le moteur.

**Côté mobile** — trois écrans génériques en React Native pur :

- `ResourceListScreen` : `FlatList` virtualisée, recherche, filtres en bottom-sheet, KPIs en tête,
  pagination à défilement infini, état vide, état d'erreur, pull-to-refresh.
- `ResourceDetailScreen` : sections de champs typés, actions contextuelles.
- `ResourceFormScreen` : champs natifs générés depuis `form`, validation locale puis serveur,
  erreurs remontées champ par champ.

Ajouter un domaine = écrire un descripteur (~50 lignes) et l'enregistrer. Le rendu, les filtres,
la pagination, les erreurs et les tests de contrat sont mutualisés.

### 3. Écrans sur-mesure

Quatre domaines profonds, choisis explicitement, reçoivent une UX dédiée plutôt que le rendu
générique :

1. **Utilisateurs et rôles** — recherche, création, édition, changement de rôle, suspension,
   réinitialisation de mot de passe.
2. **Prix, catalogue et métiers** — tarification v2, catalogue de commande (secteur → métier →
   questions), prix par métier et par zone, bundles, promotions.
3. **Missions et réservations** — recherche, détail, réassignation, dispatch, annulation avec
   surcharge.
4. **Files de validation** — litiges, KYC, KYB, approbations d'entreprises : conçues pour décider
   en quelques gestes.

S'y ajoutent les centres dont l'interface est irréductible à une liste : simulateur de matching,
constructeur de questionnaire, suivi de trajet cartographique, graphiques analytics, planning et
calendrier.

### 4. Garantie d'exhaustivité

Un **test d'inventaire** parcourt les routes `admin/*` du routeur et échoue tant qu'une page n'est
couverte ni par un écran sur-mesure déclaré, ni par un descripteur enregistré. Une page admin
ajoutée au web sans équivalent mobile fait rougir la suite.

C'est ce test — et non un jugement — qui détermine quand le chantier est terminé.

Deux registres jumeaux le nourrissent :

- `config/admin_console.php` : la correspondance route admin → couverture (descripteur ou écran).
- Un test mobile symétrique vérifiant que chaque clé de couverture est réellement atteignable
  depuis `AdminNavigator` — la mémoire projet documente déjà des écrans mobiles orphelins que ni
  `tsc` ni `jest` ne signalent.

## Portail de vérification

Exigé vert à la fin de chaque lot, sans exception :

| Contrôle | Commande |
|---|---|
| Style PHP | `vendor/bin/pint --test` |
| Analyse statique | `vendor/bin/phpstan analyse` (run **complet**, jamais limité à un chemin) |
| Tests PHP | `php artisan test` |
| Typage mobile | `npm run typecheck` dans `mobile/provider` |
| Tests mobile | `npm test` dans `mobile/provider` |
| Exhaustivité | test d'inventaire admin |

La mémoire projet retient deux pièges qui s'appliquent directement ici : la suite tourne sur SQLite
alors que l'application tourne sur MySQL strict (classe de défauts invisible), et les revues par
tâche ne lancent pas PHPStan en entier. Une passe MySQL et un PHPStan complet closent donc chaque
sous-projet.

## Découpage

| Sous-projet | Contenu | Fin |
|---|---|---|
| **A** | `role:admin` sur `/api/admin/*` + tests de non-régression ; alias Babel ; `AdminNavigator`, sélecteur d'espace, accueil KPI, annuaire des 91 modules | portail vert, admin qui entre et navigue |
| **B** | Moteur de console : contrat, endpoints génériques, trois écrans natifs, 10 premiers descripteurs | portail vert, 10 domaines pilotables |
| **C** | Les 4 domaines profonds sur-mesure | portail vert |
| **D** | Le reste des domaines par lots, en boucle | test d'inventaire au vert |
| **E** | Centres irréductibles + polish (offline, recherche transverse, notifications actionnables) | portail vert |

## Ce qui n'est pas fait

- **Aucune WebView de repli.** Le choix est 100 % natif ; un repli en vue embarquée masquerait les
  domaines non encore couverts et ferait mentir le test d'inventaire.
- **L'application cliente n'est pas touchée.** Son `ModuleHubScreen` et son parity-map restent en
  l'état.
- **Le registre de parité (`config/parity.php`) n'est pas modifié dans les sous-projets A à D.**
  Basculer les entrées admin de `webview` à `native` supposerait la couverture complète ; c'est un
  geste de fin de chantier, en E.
- **La double authentification admin** n'est pas retouchée : `Enforce2FA` garde le web, la console
  mobile passe par l'API et n'en dépend pas. Si `auth.enforce_2fa_for_admins` est actif, la parité
  de garde entre web et API est un point ouvert signalé en fin de sous-projet A.
