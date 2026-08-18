# Fondation mission terrain — moteur de mission et to-do list client

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development
> (recommended) or superpowers:executing-plans to implement this plan task-by-task.
> Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** poser le résolveur de moteur de mission dont tout le reste dépend, puis remplacer la
checklist imposée par la to-do list que le client remplit lui-même.

**Architecture :** un résolveur pur (`MissionEngine`) qui lit deux colonnes déjà figées sur la
réservation et rend un moteur exclusif ; un service (`MissionTodoService`) qui écrit dans la table
de checklist existante — celle qui barre déjà la clôture — avec une fenêtre d'édition bornée dans
le temps. Aucune nouvelle table, aucune nouvelle notion.

**Tech Stack :** Laravel 11, PHPUnit (attributs `#[Test]`), MySQL en développement, SQLite en test.

**Spec :** `docs/superpowers/specs/2026-08-18-mission-terrain-design.md`

## Global Constraints

- **Aucune nouvelle colonne booléenne tenue à la main.** `TradeRouteRules` l'interdit.
- **`missions.mission_type` est déjà pris** (standard / enterprise / batched_execution). Ne pas y
  toucher.
- **Un test de refus exige un témoin positif** prouvant que le chemin fonctionne quand il doit.
- **Aucun nom d'index au-delà de 64 caractères** — casse MySQL, invisible sous SQLite.
- **Migrations** : garde `Schema::hasTable` / `Schema::hasColumn`, nom de fichier daté après
  `2026_09_02_090000`, doc en tête expliquant la décision.
- **Ne jamais éditer un fichier pendant qu'une suite tourne.** Encadrer chaque exécution d'un
  `git status`.
- Valeurs de la spec, verbatim : fenêtre to-do **30 minutes** après `actual_start_at` ;
  réouverture du nouveau devis **6 minutes** par tâche ajoutée.

---

## Structure des fichiers

| Fichier | Responsabilité |
|---|---|
| `app/Support/Domain/MissionEngine.php` | **créé** — dit quel moteur, et ce que chaque moteur accepte |
| `config/missions.php` | **créé** — les deux durées de fenêtre |
| `database/migrations/2026_09_03_090000_porter_la_todo_du_client.php` | **créé** — 3 colonnes sur `mission_checklist_items` |
| `app/Services/Missions/MissionTodoService.php` | **créé** — ajouter, retirer, verrouiller, servir la fenêtre |
| `app/Services/Missions/MissionChecklistService.php` | modifié — le gabarit cesse d'imposer |
| `app/Services/Missions/OnSite/MissionChecklistService.php` | modifié — expose la source de chaque tâche |
| `app/Http/Controllers/Api/Client/ClientMissionOnSiteController.php` | modifié — 3 routes to-do |
| `routes/api/client.php` | modifié — déclare les 3 routes |
| `tests/Unit/MissionEngineTest.php` | **créé** |
| `tests/Feature/Missions/MissionTodoListTest.php` | **créé** |
| `tests/Feature/Missions/ChecklistGabaritTest.php` | **créé** |

---

### Task 1 : `MissionEngine`, le résolveur

**Files:**
- Create: `app/Support/Domain/MissionEngine.php`
- Test: `tests/Unit/MissionEngineTest.php`

**Interfaces:**
- Consumes: `App\Models\Booking` (`dropoff_lat`, `dropoff_lng`, `purchased_minutes`),
  `App\Models\Mission` (`booking`)
- Produces:
  - `MissionEngine::VEHICULE|HORAIRE|DOMICILE` (constantes `string`)
  - `MissionEngine::pourReservation(?Booking $booking): string`
  - `MissionEngine::pourMission(?Mission $mission): string`
  - `MissionEngine::accepteLeNouveauDevis(string $moteur): bool`
  - `MissionEngine::accepteLaToDoList(string $moteur): bool`
  - `MissionEngine::accepteLesCodes(string $moteur): bool`
  - `MissionEngine::accepteLeSupplement(string $moteur): bool`
  - `MissionEngine::all(): array<int,string>`

- [ ] **Step 1 : écrire le test qui échoue**

