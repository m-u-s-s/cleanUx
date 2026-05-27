# Spec: Priorité 1 — Neutraliser le nettoyage-first

**Date**: 2026-05-27
**Scope**: DB schema, seeders, labels UI, admin CRUD, User model cleanup
**Goal**: Transformer CleanUx d'une plateforme de nettoyage en marketplace multi-métiers générique

---

## Décisions validées

- **12 métiers** : Nettoyage, Peinture, Bâtiment, Plomberie, Électricité, Jardinage, Déménagement, Garde d'enfants, Toiture, Levage/Lift, Rénovation, Sécurité/Gardiennage
- **Migration stricte** des rôles : suppression de la colonne `role` legacy
- **Généralisation** des champs OrganizationSite
- **Admin CRUD** métiers avec pricing par zone

---

## Section A — Migrations (3 fichiers)

### A1: `trade_id` NOT NULL sur `service_catalogs`

1. Backfill: tous les `service_catalogs` où `trade_id IS NULL` → assigner au trade "Nettoyage" (résolu par slug `cleaning`)
2. `ALTER COLUMN trade_id SET NOT NULL` + foreign key constraint

### A2: Généraliser `organization_sites`

1. Renommer `cleaning_frequency` → `service_frequency` (si la colonne existe)
2. Drop `nombre_heures_nettoyage` (si existe — redondant avec booking duration)
3. Ajouter `trade_preferences JSON nullable` — stocke les préférences par métier:
   ```json
   {
     "cleaning": {"frequency": "weekly", "preferred_day": "monday"},
     "gardening": {"season": "spring-summer"}
   }
   ```

### A3: Supprimer la colonne `role` sur `users`

Migration de données avant drop:
- `role = 'employe'` ET `provider_type IS NULL` → `provider_type = 'independent'`
- `role = 'client'` ET `customer_type IS NULL` → `customer_type = 'personal'`
- `role = 'entreprise'` ET `customer_type IS NULL` → `customer_type = 'company'`
- `role = 'admin'` ET `platform_role IS NULL` → `platform_role = 'admin'`

Puis: `DROP COLUMN role`

---

## Section B — Seeders 12 métiers + ~50 services

### TradeSeeder (12 trades)

Chaque trade a:
- `name` (FR), `slug`, `code` (uppercase 3-4 lettres)
- `description` (1 phrase)
- `icon` (nom Heroicon: wrench, paint-brush, bolt, etc.)
- `form_schema` JSON — questions spécifiques métier pour le booking
- `billing_unit` par défaut (hourly, per_m2, fixed, per_item)
- `requires_site_visit` (bool)
- `is_active` = true

| Slug | Code | Name | billing_unit | requires_site_visit |
|------|------|------|-------------|-------------------|
| cleaning | CLN | Nettoyage | hourly | false |
| painting | PNT | Peinture | per_m2 | true |
| construction | BLD | Bâtiment / Gros œuvre | fixed | true |
| plumbing | PLB | Plomberie | hourly | false |
| electrical | ELC | Électricité | hourly | false |
| gardening | GRD | Jardinage | hourly | false |
| moving | MOV | Déménagement | fixed | true |
| childcare | CHD | Garde d'enfants | hourly | false |
| roofing | ROF | Toiture | per_m2 | true |
| lifting | LFT | Levage / Lift | fixed | true |
| renovation | RNV | Rénovation | per_m2 | true |
| security | SEC | Sécurité / Gardiennage | hourly | false |

### ServiceCatalogSeeder (~50 services)

3-5 services par métier. Exemples:
- **Nettoyage**: Standard, Profond, Fin de chantier, Fin de bail, Bureaux, Vitres
- **Peinture**: Intérieur murs, Intérieur plafond, Extérieur façade, Boiseries, Décoration
- **Plomberie**: Dépannage urgent, Installation sanitaire, Débouchage, Chauffe-eau
- **Électricité**: Dépannage, Mise en conformité, Installation tableau, Éclairage
- etc.

Chaque service a: `name`, `trade_id`, `billing_unit`, `base_price`, `vat_rate`, `description`, `is_active`

---

## Section C — Nettoyage labels UI

### Rechercher et remplacer dans les vues Blade:
- "nettoyeur" → "prestataire" (toutes les occurrences)
- "nettoyage" en tant que label générique → "service" (sauf quand c'est le nom du trade)
- "employé" utilisé comme rôle → "prestataire" (sauf `organization_members` où c'est le vrai rôle interne)
- Dashboard admin: "Nettoyages du jour" → "Missions du jour"

### Ne PAS toucher:
- Les noms de trade ("Nettoyage" est un trade valide)
- Les noms de routes (breaking change trop large)
- Les noms de tables DB (trop risqué)

---

## Section D — Admin CRUD Métiers + Pricing par zone

### Table `trade_zone_pricing` (nouvelle)

| Colonne | Type | Description |
|---------|------|-------------|
| id | bigint PK | |
| trade_id | FK trades | |
| service_zone_id | FK service_zones | |
| base_rate_cents | integer | Tarif de base en centimes |
| surge_multiplier | decimal(4,2) default 1.00 | Multiplicateur peak |
| min_price_cents | integer nullable | Prix minimum |
| max_price_cents | integer nullable | Prix maximum |
| is_active | boolean default true | Métier actif dans cette zone |
| metadata | JSON nullable | |
| timestamps | | |

UNIQUE constraint: `(trade_id, service_zone_id)`

### Admin UI (Livewire dans le CRUD Trades existant)

- Onglet "Pricing par zone" dans le détail d'un trade
- Grille: zones en lignes, colonnes = base_rate, surge, min, max, actif
- Inline edit + save
- Filtre par zone

---

## Section E — Nettoyage modèle User

### Supprimer `HasLegacyRoleCompatibility` trait

Ce trait mappe `role` vers les nouveaux champs. Après la migration A3 (drop `role`), il n'a plus de raison d'être.

### Mettre à jour `HasUserTypeChecks`

- Supprimer toute référence à `$this->role`
- Ne lire que `platform_role`, `customer_type`, `provider_type`

### Mettre à jour `CheckRole` middleware

- Ne plus lire `role` — utiliser `platform_role` uniquement
- Les checks par type (isClient, isProvider) passent par les traits

### Mettre à jour les tests

- Tous les tests qui créent des users avec `role => 'employe'` → utiliser `provider_type => 'independent'`
- Tous les tests avec `role => 'client'` → utiliser `customer_type => 'personal'`
- Tous les tests avec `role => 'admin'` → utiliser `platform_role => 'admin'`

### Mettre à jour UserFactory

- Supprimer `role` des définitions
- Les states `employe()`, `client()`, `admin()` doivent utiliser les nouveaux champs

---

## Tests requis

- Migration A1: test que `trade_id` est NOT NULL après migration
- Migration A3: test que les données sont correctement migrées avant drop
- TradeSeeder: test que 12 trades existent après seed
- ServiceCatalogSeeder: test que chaque trade a au moins 3 services
- `trade_zone_pricing` CRUD: test create/update/delete + unique constraint
- User sans `role`: test que isClient/isProvider/isAdmin fonctionnent
- CheckRole middleware: test que le middleware fonctionne sans `role`

---

## Hors scope

- Flux de réservation multi-métiers (Priorité 2)
- Chatbot par rôle (Priorité 3)
- Formulaires dynamiques par trade dans le booking (Priorité 2)
- Pricing engine complet (Priorité 2)
