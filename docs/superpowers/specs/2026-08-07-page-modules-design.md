# Page Modules — un point d'entrée exhaustif par tableau de bord

**Date :** 2026-08-07
**État :** validé, prêt pour le plan d'implémentation

## Le problème

La navbar web (`resources/views/navigation-menu.blade.php`, 580 lignes) porte 126 liens répartis
en 22 groupes selon le rôle. Elle est illisible. Et elle est pourtant **incomplète** : 36 pages de
tableau de bord n'y figurent pas.

## Ce que le code dit

Inventaire établi à partir de la table de routes réelle (`php artisan route:list`), pas de la
documentation ni de `config/parity.php`.

| Mesure | Valeur |
|---|---|
| Routes totales | 684 |
| Pages GET sans paramètre | 211 |
| Pages de tableau de bord (`admin/*`, `dashboard/*`) | **162** |
| Dont exports et callbacks (CSV, PDF, XLSX, retours Stripe) | 13 |
| **Modules réels** | **149** |

Répartition : admin 90, client 32, employé 22, entreprise-cliente 12, entreprise-prestataire 6.

### Le registre est éparpillé en quatre endroits

1. `navigation-menu.blade.php` — trois tableaux PHP inline (`$clientGroups`, `$employeGroups`,
   `$adminGroups`), 22 groupes, 126 liens.
2. `layouts/client-company.blade.php` — 11 liens en dur, aucun groupe.
3. `layouts/provider-company.blade.php` — 6 liens en dur, aucun groupe.
4. Des liens ad hoc dans des vues individuelles (panneau d'activité, modale calendrier, en-tête du
   dashboard client…).

### Aucune page n'est orpheline — vérifié

Les quatre candidates (`admin.audit.logs`, `admin.calendar`, `admin.calendar.settings`,
`client.analytics.dashboard`) sont toutes citées depuis au moins une vue. Le défaut n'est donc pas
l'injoignabilité — c'est que 36 pages ne s'atteignent qu'en descendant dans une autre page, sans
qu'aucune surface ne les recense.

C'est la correction d'une hypothèse initiale : on soupçonnait des modules invisibles, le code dit
autre chose. Les deux espaces société, notamment, ont bien leur navigation — simplement définie
ailleurs.

## La décision

### 1. Un registre unique : `config/modules.php`

Les quatre sources fusionnent en un fichier de configuration. Forme d'une entrée :

```php
[
  'key'      => 'client.loyalty',      // identifiant stable
  'label'    => 'Programme fidélité',
  'icon'     => '🎖️',                  // emoji, traduit en Heroicon par la table existante
  'route'    => 'client.loyalty',      // route nommée, cible de la case
  'context'  => 'client',              // client | employe | admin | client-company | provider-company
  'category' => 'engagement',          // fonction — voir taxonomie
  'primary'  => false,                 // true = reste dans la navbar allégée
]
```

Les libellés et icônes existants sont **repris tels quels** : ils ont été choisis, ils sont bons,
et les réécrire ferait perdre de l'information sans rien gagner.

### 2. Taxonomie fonctionnelle

Les groupes actuels mélangent fonction et niveau (« Business avancé », « Pro »). La nouvelle
taxonomie est fonctionnelle et commune aux cinq contextes :

| Catégorie | Contenu |
|---|---|
| `rendez-vous` | Réservations, planning, calendriers, disponibilités, récurrences |
| `missions` | Missions, dispatch, terrain, coordination, trajets |
| `documents` | Contrats, signatures, factures, exports, comptabilité |
| `finance` | Paiements, portefeuille, revenus, abonnements, tarification, devises |
| `comptes` | Profils, membres, utilisateurs, entreprises, sites, locaux |
| `prestataires` | Recherche, favoris, badges, équipes, flotte, inscriptions |
| `communication` | Messagerie, notifications, SMS, push, e-mails, temps réel |
| `qualite` | Litiges, SAV, avis, inspections, incidents, signalements |
| `conformite` | KYC, KYB, RGPD, audit, risque, assurance |
| `croissance` | Fidélité, parrainage, promotions, marketing, NPS |
| `donnees` | Analytics, rapports, matching, IA |
| `plateforme` | Modules, feature flags, traductions, jetons d'API, webhooks, réglages |

Une catégorie vide pour un contexte n'est simplement pas rendue.

### 3. Une page `/modules` par contexte

Un composant Livewire unique, `App\Livewire\Shared\ModulesDirectory`, monté sur cinq routes —
une par contexte, gardée par le middleware de rôle déjà en place pour ce tableau de bord. Il lit
`config/modules.php`, filtre sur le contexte, retire les routes absentes (`Route::has`) et rend
des cases nom + icône groupées par catégorie. Chaque case est un lien vers la route du module.

Pas d'écran d'administration, pas de personnalisation, pas de favoris : ce serait du périmètre en
plus sans demande.

### 4. La navbar allégée

Elle conserve : l'accueil, les liens marqués `primary` (4 à 5 par rôle), une entrée **Modules**,
les notifications et le menu profil. Les tableaux inline disparaissent au profit du registre. Les
deux layouts société consomment le même registre et gagnent eux aussi leur entrée Modules.

### 5. La garantie d'exhaustivité est un test

`tests/Feature/Navigation/CatalogueDesModulesTest.php` parcourt la table de routes réelle et
échoue si une page de tableau de bord n'a pas de case dans `config/modules.php`.

Une liste blanche explicite couvre les 13 non-modules (exports, callbacks Stripe), chacun avec sa
raison. Toute nouvelle page de tableau de bord fera échouer ce test tant qu'elle n'aura pas sa
case ou sa ligne de liste blanche.

C'est ce qui rend le « 100 % » vérifiable plutôt que déclaratif. Ce dépôt a déjà produit des
tests de joignabilité qui asséraient une déclaration au lieu d'un chemin ; celui-ci part de la
table de routes, la seule source qui ne peut pas mentir sur ce qui existe.

## Hors périmètre

- La navigation mobile (`mobile/`) et `config/parity.php` : inchangés.
- Le contenu des pages de modules : on ne touche qu'aux points d'entrée.
- Les exports et callbacks : listés en liste blanche, pas transformés en cases.

## Critères d'acceptation

1. `config/modules.php` couvre les 149 modules réels, chacun avec libellé, icône, route,
   contexte et catégorie.
2. Les cinq pages `/modules` rendent les cases groupées par catégorie ; chaque case mène à sa route.
3. La navbar passe sous 10 entrées par rôle.
4. Les deux layouts société consomment le registre au lieu de leurs liens en dur.
5. Le test de catalogue passe, et échoue si on ajoute une page de tableau de bord sans case.
6. Suite PHP verte, PHPStan vert.