```php
<?php

namespace Tests\Unit;

use App\Models\Booking;
use App\Support\Domain\MissionEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MissionEngineTest extends TestCase
{
    private function reservation(array $attributs = []): Booking
    {
        // Pas de base : le résolveur est pur, il ne lit que des attributs.
        return (new Booking)->forceFill($attributs);
    }

    #[Test]
    public function une_depose_fait_une_mission_de_vehicule(): void
    {
        $this->assertSame(
            MissionEngine::VEHICULE,
            MissionEngine::pourReservation($this->reservation([
                'dropoff_lat' => 50.85, 'dropoff_lng' => 4.35,
            ])),
        );
    }

    #[Test]
    public function du_temps_achete_sans_depose_fait_une_mission_horaire(): void
    {
        $this->assertSame(
            MissionEngine::HORAIRE,
            MissionEngine::pourReservation($this->reservation(['purchased_minutes' => 180])),
        );
    }

    #[Test]
    public function le_reste_est_une_mission_a_domicile(): void
    {
        $this->assertSame(MissionEngine::DOMICILE, MissionEngine::pourReservation($this->reservation()));
        $this->assertSame(MissionEngine::DOMICILE, MissionEngine::pourReservation(null));
    }

    #[Test]
    public function le_vehicule_prime_sur_l_horaire_quand_les_deux_sont_vrais(): void
    {
        $this->assertSame(
            MissionEngine::VEHICULE,
            MissionEngine::pourReservation($this->reservation([
                'dropoff_lat' => 50.85, 'dropoff_lng' => 4.35, 'purchased_minutes' => 180,
            ])),
        );
    }

    #[Test]
    public function une_depose_incomplete_ne_fait_pas_une_course(): void
    {
        $this->assertSame(
            MissionEngine::DOMICILE,
            MissionEngine::pourReservation($this->reservation(['dropoff_lat' => 50.85])),
        );
    }

    #[Test]
    public function chaque_moteur_declare_ce_qu_il_accepte(): void
    {
        $this->assertTrue(MissionEngine::accepteLeNouveauDevis(MissionEngine::DOMICILE));
        $this->assertFalse(MissionEngine::accepteLeNouveauDevis(MissionEngine::HORAIRE));
        $this->assertFalse(MissionEngine::accepteLeNouveauDevis(MissionEngine::VEHICULE));

        $this->assertTrue(MissionEngine::accepteLaToDoList(MissionEngine::HORAIRE));
        $this->assertFalse(MissionEngine::accepteLaToDoList(MissionEngine::VEHICULE));

        $this->assertTrue(MissionEngine::accepteLesCodes(MissionEngine::DOMICILE));
        $this->assertFalse(MissionEngine::accepteLesCodes(MissionEngine::VEHICULE));

        $this->assertTrue(MissionEngine::accepteLeSupplement(MissionEngine::HORAIRE));
        $this->assertFalse(MissionEngine::accepteLeSupplement(MissionEngine::VEHICULE));
    }
}
```

- [ ] **Step 2 : lancer le test, vérifier qu'il échoue**

Run: `php artisan test tests/Unit/MissionEngineTest.php`
Expected: FAIL — `Class "App\Support\Domain\MissionEngine" not found`

- [ ] **Step 3 : écrire l'implémentation minimale**

