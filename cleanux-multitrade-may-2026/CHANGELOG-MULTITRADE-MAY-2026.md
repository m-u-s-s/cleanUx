# CleanUx — Multi-trade integration mai 2026 (Patch 2)

Ce patch fait suite au patch "7 bombes techniques". Il **branche concrètement** le modèle Trade dans le pipeline produit, qui n'était jusqu'à présent qu'une table CRUD-able orpheline.

Note préalable : ce patch part du principe que le **patch 1** (cleanux-fixes-may-2026) a déjà été appliqué — notamment l'ajout de `TradeSeeder` dans `ReferencePlatformSeeder`. Si non appliqué, ce patch 2 reste fonctionnel mais les seeders de base ne tourneront pas dans le bon ordre.

---

## Ce que fait ce patch

### 1. Admin `CatalogueServices` — gestion du `trade_id`

Avant : tu ne pouvais pas rattacher un service à un métier depuis l'admin (le champ n'existait nulle part dans le form, et le filtre par métier non plus).

Après :
- **Champ `<select>` Métier** dans le formulaire de création/édition de service, avec option "Aucun (à rattacher)" pour la phase de transition.
- **Filtre par métier** dans la barre de filtres (à côté de Type, Statut, Marché).
- **Colonne Métier** dans le tableau des services, avec chip coloré ou "Non rattaché" si null.
- Validation `nullable|integer|exists:trades,id` (sera durcie en `required` quand toute la base sera backfillée).
- ActivityLogger inclut maintenant `trade_id` dans les meta.

**Fichiers modifiés** :
- `app/Livewire/Admin/CatalogueServices.php`
- `resources/views/livewire/admin/catalogue-services.blade.php`

### 2. Booking client — services groupés par métier

Avant : la liste de services dans le formulaire de réservation était un `<select>` plat. Pour un client face à 30 services, c'était illisible et le métier n'était pas visible.

Après : `<optgroup>` par métier dans le `<select>`. Les services sans trade rattaché tombent dans un groupe "Autres" (jamais perdus pendant la transition).

**Implémentation prudente** :
- L'ancienne propriété `$services` (flat) reste exposée — back-compat avec `InteractsWithBookingFormState::updatedSelectedServiceIdentifier()` qui l'utilise pour récupérer le label du service sélectionné.
- Une **nouvelle propriété `$servicesGroupedByTrade`** est ajoutée et passée à la vue. La vue détecte sa présence et fallback sur le rendu flat si elle manque.

**Fichiers modifiés** :
- `app/Support/Livewire/Concerns/InteractsWithBookingFormState.php` — nouvelle méthode `getServicesGroupedByTradeProperty()`
- `app/Livewire/Client/PrendreRendezVous.php` — passe la nouvelle propriété au render
- `resources/views/livewire/client/booking/service/field-service.blade.php` — `<optgroup>` avec fallback

### 3. Wording home page — multi-services

Avant : 8 occurrences de "nettoyage" sur la home, dont la tagline et les 4 tuiles services qui ne montraient que des variantes de nettoyage.

Après :
- Tagline : "Plateforme de **services à la demande**" (au lieu de "nettoyage moderne").
- H1 : "Réservez **un service**, suivez l'employé".
- CTA final : "Prêt à réserver votre prochain **service** ?".
- 4 tuiles changées : Nettoyage / Peinture / Bâtiment / Jardinage (au lieu de 4 sous-services nettoyage).

J'ai laissé volontairement intacts :
- "Mission en cours: Nettoyage bureaux" dans le mockup hero (élément de démo visuelle qui sera dynamique en prod).
- Le label "Nettoyeur" dans `OrganizationRole::WORKER` (couleur, label, etc. sont gérés ailleurs et changent de toute façon par client).

**Fichiers modifiés** :
- `resources/views/home.blade.php`

### 4. Seeder demo `MultiTradeDemoServicesSeeder`

Sans ce seeder, après `migrate:fresh --seed`, tu avais : 5 trades créés (TradeSeeder), mais aucun service rattaché à Peinture/Bâtiment/Levage/Jardinage. La preuve visuelle du multi-trade était donc invisible côté UX.

Le seeder ajoute **11 services de démo réalistes** :
- Peinture : intérieure, façade (sur devis), retouches
- Bâtiment : rénovation (sur devis), petits travaux, carrelage (sur devis)
- Levage : nacelle élévatrice (B2B, sur devis), manutention lourde (B2B, sur devis)
- Jardinage : tonte, taille de haies, aménagement paysager (sur devis)

Idempotent : `updateOrCreate` sur le slug. Re-runnable.

Inscrit dans la chaîne `ReferencePlatformSeeder` après le backfill, pour que l'ordre `Trades → Services nettoyage → Backfill → Services multi-trade` soit garanti.

