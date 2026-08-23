# Domaine

Le vocabulaire de la plateforme. Chaque terme a un sens précis, une table, et des règles qui ne
se devinent pas.

## Le catalogue

### Secteur

Une famille de services. Six existent : Bâtiment & rénovation, Nettoyage, Espaces verts, Services
à la personne, Sécurité, Mobilité.

Table `sectors`. Un secteur porte un slug, un nom, une accroche, une icône et un ordre
d'affichage. Son nom est traduisible.

### Métier

Ce qu'un prestataire sait faire : Peinture, Plomberie, Garde d'enfants, Course. Seize existent.

Table `trades`, 45 colonnes. Les plus structurantes :

| Colonne | Ce qu'elle décide |
|---|---|
| `sector_id` | Sans elle, le métier **n'apparaît sur aucun écran de commande** |
| `allows_asap` | Le métier accepte-t-il l'immédiat ? Un ravalement de façade, non |
| `allows_scheduled`, `allows_bundle` | Les autres modes de commande |
| `pricing_unit` | Au forfait, à l'heure, au mètre carré, au kilomètre |
| `requires_face_check` | Le prestataire doit-il prouver son visage avant d'intervenir ? |
| `hourly_billing` | La facturation se fait-elle au temps réellement passé ? |

### Question

Ce qu'on demande au client avant de chiffrer. Tables `question_steps`, `questions`,
`question_options`.

Une question porte son impact sur le prix : un forfait, un montant par unité, un multiplicateur.
Le moteur de prix lit ces impacts — aucun métier n'est calculé en dur.

Règles tenues par des tests :

- Sept questions maximum par étape. Au-delà, un client sur trois abandonne.
- Chaque question offre une porte de sortie — exactement une option par défaut.
- Chaque métier propose une photo, jamais obligatoire.

## La géographie

### Zone de service

Le maillage commercial. Table `service_zones` : un pays, une région, des codes postaux couverts.

### Grille de prix

Table `trade_zone_pricing`. **C'est la source unique de l'ouverture commerciale.**

| Fait | Conséquence |
|---|---|
| Une ligne existe pour (métier, zone) | Le métier est vendu dans cette zone |
| Aucune ligne | Le métier est **fermé** dans cette zone — silencieusement |
| `asap_enabled` à vrai | L'immédiat est ouvert pour ce couple |
| `surge_multiplier` | La majoration appliquée à cette zone |

Une table `trade_zone_settings` a existé en parallèle. Elle a été supprimée : deux sources pour
la même décision, et elles divergeaient.

## La commande

### Panier

Table `order_drafts`, 39 colonnes. Rattaché à un jeton de session, pas à un compte : le prix se
calcule avant l'identité.

Un panier porte une adresse, une zone, un mode, des articles (`order_draft_items`) et les
réponses aux questions.

### Réservation

Table `bookings`, 159 colonnes. C'est le contrat entre le client et la plateforme.

**Quinze paires de colonnes jumelles** y cohabitent, héritage d'une fusion français/anglais :

| Français | Anglais | | Français | Anglais |
|---|---|---|---|---|
| `client_id` | `customer_user_id` | | `type_lieu` | `place_type` |
| `employe_id` | `assigned_provider_user_id` | | `frequence` | `frequency` |
| `date` | `scheduled_date` | | `priorite` | `priority` |
| `heure` | `scheduled_time` | | `commentaire_client` | `customer_comment` |
| `adresse` | `address` | | `telephone_client` | `contact_phone` |
| `ville` | `city` | | `devis_estime` | `estimated_price` |
| `code_postal` | `postal_code` | | `duree_estimee` | `estimated_duration_minutes` |
| `organization_account_id` | `customer_organization_id` | | | |

Le trait `HasLegacyBookingAliases` les tient d'accord. Sa règle n'est pas « recopier » mais
**suivre la fraîcheur** : un trou se comble ; si un seul côté a changé, il fait foi ; si les deux
ont changé, l'appelant a tranché et on ne devine pas à sa place.

Attention : une écriture par le **constructeur de requêtes** (`DB::table('bookings')->update()`)
ne déclenche pas ce trait. Elle doit citer les deux côtés de chaque paire.

### Statut d'une réservation

| Statut | Sens |
|---|---|
| `en_attente` | Créée, personne n'a encore accepté |
| `confirme` | Un prestataire est assigné |
| `en_route` | Il se déplace |
| `sur_place` | Il est arrivé, présence confirmée |
| `termine` | L'intervention est close |
| `annule` | Annulée par le client ou la plateforme |
| `refuse` | Refusée |

## L'exécution

### Mission

Le travail de terrain. Table `missions`, 61 colonnes. Une réservation donne une ou plusieurs
missions — plusieurs quand la commande groupe des métiers.

### Assignation

Table `mission_assignments`. Qui fait quoi sur cette mission : un chef, des renforts.

Piège : `assignment_status = 'assigned'` est **ambigu**. C'est soit une offre de place de marché
en attente de réponse, soit une décision d'employeur déjà prise. Le discriminant est
`provider_organization_id` — s'il est renseigné, c'est un employeur qui a décidé.

### Présence

Table `provider_presence`. La position vivante du prestataire, poussée par le battement de cœur
de l'application mobile : statut, coordonnées, rayon accepté, dernier signal.

**C'est cette table qui fait foi pour « en ligne »**, pas un champ sur `users`. Un repli existe
sur `provider_profiles` pour un prestataire dont l'application n'a pas encore battu.

### Confirmation de présence

Le client affiche un code à six chiffres, le prestataire le saisit. C'est ce qui atteste qu'il
est bien arrivé, et ce qui ouvre la fiche d'accès (étage, digicode, consignes).

## Les acteurs

### Utilisateur

Table `users`, 55 colonnes. Un compte peut être client, prestataire, membre d'une société,
administrateur — parfois plusieurs à la fois.

Le rôle se lit par les **prédicats** de `HasUserTypeChecks` (`isProvider()`, `isCompanyClient()`)
et par ses **portées SQL** (`User::providers()`, `User::clients()`). Jamais par un
`where('role', …)` écrit à la main : la colonne `users.role` est un héritage encore lu en repli,
et l'interroger directement donne des résultats faux.

### Société

Table `organization_accounts`, 38 colonnes. Une société cliente commande pour ses sites ; une
société prestataire emploie des intervenants et répartit leur travail.

Les quatre relations commerciales sont toutes servies :

| | Client particulier | Client société |
|---|---|---|
| **Prestataire indépendant** | C2I | B2I |
| **Société prestataire** | C2B | B2B |

À la demande ou sous contrat.

## Ensuite

- [Parcours](parcours.md) — comment ces objets s'enchaînent
- [Données](donnees.md) — le schéma et ses pièges