```php
<?php

namespace App\Support\Domain;

use App\Models\Booking;
use App\Models\Mission;

/**
 * QUEL MOTEUR EXECUTE CETTE MISSION -- une seule reponse, et trois reponses possibles.
 *
 * Les trois parcours existaient deja, chacun avec son propre discriminant, lus a des endroits
 * differents : `Booking::estUneCourse()` d'un cote, `HourlyRateResolver::seFactureALHeure()` de
 * l'autre, et « tout le reste » nulle part. Rien ne les rendait EXCLUSIFS : un metier horaire
 * portant une depose etait les deux a la fois, et deux services en tiraient deux conclusions
 * opposees sur la meme mission.
 *
 * AUCUNE COLONNE NOUVELLE. `TradeRouteRules` interdit le drapeau booleen tenu a la main, et il a
 * raison : il finit par contredire sa source. Les deux discriminants existent deja, et ils sont
 * deja FIGES sur la reservation au moment de l'achat :
 *
 *   `dropoff_lat`/`dropoff_lng`  le point d'arrivee, ecrit par le moteur de commande
 *   `purchased_minutes`          le temps achete ; `null` dit « pas vendu au temps »
 *
 * C'est ce gel qui compte. `trades.hourly_billing` est lu EN DIRECT : un administrateur qui
 * decoche la case changerait la nature d'une mission en cours d'execution.
 *
 * L'ORDRE EST UNE PRIORITE STRICTE, et c'est lui qui rend les trois exclusifs. Le vehicule
 * d'abord : une course vendue au temps reste une course -- on ne demande pas six chiffres a
 * quelqu'un au volant.
 *
 * CE QUI N'EST PAS ICI. Cette classe dit quel PARCOURS et quelle PAGE.
 * `HourlyRateResolver::seFactureALHeure()` dit si le depassement est FACTURABLE. Deux questions
 * distinctes ; les fondre reproduirait le defaut qu'on ferme.
 */
final class MissionEngine
{
    /** D'un point a un autre : ni code, ni checklist, la trace GPS fait preuve. */
    public const VEHICULE = 'vehicule';

    /** Vendue au temps : compteur, prolongation, depassement. */
    public const HORAIRE = 'horaire';

    /** Tout le reste : codes de debut et de fin, checklist, nouveau devis. */
    public const DOMICILE = 'domicile';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::VEHICULE, self::HORAIRE, self::DOMICILE];
    }

    /**
     * LE MOTEUR D'UNE RESERVATION.
     *
     * `DOMICILE` est le repli, et c'est le choix PROTECTEUR : c'est le parcours qui exige les codes
     * et la checklist. Une reservation illisible ne doit pas se retrouver sur le parcours qui n'en
     * demande aucun.
     */
    public static function pourReservation(?Booking $booking): string
    {
        if ($booking === null) {
            return self::DOMICILE;
        }

        // Les DEUX coordonnees, comme `estUneCourse()` : une seule ne trace aucun itineraire et
        // ne donne aucun lieu ou confronter la position a la cloture.
        if ($booking->dropoff_lat !== null && $booking->dropoff_lng !== null) {
            return self::VEHICULE;
        }

        if ((int) ($booking->purchased_minutes ?? 0) > 0) {
            return self::HORAIRE;
        }

        return self::DOMICILE;
    }

    /**
     * LE MOTEUR D'UNE MISSION -- delegue a sa reservation, jamais decide ici.
     *
     * Une mission n'est qu'un dossier d'execution : ce qui a ete VENDU vit sur la reservation. Y
     * repondre autrement ferait deux sources pour une meme question.
     */
    public static function pourMission(?Mission $mission): string
    {
        return self::pourReservation($mission?->booking);
    }

    /** Le devis se revise la ou le prix vient d'une estimation : ni au temps, ni au trajet. */
    public static function accepteLeNouveauDevis(string $moteur): bool
    {
        return $moteur === self::DOMICILE;
    }

    /** Il n'y a rien a cocher sur un trajet. */
    public static function accepteLaToDoList(string $moteur): bool
    {
        return $moteur !== self::VEHICULE;
    }

    /** Les six chiffres attestent d'une rencontre a une porte ; une course n'en a pas. */
    public static function accepteLesCodes(string $moteur): bool
    {
        return $moteur !== self::VEHICULE;
    }

    /** Un supplement s'ajoute a une prestation ; le prix d'une course est fixe par le trajet. */
    public static function accepteLeSupplement(string $moteur): bool
    {
        return $moteur !== self::VEHICULE;
    }
}
```

- [ ] **Step 4 : lancer le test, vérifier qu'il passe**

Run: `php artisan test tests/Unit/MissionEngineTest.php`
Expected: PASS (6 tests)

- [ ] **Step 5 : commit**

```bash
git add app/Support/Domain/MissionEngine.php tests/Unit/MissionEngineTest.php
git commit -m "feat(mission): un seul resolveur pour les trois moteurs"
```