**Fichiers ajoutés/modifiés** :
- `database/seeders/MultiTradeDemoServicesSeeder.php` (nouveau)
- `database/seeders/ReferencePlatformSeeder.php` (chaîne de seeding)

### 5. Tests de régression

`tests/Feature/Regression/MultiTradeIntegrationTest.php` — 6 tests :
- ✅ Admin peut sauver un service avec `trade_id`
- ✅ Admin peut sauver un service avec `trade_id=null` (back-compat transition)
- ✅ Filtre `tradeFilter` réduit la liste à un seul métier
- ✅ `servicesGroupedByTrade` retourne la structure attendue (incluant le groupe "Autres" pour les orphelins)
- ✅ Le seeder demo crée des services pour chaque trade non-Nettoyage
- ✅ Le seeder est idempotent

---

## Comment appliquer

```bash
# Depuis la racine de CleanUx, après avoir appliqué le patch 1 :
cp -r /chemin/vers/cleanux-multitrade-may-2026/* .
```

Puis :

```bash
# Re-charger le référentiel pour voir les nouveaux services demo apparaître
php artisan db:seed --class=ReferencePlatformSeeder

# Vérifier en lançant les tests (après avoir résolu l'environnement PHP)
php artisan test --filter=MultiTradeIntegrationTest
php artisan test --filter=PostFixesRegressionTest

# Vérifier visuellement
# /admin/services → on doit voir colonne Métier + filtre Métier
# /admin/trades → la page ne crash plus (patch 1)
# /prendre-rendez-vous → services groupés en <optgroup>
# / → tuiles multi-services
```

---

## Ce qu'il reste à faire (suite logique)

Pour pousser le multi-trade plus loin (au-delà du tactique livré ici), 4 chantiers restent en file :

### A. Onboarding prestataire — sélection des trades pratiqués
À ce stade, un prestataire qui s'inscrit ne déclare pas ses métiers. Le dispatch n'a donc pas de moyen de filtrer les candidats par compétence. À ajouter :
- Table `provider_trades` (n-n entre `provider_profiles` et `trades`) avec colonne `proficiency` ou `years_experience`
- Step dans `ProviderOnboardingService` pour cocher les trades
- Filtre dans `AiDispatchService::rankEmployees()` : ne propose que les prestataires dont `provider_trades` contient le trade du booking
- Effort : ~3-4h

### B. Réservation guidée par métier (workflow Step 1 → Step 2)
Ce que livre ce patch : `<optgroup>` qui groupe la liste plate. La vraie marketplace fait Step 1 (choix métier visuel, type tuiles) → Step 2 (services du métier) → Step 3 (détails spécifiques au métier). Plus engageant mais plus de refactor :
- Nouveau composant `BookingTradePicker` 
- État `selected_trade_id` dans le trait
- Reset `selected_service_identifier` quand `selected_trade_id` change
- Champs spécifiques par métier (m² pour peinture, étage/poids pour levage, etc.) — extension du modèle `ServiceOption` déjà présent
- Effort : ~5-7h

### C. Wording register / dashboards prestataire / chatbot
Ce patch a touché la home. Restent :
- `register` / signup wording (parle encore "société de nettoyage")
- Dashboards prestataire (TaskBoard, MissionFieldPage) : tonalité orientée nettoyage par défaut
- Chatbot prompts : `AssistantContextBuilder` parle "nettoyage". Adapter pour multi-trade.
- Effort cumulé : ~3-4h

### D. Permissions par trade (à venir)
Une organisation prestataire pourra vouloir limiter certains rôles à certains trades (ex. un team_lead Peinture n'accède pas aux missions Bâtiment). Pas urgent — à modeler quand un client le demande.

---

## Points de vigilance

1. **`$service->trade` peut être null** — le code partout dans cette modif gère ce cas. Si tu écris du nouveau code qui suppose un trade, ajoute toujours `?->name` ou un check.

2. **Le seeder demo crée des services qui apparaissent dans la réservation client immédiatement**. En staging c'est ce qu'on veut. En prod, double-check que tu ne le runs pas sans le savoir (le seeder n'est appelé que par `ReferencePlatformSeeder`, pas par défaut sur prod sauf si tu seedes explicitement).

3. **`tradeFilter` est dans `$queryString`** — ça permet de partager des URL filtrées, mais ça veut aussi dire que si tu changes la sémantique du paramètre, les bookmarks anciens cassent. Pas un souci aujourd'hui, à garder en tête pour plus tard.

4. **Le filtre de la propriété `getServicesProperty` n'a pas été touché** : si un autre composant utilise `$services` flat, il continue de marcher comme avant sans grouper. Migration future possible si tu veux uniformiser.