---

### Task 2 : les durées en configuration, et les trois colonnes de la to-do list

**Files:**
- Create: `config/missions.php`
- Create: `database/migrations/2026_09_03_090000_porter_la_todo_du_client.php`

**Interfaces:**
- Produces : `config('missions.todo_window_minutes')` = `30`,
  `config('missions.requote_reopen_minutes')` = `6` ;
  colonnes `mission_checklist_items.source`, `.created_by_user_id`, `.locked_at`

- [ ] **Step 1 : écrire la configuration**

```php
<?php

/*
 * LES DEUX DUREES DE LA FENETRE D'EDITION, EN CONFIGURATION ET PAS EN DUR.
 *
 * Elles se regleront sur les donnees reelles une fois le trafic present. Les ecrire dans le code
 * obligerait a un deploiement pour changer un nombre que l'exploitation doit pouvoir ajuster.
 */
return [
    // Combien de temps, apres le demarrage, le client peut encore toucher a sa liste.
    'todo_window_minutes' => (int) env('MISSION_TODO_WINDOW_MINUTES', 30),

    // Ce que chaque tache ajoutee rouvre au prestataire pour reviser son devis. La symetrie est
    // la regle : sans elle, un client ajoute des taches lourdes quand plus rien n'est revisable.
    'requote_reopen_minutes' => (int) env('MISSION_REQUOTE_REOPEN_MINUTES', 6),
];
```

- [ ] **Step 2 : écrire la migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * LA LISTE DU CLIENT EST LA CHECKLIST QUI BARRE DEJA LA CLOTURE.
 *
 * Ce depot porte deja TROIS checklists -- celle de la mission, celle de l'inspection qualite, et
 * un tableau JSON sur la reservation. Une seule barre la porte :
 * `MissionLifecycleService::assertRequiredChecklistCompleted()` interroge `mission_checklist_items`.
 * En creer une quatrieme pour le client aurait reproduit exactement le defaut dominant du depot.
 *
 * Trois colonnes suffisent :
 *
 *   `source`              qui a mis cette tache la -- client, gabarit, ou prestataire. Sans elle,
 *                         impossible de distinguer une tache que le client a ecrite d'une tache
 *                         suggeree, ni de savoir laquelle retirer quand il change d'avis.
 *   `created_by_user_id`  la personne, pour l'affichage et pour l'audit.
 *   `locked_at`          l'instant ou la liste s'est figee. Une DATE et non un booleen : elle dit
 *                         AUSSI quand, ce qu'un drapeau ne dit pas -- et c'est cette date que le
 *                         support relira quand un client affirmera avoir ajoute a temps.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('mission_checklist_items')) {
            return;
        }

        Schema::table('mission_checklist_items', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_checklist_items', 'source')) {
                $table->string('source', 16)->default('template')->after('label');
            }

            if (! Schema::hasColumn('mission_checklist_items', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->after('source');
            }

            if (! Schema::hasColumn('mission_checklist_items', 'locked_at')) {
                $table->timestamp('locked_at')->nullable()->after('completed_at');
            }
        });

        // Index court : « les taches du client sur cette liste », la seule requete nouvelle.
        // Nom tenu sous 64 caracteres -- au-dela, MySQL refuse et SQLite ne le dit pas.
        Schema::table('mission_checklist_items', function (Blueprint $table) {
            $table->index(['mission_checklist_id', 'source'], 'mci_liste_source_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('mission_checklist_items')) {
            return;
        }

        Schema::table('mission_checklist_items', function (Blueprint $table) {
            $table->dropIndex('mci_liste_source_index');
            $table->dropColumn(['source', 'created_by_user_id', 'locked_at']);
        });
    }
};
```

- [ ] **Step 3 : jouer la migration et vérifier le schéma**

```bash
php artisan migrate
php artisan tinker --execute="foreach(DB::select('SHOW COLUMNS FROM mission_checklist_items') as \$c){ if(in_array(\$c->Field,['source','created_by_user_id','locked_at'])) echo \$c->Field.' '.\$c->Type.PHP_EOL; }"
```
Expected : les trois colonnes listées.

- [ ] **Step 4 : ajouter les colonnes au modèle**

Dans `app/Models/MissionChecklistItem.php`, ajouter `'source'`, `'created_by_user_id'`,
`'locked_at'` à `$fillable`, et `'locked_at' => 'datetime'` aux casts.

> ⚠️ Sans cet ajout, Eloquent **écarte en silence** : la tâche se crée sans sa source, et rien ne
> le signale.

- [ ] **Step 5 : commit**

```bash
git add config/missions.php database/migrations/2026_09_03_090000_porter_la_todo_du_client.php app/Models/MissionChecklistItem.php
git commit -m "feat(mission): la checklist sait desormais qui a pose chaque tache"
```

---

### Task 3 : `MissionTodoService` — la fenêtre et le verrouillage

**Files:**
- Create: `app/Services/Missions/MissionTodoService.php`
- Test: `tests/Feature/Missions/MissionTodoListTest.php`

**Interfaces:**
- Consumes: `MissionEngine::accepteLaToDoList()`, `config('missions.todo_window_minutes')`
- Produces:
  - `fenetre(Mission $mission): array{open: bool, closes_at: ?string, minutes_left: ?int, reason: ?string}`
  - `ajouter(Mission $mission, User $client, string $label): MissionChecklistItem`
  - `retirer(Mission $mission, User $client, MissionChecklistItem $item): void`
  - `pourLeClient(Mission $mission): array{engine: string, window: array, items: list<array>, suggestions: list<string>}`
  - lève `DomainException` sur fenêtre fermée, moteur non éligible, tâche déjà cochée

**Règles que le service tient — chacune a son test :**

| Règle | Refus rendu |
|---|---|
| Moteur `VEHICULE` | « Une course n'a pas de liste de tâches. » |
| Fenêtre close (30 min après `actual_start_at`) | « La liste est figée depuis HH:MM. » |
| Mission terminée ou annulée | « L'intervention est terminée. » |
| Retirer une tâche déjà cochée | « Le prestataire a déjà fait cette tâche. » |
| Retirer une tâche qui n'est pas `source = client` | « Cette tâche ne vient pas de vous. » |
| Libellé vide ou > 191 caractères | « Dites en une phrase ce qui doit être fait. » |

**La fenêtre, en une règle :**

```
window_closes_at = actual_start_at ? actual_start_at + todo_window_minutes : null
open = engine accepte la to-do
       ET mission non terminee/annulee
       ET (window_closes_at === null OU now() < window_closes_at)
```

`null` avant démarrage = **ouverte sans échéance**, ce qui est juste : la mission n'a pas commencé,
le prestataire n'a rien pu faire.

- [ ] **Step 1 : écrire le test qui échoue** — `tests/Feature/Missions/MissionTodoListTest.php`,
      une méthode par ligne du tableau ci-dessus, plus **les deux témoins positifs** :
      `le_client_ajoute_une_tache_avant_le_demarrage` et
      `le_client_ajoute_une_tache_a_vingt_neuf_minutes` (via `Carbon::setTestNow`).
- [ ] **Step 2 :** `php artisan test tests/Feature/Missions/MissionTodoListTest.php` → FAIL (classe absente)
- [ ] **Step 3 :** écrire `MissionTodoService` avec les six refus et les deux écritures
- [ ] **Step 4 :** `php artisan test tests/Feature/Missions/MissionTodoListTest.php` → PASS
- [ ] **Step 5 :** commit `feat(mission): la to-do list du client, et la fenetre qui la ferme`

---

### Task 4 : l'API client de la to-do list

**Files:**
- Modify: `app/Http/Controllers/Api/Client/ClientMissionOnSiteController.php`
- Modify: `routes/api/client.php`
- Test: `tests/Feature/Missions/MissionTodoListTest.php` (ajouts)

**Interfaces:**
- Produces :
  - `GET  /api/bookings/{booking}/onsite/todo` → `{ok, engine, window, items, suggestions}`
  - `POST /api/bookings/{booking}/onsite/todo` body `{label: string}` → `{ok, item}`
  - `DELETE /api/bookings/{booking}/onsite/todo/{item}` → `{ok}`

- [ ] **Step 1 :** test d'API — un client qui n'est pas propriétaire reçoit 403 ; **témoin** : le
      propriétaire reçoit 200
- [ ] **Step 2 :** FAIL (route absente, 404)
- [ ] **Step 3 :** trois méthodes de contrôleur déléguant à `MissionTodoService`, trois routes dans
      le groupe `onsite` existant
- [ ] **Step 4 :** PASS
- [ ] **Step 5 :** commit `feat(api): le client tient sa liste depuis son espace`

---

### Task 5 : la source visible côté prestataire

**Files:**
- Modify: `app/Services/Missions/OnSite/MissionChecklistService.php:48-56`
- Test: `tests/Feature/Missions/MissionTodoListTest.php` (ajout)

**Interfaces:**
- Produces : chaque item du payload prestataire porte `source` et `added_by`

- [ ] **Step 1 :** test — une tâche `source = client` est servie avec `'source' => 'client'` et le
      prénom du client dans `added_by`
- [ ] **Step 2 :** FAIL (clés absentes)
- [ ] **Step 3 :** ajouter les deux clés dans le `map()` de `pour()`
- [ ] **Step 4 :** PASS
- [ ] **Step 5 :** commit `feat(mission): le prestataire voit qui a demande chaque tache`

> Le prestataire doit distinguer une tâche que le client a écrite d'une tâche générique : la
> première se discute avec lui, la seconde non.

---

### Task 6 : le gabarit cesse d'imposer

**Files:**
- Modify: `app/Services/Missions/MissionChecklistService.php:35-68`
- Test: `tests/Feature/Missions/ChecklistGabaritTest.php`

**Interfaces:**
- Consumes : `MissionEngine::accepteLaToDoList()`
- Produces : `MissionChecklistService::suggestionsPour(Mission $mission): list<string>`
  *(le gabarit d'aujourd'hui, rendu comme propositions)*

**Le changement, en une phrase :** `ensureChecklist()` crée toujours la `mission_checklists`, mais
**n'y pose plus aucun item**. Le gabarit, lui, devient `suggestionsPour()`, servi au client dans le
payload de la Task 3.

- [ ] **Step 1 : écrire le test qui échoue**

```php
#[Test]
public function une_mission_neuve_n_a_plus_de_tache_imposee(): void
{
    $mission = $this->missionDomicile();

    app(MissionChecklistService::class)->ensureChecklist($mission);

    $this->assertSame(1, $mission->checklists()->count(), 'la liste existe');
    $this->assertSame(0, MissionChecklistItem::query()
        ->whereIn('mission_checklist_id', $mission->checklists()->pluck('id'))
        ->count(), 'et elle est vide');
}

#[Test]
public function le_gabarit_survit_en_suggestions(): void
{
    $suggestions = app(MissionChecklistService::class)->suggestionsPour($this->missionDomicile());

    $this->assertNotEmpty($suggestions);
    $this->assertContainsOnly('string', $suggestions);
}

#[Test]
public function une_course_n_a_toujours_aucune_checklist(): void
{
    $this->assertNull(app(MissionChecklistService::class)->ensureChecklist($this->missionCourse()));
}
```

- [ ] **Step 2 :** `php artisan test tests/Feature/Missions/ChecklistGabaritTest.php` → FAIL
- [ ] **Step 3 :** retirer la boucle de création d'items de `ensureChecklist()`, exposer
      `suggestionsPour()` qui rend `$this->resolveTemplate($mission)['items']`
- [ ] **Step 4 :** PASS
- [ ] **Step 5 :** commit `feat(mission): le gabarit propose, il n'impose plus`

---

### Task 7 : la clôture suit la liste du client — non-régression

**Files:**
- Test: `tests/Feature/Missions/ChecklistGabaritTest.php` (ajouts)
- Aucun code applicatif : `assertRequiredChecklistCompleted()` fonctionne déjà ainsi.

**Ce test est le cœur du lot.** Il prouve que le comportement demandé est obtenu **sans toucher à
la porte de clôture**.

- [ ] **Step 1 : écrire les trois cas, avec leur témoin**

```php
#[Test]
public function liste_vide_la_cloture_passe(): void
{
    // Le comportement DEMANDE : « si la to-do list est vide il peut fermer la mission ».
    $mission = $this->missionDemarree();

    $ferme = app(MissionLifecycleService::class)
        ->completeMission($mission, $this->prestataire, 50.8466, 4.3528);

    $this->assertSame(MissionStatus::COMPLETED, $ferme->status);
}

#[Test]
public function une_tache_client_ouverte_bloque_la_cloture(): void
{
    $mission = $this->missionDemarree();
    $this->tacheClient($mission, 'Nettoyer la hotte');

    $this->expectException(RuntimeException::class);
    app(MissionLifecycleService::class)
        ->completeMission($mission, $this->prestataire, 50.8466, 4.3528);
}

#[Test]
public function la_meme_tache_cochee_debloque_la_cloture(): void
{
    // LE TEMOIN du test precedent : sans lui, le refus ci-dessus pourrait mesurer une panne.
    $mission = $this->missionDemarree();
    $item = $this->tacheClient($mission, 'Nettoyer la hotte');
    $item->update(['status' => 'done']);

    $ferme = app(MissionLifecycleService::class)
        ->completeMission($mission, $this->prestataire, 50.8466, 4.3528);

    $this->assertSame(MissionStatus::COMPLETED, $ferme->status);
}
```

- [ ] **Step 2 :** lancer — les trois doivent passer **sans modifier une ligne d'application**.
      Si l'un échoue, c'est le lot précédent qui est faux, pas ce test.
- [ ] **Step 3 :** commit `test(mission): la cloture suit la liste du client, vide ou pleine`

---

### Task 8 : la suite ciblée, puis le verdict

- [ ] **Step 1 :** `git status --porcelain` — doit être vide avant de lancer
- [ ] **Step 2 :** `php artisan test tests/Feature/Missions tests/Unit/MissionEngineTest.php`
- [ ] **Step 3 :** `php artisan test tests/Feature/Api/Provider tests/Feature/Phase12`
      *(les suites qui touchent la clôture et la checklist)*
- [ ] **Step 4 :** `php -d memory_limit=2G vendor/bin/phpstan analyse --no-progress`
      — **sans argument de chemin**, sinon il rend vert sur du rouge
- [ ] **Step 5 :** `php artisan migrate --pretend` pour vérifier le SQL MySQL réel

---

## Self-review — couverture de la spec par ce plan

| Section de spec | Tâche |
|---|---|
| § 1.1 discriminant, § 1.2, § 1.3 | Task 1 |
| § 1.4 tableau des moteurs (`accepte*`) | Task 1 |
| § 2.1 colonnes | Task 2 |
| § 2.3 fenêtre et minuteur | Tasks 2, 3 |
| § 2.2 gabarit en suggestions | Task 6 |
| § 2.4 qui coche | Task 7 *(la porte n'est pas touchée)* |
| § 2.5 périmètre par moteur | Tasks 1, 3, 6 |
| § 9 non-régression course | Tasks 1, 6 |
| § 10 témoins positifs | Tasks 3, 4, 6, 7 |

**Hors périmètre de ce plan, couvert par les suivants :** § 1.5 portes de refus du nouveau devis
(plan 2), §§ 3 à 4 nouveau devis et arbitre (plan 2), § 8 annulation (plan 3), §§ 5 à 7 surfaces
et modules (plans 4 et 5).

## La suite des plans

| Plan | Lots de la spec | Contenu |
|---|---|---|
| 1 — celui-ci | L0, L1, L2 | moteur, to-do list, gabarit |
| 2 | L3, L4, L5 | nouveau devis, refus, arbitre et sanctions |
| 3 | L6 | annulation, questionnaire, module admin |
| 4 | L7, L8, L9, L10 | surfaces client et prestataire, web et mobile |
| 5 | L11, L12 | les sept ajouts, puis la passe graphique |
