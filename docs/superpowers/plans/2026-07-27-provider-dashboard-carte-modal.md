# Dashboard provider carte-first + modal d'actions — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Transformer le dashboard prestataire en écran carte-first (missions en attente = marqueurs) avec tous les boutons de la page repliés dans un bottom sheet, après avoir réparé le contrat de l'inbox dont dépendent les libellés des marqueurs.

**Architecture:** Le contrat API est aligné d'abord (le sérialiseur de liste devient plat, conforme au type TS que les écrans consomment déjà), puis la carte est construite dessus. `react-native-maps` est chargé derrière un loader défensif afin qu'un runtime sans le module natif dégrade vers un placeholder texte au lieu de planter. Le modal réutilise le `BottomSheet` partagé et le `PresenceToggle` existant.

**Tech Stack:** Laravel 12 / PHP 8.5 · React Native + Expo SDK 56 · TypeScript · `react-native-maps@1.27.2` · `@gorhom/bottom-sheet` · TanStack Query · Jest + `@testing-library/react-native` + `axios-mock-adapter` · PHPUnit

**Spec :** `docs/superpowers/specs/2026-07-27-provider-dashboard-carte-modal-design.md`

## Global Constraints

- Version de `react-native-maps` : **exactement `1.27.2`**, installée via `npx expo install react-native-maps` (version épinglée par `expo/bundledNativeModules.json`, identique à l'app client).
- **L'app client (`mobile/client`) n'est pas modifiée.** Aucun fichier sous `mobile/client/` ne doit apparaître dans un **commit de ce plan**. Attention : `mobile/client/App.tsx`, `app.json`, `metro.config.js` et `package.json` sont **déjà modifiés dans l'arbre de travail** par du travail antérieur sans lien avec ce plan — ne jamais les stager, ne pas chercher à les « réparer ».
- `show()` (`GET /api/provider/assignments/{assignment}`) **conserve sa forme imbriquée actuelle**. `tests/Feature/Phase11/ProviderMissionAssignmentControllerTest.php` doit rester vert sans modification.
- Aucune migration de base de données. Aucun géocodage. Aucun appel réseau ajouté côté serveur.
- Tout texte visible par l'utilisateur est en **français**.
- Chaque test écrit doit être **vu échouer avant d'être vu passer**. Un test qui passe du premier coup sur du code non encore écrit est un test faux : le corriger avant de continuer.
- La tab bar (`Dashboard` / `Missions` / `Revenus` / `Profil`) reste en place.
- `distance_km` **n'est jamais un champ d'API** : c'est une valeur dérivée côté mobile.

## Structure de fichiers

| Fichier | Responsabilité |
|---|---|
| `app/Http/Controllers/Api/ProviderMissionAssignmentController.php` | 2 sérialiseurs séparés : liste (plat) et détail (imbriqué) |
| `tests/Feature/Api/Provider/ProviderAssignmentInboxContractTest.php` | verrouille le payload plat de l'inbox |
| `database/seeders/DevProviderMissionSeeder.php` | fixture de dév : booking géocodé + mission + assignation |
| `mobile/provider/src/missions/types.ts` | contrat TS de `MissionAssignment` |
| `mobile/provider/src/tracking/distance.ts` | haversine partagé, dérivation de distance |
| `mobile/provider/src/maps/module.ts` | chargement défensif de `react-native-maps` |
| `mobile/provider/src/tracking/hooks.ts` | `useGpsWatcher` expose l'état de permission |
| `mobile/provider/src/screens/components/ProviderMap.tsx` | la carte, ses marqueurs et ses replis |
| `mobile/provider/src/screens/components/PresencePill.tsx` | affichage seul du statut courant |
| `mobile/provider/src/screens/components/DashboardActionsSheet.tsx` | le modal : statut, KPIs, accès rapides |
| `mobile/provider/src/screens/DashboardScreen.tsx` | assemblage carte + calques flottants |
| `mobile/provider/App.tsx` | `GestureHandlerRootView` à la racine |
| `mobile/provider/__mocks__/react-native-maps.tsx` | stub Jest du module natif |

---

### Task 1 : Payload plat de l'inbox (backend)

**Files:**
- Modify: `app/Http/Controllers/Api/ProviderMissionAssignmentController.php:38-60` (eager-load `inbox`), `:78` (appel dans `show`), `:137-179` (sérialiseur)
- Test: `tests/Feature/Api/Provider/ProviderAssignmentInboxContractTest.php` (créer)

**Interfaces:**
- Consomme : rien.
- Produit : le payload de `GET /api/provider/assignments/inbox`, dont chaque élément de `data` a les clés `id`, `mission_id`, `assignment_status`, `assigned_at`, `expires_at`, `remaining_seconds`, `booking_id`, `service_name`, `client_name`, `address`, `city`, `postal_code`, `scheduled_date`, `scheduled_time`, `latitude`, `longitude`, `created_at`. `latitude`/`longitude` sont des `float` ou `null`. Toutes les tâches mobiles suivantes dépendent de ces noms exacts.

- [ ] **Step 1 : Écrire le test qui échoue**

Créer `tests/Feature/Api/Provider/ProviderAssignmentInboxContractTest.php` :

```php
<?php

namespace Tests\Feature\Api\Provider;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\ProviderProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verrouille le contrat plat de GET /api/provider/assignments/inbox.
 *
 * Le sérialiseur renvoyait une structure imbriquée { mission: {...}, booking: {...} } alors que
 * le type TS mobile (MissionAssignment) et les deux écrans qui le consomment attendent un
 * payload plat — d'où des champs vides dans l'app et un missionId undefined à la navigation.
 */
class ProviderAssignmentInboxContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_returns_a_flat_payload_with_coordinates(): void
    {
        $provider = $this->makeProvider();
        $client = User::factory()->create(['name' => 'Paul Klee']);
        $booking = $this->makeBooking($client);
        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'planned_start_at' => now()->addDay(),
            'start_lat' => 50.8503,
            'start_lng' => 4.3517,
        ]);
        $this->makeAssignment($mission, $provider);

        $response = $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/assignments/inbox');

        $response->assertOk();
        $response->assertJsonPath('data.0.booking_id', $booking->id);
        $response->assertJsonPath('data.0.client_name', 'Paul Klee');
        $response->assertJsonPath('data.0.address', '12 rue du Test');
        $response->assertJsonPath('data.0.city', 'Bruxelles');
        $response->assertJsonPath('data.0.postal_code', '1000');
        $response->assertJsonPath('data.0.latitude', 50.8503);
        $response->assertJsonPath('data.0.longitude', 4.3517);
        $response->assertJsonStructure([
            'data' => [
                ['id', 'mission_id', 'assignment_status', 'expires_at', 'remaining_seconds',
                 'booking_id', 'service_name', 'client_name', 'address', 'city', 'postal_code',
                 'scheduled_date', 'scheduled_time', 'latitude', 'longitude'],
            ],
        ]);
    }

    public function test_inbox_returns_null_coordinates_when_the_mission_is_not_geocoded(): void
    {
        $provider = $this->makeProvider();
        $booking = $this->makeBooking(User::factory()->create());
        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'planned_start_at' => now()->addDay(),
        ]);
        $this->makeAssignment($mission, $provider);

        $this->actingAs($provider, 'sanctum')
            ->getJson('/api/provider/assignments/inbox')
            ->assertOk()
            ->assertJsonPath('data.0.latitude', null)
            ->assertJsonPath('data.0.longitude', null);
    }

    protected function makeProvider(): User
    {
        $user = User::factory()->employe()->create();

        ProviderProfile::create([
            'user_id' => $user->id,
            'provider_type' => 'individual',
            'status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $user->fresh();
    }

    protected function makeBooking(User $client): Booking
    {
        return Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'address' => '12 rue du Test',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ]);
    }

    protected function makeAssignment(Mission $mission, User $provider): MissionAssignment
    {
        return MissionAssignment::factory()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
            'expires_at' => now()->addMinutes(5),
        ]);
    }
}
```

- [ ] **Step 2 : Lancer le test pour vérifier qu'il échoue**

```
php artisan test tests/Feature/Api/Provider/ProviderAssignmentInboxContractTest.php
```

Attendu : ÉCHEC. `data.0.booking_id` est `null`/absent — le sérialiseur actuel place ces données sous `data.0.booking.*`.

- [ ] **Step 3 : Ajouter la relation client à l'eager-load de `inbox()`**

Dans `inbox()`, remplacer le bloc `->with([...])` par :

```php
            ->with([
                'mission:id,booking_id,planned_start_at,status,start_lat,start_lng,end_lat,end_lng',
                'mission.booking:id,customer_user_id,booking_reference,address,city,postal_code,service_catalog_id,scheduled_date,scheduled_time,booking_mode,priority',
                'mission.booking.serviceCatalog:id,name',
                'mission.booking.customer:id,name',
            ])
```

`customer_user_id` doit figurer dans le `select` du booking, sinon la relation `customer()` ne peut pas être résolue.

- [ ] **Step 4 : Ajouter `serializeForList()`**

Insérer avant `serializeAssignment()` :

```php
    /**
     * Payload de liste — plat, aligné sur le type TS MissionAssignment consommé par
     * DashboardScreen et MissionInboxScreen. Distinct du payload de détail, qui reste imbriqué.
     */
    protected function serializeForList(MissionAssignment $a): array
    {
        $mission = $a->mission;
        $booking = $mission?->booking;

        return [
            'id' => $a->id,
            'mission_id' => $a->mission_id,
            'assignment_status' => $a->assignment_status,
            'assigned_at' => $a->assigned_at?->toIso8601String(),
            'expires_at' => $a->expires_at?->toIso8601String(),
            'remaining_seconds' => $a->expires_at
                ? max(0, (int) now()->diffInSeconds($a->expires_at, false))
                : null,
            'booking_id' => $booking?->id,
            'service_name' => $booking?->serviceCatalog?->name,
            'client_name' => $booking?->customer?->name,
            'address' => $booking?->address,
            'city' => $booking?->city,
            'postal_code' => $booking?->postal_code,
            'scheduled_date' => $booking?->scheduled_date,
            'scheduled_time' => $booking?->scheduled_time,
            'latitude' => $mission?->start_lat !== null ? (float) $mission->start_lat : null,
            'longitude' => $mission?->start_lng !== null ? (float) $mission->start_lng : null,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }
```

- [ ] **Step 5 : Brancher `inbox()` sur le nouveau sérialiseur**

Dans `inbox()`, remplacer :

```php
            'data' => $assignments->map(fn ($a) => $this->serializeAssignment($a))->all(),
```

par :

```php
            'data' => $assignments->map(fn ($a) => $this->serializeForList($a))->all(),
```

Ne pas toucher `show()` : il continue d'appeler `serializeAssignment($assignment, detailed: true)`.

- [ ] **Step 6 : Lancer le nouveau test**

```
php artisan test tests/Feature/Api/Provider/ProviderAssignmentInboxContractTest.php
```

Attendu : 2 tests PASS.

- [ ] **Step 7 : Vérifier que le contrat de `show()` n'a pas bougé**

```
php artisan test tests/Feature/Phase11/ProviderMissionAssignmentControllerTest.php
```

Attendu : PASS sans aucune modification du fichier. Si `data.mission.id` échoue, `show()` a été touché par erreur — revenir en arrière.

- [ ] **Step 8 : Commit**

```bash
git add app/Http/Controllers/Api/ProviderMissionAssignmentController.php tests/Feature/Api/Provider/ProviderAssignmentInboxContractTest.php
git commit -m "fix(api): flatten provider assignment inbox payload to match its mobile contract"
```

---

### Task 2 : Distance dérivée + contrat TS aligné

**Files:**
- Create: `mobile/provider/src/tracking/distance.ts`
- Modify: `mobile/provider/src/missions/types.ts:1-14`, `mobile/provider/src/tracking/index.ts`, `mobile/provider/src/screens/TrackingScreen.tsx:21-39,60-75`
- Test: `mobile/provider/__tests__/tracking/distance.test.ts` (créer)

**Interfaces:**
- Consomme : les clés `latitude`/`longitude` produites par Task 1.
- Produit :
  - `haversineMeters(lat1: number, lon1: number, lat2: number, lon2: number): number`
  - `distanceKmTo(from: { latitude: number; longitude: number } | null, to: { latitude?: number | null; longitude?: number | null }): number | null`
  - `formatDistance(meters: number): string` (`"850 m"` / `"1.2 km"`)
  - le type `MissionAssignment` avec `latitude?: number | null`, `longitude?: number | null`, **sans** `distance_km`.

- [ ] **Step 1 : Écrire le test qui échoue**

Créer `mobile/provider/__tests__/tracking/distance.test.ts` :

```ts
import { haversineMeters, distanceKmTo, formatDistance } from '@/tracking/distance';

describe('distance', () => {
  it('mesure une distance connue (Grand-Place Bruxelles → Atomium ≈ 5.3 km)', () => {
    const meters = haversineMeters(50.8467, 4.3525, 50.8949, 4.3415);
    expect(meters / 1000).toBeCloseTo(5.3, 0);
  });

  it('renvoie 0 pour deux points identiques', () => {
    expect(haversineMeters(50.85, 4.35, 50.85, 4.35)).toBe(0);
  });

  it('dérive la distance en km depuis ma position vers une mission', () => {
    const km = distanceKmTo({ latitude: 50.8467, longitude: 4.3525 }, { latitude: 50.8949, longitude: 4.3415 });
    expect(km).toBeCloseTo(5.3, 0);
  });

  it('renvoie null quand ma position est inconnue', () => {
    expect(distanceKmTo(null, { latitude: 50.89, longitude: 4.34 })).toBeNull();
  });

  it('renvoie null quand la mission n a pas de coordonnées', () => {
    expect(distanceKmTo({ latitude: 50.84, longitude: 4.35 }, { latitude: null, longitude: null })).toBeNull();
    expect(distanceKmTo({ latitude: 50.84, longitude: 4.35 }, {})).toBeNull();
  });

  it('formate en mètres sous 1 km et en km au-delà', () => {
    expect(formatDistance(850)).toBe('850 m');
    expect(formatDistance(1240)).toBe('1.2 km');
  });
});
```

- [ ] **Step 2 : Lancer le test pour vérifier qu'il échoue**

```
cd mobile/provider && npx jest __tests__/tracking/distance.test.ts
```

Attendu : ÉCHEC — `Cannot find module '@/tracking/distance'`.

- [ ] **Step 3 : Créer le module**

`mobile/provider/src/tracking/distance.ts` :

```ts
/**
 * Distances géographiques, partagées par la carte du dashboard et la liste des missions.
 *
 * `distance_km` n'existe pas côté API : la distance est dérivée ici depuis la position GPS
 * vive, ce qui est plus juste qu'une distance calculée serveur depuis une position de
 * présence potentiellement périmée. L'implémentation vient de TrackingScreen, qui la
 * consomme désormais au lieu d'en garder une copie locale.
 */

const EARTH_RADIUS_METERS = 6371000;

export function haversineMeters(lat1: number, lon1: number, lat2: number, lon2: number): number {
  const dLat = ((lat2 - lat1) * Math.PI) / 180;
  const dLon = ((lon2 - lon1) * Math.PI) / 180;
  const a =
    Math.sin(dLat / 2) * Math.sin(dLat / 2) +
    Math.cos((lat1 * Math.PI) / 180) *
      Math.cos((lat2 * Math.PI) / 180) *
      Math.sin(dLon / 2) *
      Math.sin(dLon / 2);
  return EARTH_RADIUS_METERS * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
}

export function distanceKmTo(
  from: { latitude: number; longitude: number } | null,
  to: { latitude?: number | null; longitude?: number | null },
): number | null {
  if (!from) return null;
  if (to.latitude == null || to.longitude == null) return null;
  return haversineMeters(from.latitude, from.longitude, to.latitude, to.longitude) / 1000;
}

export function formatDistance(meters: number): string {
  if (meters >= 1000) return `${(meters / 1000).toFixed(1)} km`;
  return `${Math.round(meters)} m`;
}
```

- [ ] **Step 4 : Lancer le test pour vérifier qu'il passe**

```
cd mobile/provider && npx jest __tests__/tracking/distance.test.ts
```

Attendu : 6 tests PASS.

- [ ] **Step 5 : Exporter depuis le baril `tracking`**

Ajouter à `mobile/provider/src/tracking/index.ts` :

```ts
export { haversineMeters, distanceKmTo, formatDistance } from './distance';
```

- [ ] **Step 6 : Aligner le type `MissionAssignment`**

Dans `mobile/provider/src/missions/types.ts`, remplacer l'interface `MissionAssignment` par :

```ts
export interface MissionAssignment {
  id: number;
  mission_id: number;
  assignment_status: string;
  assigned_at?: string | null;
  expires_at?: string | null;
  remaining_seconds?: number | null;
  booking_id: number;
  service_name: string | null;
  client_name: string | null;
  address: string | null;
  city: string | null;
  postal_code?: string | null;
  scheduled_date: string | null;
  scheduled_time: string | null;
  // Coordonnées de la mission (missions.start_lat/start_lng). Nulles si non géocodée.
  latitude?: number | null;
  longitude?: number | null;
  estimated_duration_minutes?: number;
  created_at: string;
}
```

`distance_km` disparaît volontairement : ce champ n'a jamais été renvoyé par l'API et est désormais dérivé via `distanceKmTo`.

- [ ] **Step 7 : Faire consommer le module partagé à `TrackingScreen`**

Dans `mobile/provider/src/screens/TrackingScreen.tsx` : supprimer la fonction locale `haversineDistance` (lignes 23-39) et la fonction locale `formatDistance`, puis importer les versions partagées :

```ts
import { useGpsWatcher, useSendPing, useStartTracking, haversineMeters, formatDistance } from '@/tracking';
```

Dans `updateDistance`, remplacer l'appel `haversineDistance(...)` par `haversineMeters(...)`. Ne rien changer d'autre au comportement de l'écran.

- [ ] **Step 8 : Vérifier qu'aucune régression n'est introduite**

```
cd mobile/provider && npx jest && npx tsc --noEmit -p tsconfig.json
```

Attendu : suite verte, `tsc` silencieux. Si `Dashboard.interaction.test.tsx` ou `MissionInbox.interaction.test.tsx` échouent sur `distance_km`, **ne pas** les rafistoler ici : c'est l'objet de la Task 3.

- [ ] **Step 9 : Commit**

```bash
git add mobile/provider/src/tracking/distance.ts mobile/provider/src/tracking/index.ts mobile/provider/src/missions/types.ts mobile/provider/src/screens/TrackingScreen.tsx mobile/provider/__tests__/tracking/distance.test.ts
git commit -m "refactor(mobile): share haversine helpers and align MissionAssignment with the real API contract"
```

---

### Task 3 : Réaligner la liste des missions et ses mocks menteurs

**Files:**
- Modify: `mobile/provider/src/screens/MissionInboxScreen.tsx:40-50`
- Modify: `mobile/provider/__tests__/screens/MissionInbox.interaction.test.tsx` (constante `MOCK_ASSIGNMENT`)
- Modify: `mobile/provider/__tests__/screens/Dashboard.interaction.test.tsx` (constante `MOCK_ASSIGNMENT`)

**Interfaces:**
- Consomme : `MissionAssignment` (Task 2), `distanceKmTo` (Task 2).
- Produit : un `MOCK_ASSIGNMENT` conforme au payload réel, réutilisé par les tâches suivantes.

- [ ] **Step 1 : Corriger les mocks pour qu'ils décrivent le payload réel**

Dans **les deux** fichiers de test, remplacer la constante `MOCK_ASSIGNMENT` par :

```ts
const MOCK_ASSIGNMENT = {
  id: 2,
  mission_id: 20,
  assignment_status: 'assigned',
  expires_at: null,
  remaining_seconds: null,
  booking_id: 200,
  service_name: 'Peinture',
  client_name: 'Paul Klee',
  address: '10 Rue des Arts',
  city: 'Gent',
  postal_code: '9000',
  scheduled_date: '2026-06-15',
  scheduled_time: '14:00',
  latitude: 51.0543,
  longitude: 3.7174,
  created_at: '2026-06-14T09:00:00Z',
};
```

- [ ] **Step 2 : Lancer les deux suites pour voir ce que le mensonge cachait**

```
cd mobile/provider && npx jest __tests__/screens/MissionInbox.interaction.test.tsx __tests__/screens/Dashboard.interaction.test.tsx
```

Attendu : ÉCHEC sur les assertions liées à la distance (`1.5 km` n'existe plus). Les assertions sur `service_name`, `client_name`, `city` doivent en revanche **passer** : c'est la preuve que le contrat plat de la Task 1 est bien celui que l'écran attendait.

- [ ] **Step 3 : Dériver la distance dans `MissionInboxScreen`**

En tête du composant, ajouter le suivi de position et la dérivation :

```tsx
import { useState, useCallback } from 'react';
import { useGpsWatcher, distanceKmTo } from '@/tracking';

// ...dans le composant :
const [position, setPosition] = useState<{ latitude: number; longitude: number } | null>(null);
useGpsWatcher(true, useCallback((pos) => setPosition({ latitude: pos.latitude, longitude: pos.longitude }), []));
```

Puis, dans le `renderItem`, remplacer le bloc `{item.distance_km != null && (...)}` par :

```tsx
{(() => {
  const km = distanceKmTo(position, item);
  return km == null ? null : <Badge label={`${km.toFixed(1)} km`} variant="brand" />;
})()}
```

- [ ] **Step 4 : Adapter l'assertion de distance du test de la liste**

Dans `MissionInbox.interaction.test.tsx`, la distance dépend désormais du GPS, indisponible en test : remplacer l'assertion sur `1.5 km` par une assertion sur ce qui est réellement garanti :

```ts
  it('affiche les champs de mission issus du payload réel', async () => {
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [MOCK_ASSIGNMENT] });

    render(<MissionInboxScreen />, { wrapper: makeWrapper() });

    await waitFor(() => {
      expect(screen.getByText('Peinture')).toBeTruthy();
      expect(screen.getByText('Paul Klee')).toBeTruthy();
      expect(screen.getByText(/Gent/)).toBeTruthy();
    });
    // Sans position GPS en test, aucun badge de distance ne doit être rendu.
    expect(screen.queryByText(/km$/)).toBeNull();
  });
```

- [ ] **Step 5 : Lancer les deux suites**

```
cd mobile/provider && npx jest __tests__/screens/MissionInbox.interaction.test.tsx __tests__/screens/Dashboard.interaction.test.tsx
```

Attendu : PASS.

- [ ] **Step 6 : Commit**

```bash
git add mobile/provider/src/screens/MissionInboxScreen.tsx mobile/provider/__tests__/screens/MissionInbox.interaction.test.tsx mobile/provider/__tests__/screens/Dashboard.interaction.test.tsx
git commit -m "fix(mobile): derive mission distance from live GPS and realign test mocks with the real payload"
```

---

### Task 4 : Chargement défensif de `react-native-maps`

**Files:**
- Create: `mobile/provider/src/maps/module.ts`, `mobile/provider/src/maps/index.ts`, `mobile/provider/__mocks__/react-native-maps.tsx`
- Modify: `mobile/provider/package.json` (dépendance), `mobile/provider/jest.config.ts:24-65` (`moduleNameMapper`)
- Test: `mobile/provider/__tests__/maps/module.test.ts` (créer)

**Interfaces:**
- Produit : `loadMapModule(): MapModule | null` où `MapModule = { MapView: React.ComponentType<any>; Marker: React.ComponentType<any>; Callout: React.ComponentType<any> }`. Renvoie `null` si le module natif est absent.

- [ ] **Step 1 : Installer la dépendance à la version épinglée par Expo**

```bash
cd mobile/provider && npx expo install react-native-maps
```

Vérifier que `package.json` indique bien `"react-native-maps": "1.27.2"` — la même version que `mobile/client`. Si `npx expo install` propose autre chose, **s'arrêter** et signaler.

- [ ] **Step 2 : Créer le stub Jest**

`mobile/provider/__mocks__/react-native-maps.tsx` :

```tsx
import React from 'react';
import { View } from 'react-native';

// Stub des composants natifs de react-native-maps pour Jest : ils rendent des View
// porteuses de testID, ce qui permet d'assertion sur les marqueurs sans moteur de carte.
export const Marker = ({ children, testID, ...rest }: any) => (
  <View testID={testID ?? 'map-marker'} {...rest}>{children}</View>
);

export const Callout = ({ children, ...rest }: any) => (
  <View testID="map-callout" {...rest}>{children}</View>
);

export const Polyline = (props: any) => <View testID="map-polyline" {...props} />;

export const PROVIDER_DEFAULT = 'default';
export const PROVIDER_GOOGLE = 'google';

const MapView = ({ children, testID, ...rest }: any) => (
  <View testID={testID ?? 'map-view'} {...rest}>{children}</View>
);

export default MapView;
```

- [ ] **Step 3 : Déclarer le stub dans la config Jest**

Dans `mobile/provider/jest.config.ts`, ajouter à `moduleNameMapper`, à côté des autres modules natifs :

```ts
    // react-native-maps: stub local pour éviter l'init du moteur de carte natif en Jest
    '^react-native-maps$': '<rootDir>/__mocks__/react-native-maps',
```

- [ ] **Step 4 : Écrire le test qui échoue**

Créer `mobile/provider/__tests__/maps/module.test.ts` :

```ts
/**
 * Le dashboard doit survivre à un runtime où le module natif de carte est absent — même motif
 * que shared/src/push/availability.ts pour expo-notifications sous Expo Go Android.
 */
describe('loadMapModule', () => {
  beforeEach(() => jest.resetModules());

  it('renvoie les composants de carte quand le module est disponible', () => {
    const { loadMapModule } = require('@/maps/module');
    const mod = loadMapModule();

    expect(mod).not.toBeNull();
    expect(mod!.MapView).toBeDefined();
    expect(mod!.Marker).toBeDefined();
    expect(mod!.Callout).toBeDefined();
  });

  it('renvoie null quand le module natif est introuvable', () => {
    jest.doMock('react-native-maps', () => {
      throw new Error('native module react-native-maps is not available');
    });

    const { loadMapModule } = require('@/maps/module');
    expect(loadMapModule()).toBeNull();
  });
});
```

- [ ] **Step 5 : Lancer le test pour vérifier qu'il échoue**

```
cd mobile/provider && npx jest __tests__/maps/module.test.ts
```

Attendu : ÉCHEC — `Cannot find module '@/maps/module'`.

- [ ] **Step 6 : Écrire le loader**

`mobile/provider/src/maps/module.ts` :

```ts
import type React from 'react';

export interface MapModule {
  MapView: React.ComponentType<any>;
  Marker: React.ComponentType<any>;
  Callout: React.ComponentType<any>;
}

/**
 * Charge react-native-maps sans jamais laisser échapper d'exception.
 *
 * Même raisonnement que shared/src/push/availability.ts : un module natif absent du runtime
 * (Expo Go dépourvu, build mal configuré) doit dégrader l'écran, pas le faire planter. Le
 * require est délibérément paresseux pour que l'échec soit rattrapable.
 */
export function loadMapModule(): MapModule | null {
  try {
    // eslint-disable-next-line @typescript-eslint/no-var-requires
    const maps = require('react-native-maps');
    const MapView = maps?.default ?? maps?.MapView;

    if (!MapView || !maps?.Marker) return null;

    return { MapView, Marker: maps.Marker, Callout: maps.Callout };
  } catch {
    return null;
  }
}
```

`mobile/provider/src/maps/index.ts` :

```ts
export { loadMapModule } from './module';
export type { MapModule } from './module';
```

- [ ] **Step 7 : Lancer le test pour vérifier qu'il passe**

```
cd mobile/provider && npx jest __tests__/maps/module.test.ts
```

Attendu : 2 tests PASS.

- [ ] **Step 8 : Commit**

```bash
git add mobile/provider/package.json mobile/provider/package-lock.json mobile/provider/jest.config.ts mobile/provider/src/maps mobile/provider/__mocks__/react-native-maps.tsx mobile/provider/__tests__/maps/module.test.ts
git commit -m "feat(mobile): add react-native-maps behind a defensive loader"
```

---

### Task 5 : `useGpsWatcher` expose l'état de permission

**Files:**
- Modify: `mobile/provider/src/tracking/hooks.ts:33-59`
- Test: `mobile/provider/__tests__/tracking/gps-permission.test.ts` (créer)

**Interfaces:**
- Produit : `useGpsWatcher(enabled: boolean, onPosition: (pos) => void): { permission: 'pending' | 'granted' | 'denied' }`. Les appelants existants qui ignorent la valeur de retour restent valides.

- [ ] **Step 1 : Écrire le test qui échoue**

Créer `mobile/provider/__tests__/tracking/gps-permission.test.ts` :

```ts
import { renderHook, waitFor } from '@testing-library/react-native';

const requestForegroundPermissionsAsync = jest.fn();
const watchPositionAsync = jest.fn();

jest.mock('expo-location', () => ({
  requestForegroundPermissionsAsync: (...a: any[]) => requestForegroundPermissionsAsync(...a),
  watchPositionAsync: (...a: any[]) => watchPositionAsync(...a),
  Accuracy: { High: 4, Balanced: 3 },
}));

import { useGpsWatcher } from '@/tracking/hooks';

describe('useGpsWatcher — état de permission', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    watchPositionAsync.mockResolvedValue({ remove: jest.fn() });
  });

  it('rapporte "granted" quand la permission est accordée', async () => {
    requestForegroundPermissionsAsync.mockResolvedValue({ status: 'granted' });

    const { result } = renderHook(() => useGpsWatcher(true, jest.fn()));

    await waitFor(() => expect(result.current.permission).toBe('granted'));
    expect(watchPositionAsync).toHaveBeenCalled();
  });

  it('rapporte "denied" et ne démarre aucun suivi quand elle est refusée', async () => {
    requestForegroundPermissionsAsync.mockResolvedValue({ status: 'denied' });

    const { result } = renderHook(() => useGpsWatcher(true, jest.fn()));

    await waitFor(() => expect(result.current.permission).toBe('denied'));
    expect(watchPositionAsync).not.toHaveBeenCalled();
  });

  it('reste "pending" quand le hook est désactivé', () => {
    const { result } = renderHook(() => useGpsWatcher(false, jest.fn()));

    expect(result.current.permission).toBe('pending');
    expect(requestForegroundPermissionsAsync).not.toHaveBeenCalled();
  });
});
```

- [ ] **Step 2 : Lancer le test pour vérifier qu'il échoue**

```
cd mobile/provider && npx jest __tests__/tracking/gps-permission.test.ts
```

Attendu : ÉCHEC — `result.current` est `undefined` (le hook ne retourne rien aujourd'hui).

- [ ] **Step 3 : Faire retourner l'état de permission**

Dans `mobile/provider/src/tracking/hooks.ts`, remplacer le corps de `useGpsWatcher` par :

```ts
export type GpsPermission = 'pending' | 'granted' | 'denied';

export function useGpsWatcher(
  enabled: boolean,
  onPosition: (pos: { latitude: number; longitude: number; speed: number | null; heading: number | null }) => void,
): { permission: GpsPermission } {
  const subRef = useRef<Location.LocationSubscription | null>(null);
  // Un refus était jusqu'ici avalé par un `return` nu : l'appelant ne pouvait pas l'expliquer
  // à l'utilisateur. La carte du dashboard en a besoin pour justifier l'absence de position.
  const [permission, setPermission] = useState<GpsPermission>('pending');

  useEffect(() => {
    if (!enabled) { subRef.current?.remove(); return; }

    let cancelled = false;

    (async () => {
      const { status } = await Location.requestForegroundPermissionsAsync();
      if (cancelled) return;

      if (status !== 'granted') {
        setPermission('denied');
        return;
      }
      setPermission('granted');

      subRef.current = await Location.watchPositionAsync(
        { accuracy: Location.Accuracy.High, distanceInterval: 10, timeInterval: 5000 },
        (loc) => onPosition({
          latitude: loc.coords.latitude,
          longitude: loc.coords.longitude,
          speed: loc.coords.speed,
          heading: loc.coords.heading,
        }),
      );
    })();

    return () => { cancelled = true; subRef.current?.remove(); };
  }, [enabled]);

  return { permission };
}
```

Ajouter `useState` à l'import `react` en tête de fichier s'il n'y est pas.

- [ ] **Step 4 : Lancer le test pour vérifier qu'il passe**

```
cd mobile/provider && npx jest __tests__/tracking/gps-permission.test.ts
```

Attendu : 3 tests PASS.

- [ ] **Step 5 : Vérifier les consommateurs existants**

```
cd mobile/provider && npx jest && npx tsc --noEmit -p tsconfig.json
```

Attendu : vert. `TrackingScreen` et `MissionFieldScreen` ignorent la valeur de retour, ce qui reste légal.

- [ ] **Step 6 : Commit**

```bash
git add mobile/provider/src/tracking/hooks.ts mobile/provider/__tests__/tracking/gps-permission.test.ts
git commit -m "feat(mobile): expose GPS permission state from useGpsWatcher"
```

---

### Task 6 : `ProviderMap` — rendu, région et replis

**Files:**
- Create: `mobile/provider/src/screens/components/ProviderMap.tsx`
- Test: `mobile/provider/__tests__/screens/ProviderMap.test.tsx` (créer)

**Interfaces:**
- Consomme : `loadMapModule` (Task 4), `useGpsWatcher` (Task 5), `useMissionInbox`, `distanceKmTo` (Task 2).
- Produit : `<ProviderMap />` (aucune prop). testIDs stables : `provider-map`, `map-fallback`, `map-permission-notice`.
- Constante exportée : `FALLBACK_REGION = { latitude: 50.85, longitude: 4.35, latitudeDelta: 2, longitudeDelta: 2 }`.

- [ ] **Step 1 : Écrire le test qui échoue**

Créer `mobile/provider/__tests__/screens/ProviderMap.test.tsx` :

```tsx
import React from 'react';
import { render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MockAdapter from 'axios-mock-adapter';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));

jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true }),
}));

jest.mock('@react-navigation/native', () => ({ useNavigation: () => ({ navigate: jest.fn() }) }));

const mockPermission = { current: 'granted' as 'pending' | 'granted' | 'denied' };
jest.mock('@/tracking', () => ({
  useGpsWatcher: () => ({ permission: mockPermission.current }),
  distanceKmTo: jest.requireActual('../../src/tracking/distance').distanceKmTo,
  formatDistance: jest.requireActual('../../src/tracking/distance').formatDistance,
}));

// `react-native-maps` est déjà redirigé vers __mocks__/react-native-maps par moduleNameMapper :
// il suffit donc de faire renvoyer ce module (ou null) par loadMapModule.
const mockMapModule = { current: true };
jest.mock('@/maps', () => ({
  loadMapModule: () => {
    if (!mockMapModule.current) return null;
    const maps = require('react-native-maps');
    return { MapView: maps.default, Marker: maps.Marker, Callout: maps.Callout };
  },
}));

import { apiClient } from '@/api';
import { ProviderMap } from '@/screens/components/ProviderMap';

const apiMock = new MockAdapter(apiClient);

function makeWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  );
}

beforeEach(() => {
  apiMock.reset();
  mockMapModule.current = true;
  mockPermission.current = 'granted';
  apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
});

describe('ProviderMap', () => {
  it('rend la carte quand le module natif est disponible', async () => {
    render(<ProviderMap />, { wrapper: makeWrapper() });
    await waitFor(() => expect(screen.getByTestId('provider-map')).toBeTruthy());
  });

  it('rend le placeholder texte quand le module natif est absent', async () => {
    mockMapModule.current = false;

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByTestId('map-fallback')).toBeTruthy());
    expect(screen.queryByTestId('provider-map')).toBeNull();
  });

  it('explique une permission GPS refusée', async () => {
    mockPermission.current = 'denied';

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByTestId('map-permission-notice')).toBeTruthy());
  });

  it('annonce l absence de mission en attente', async () => {
    render(<ProviderMap />, { wrapper: makeWrapper() });
    await waitFor(() => expect(screen.getByText(/Aucune mission en attente/)).toBeTruthy());
  });

  it('affiche une erreur récupérable quand l inbox échoue', async () => {
    apiMock.reset();
    apiMock.onGet('/provider/assignments/inbox').reply(500);

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByText(/Réessayer/)).toBeTruthy());
    expect(screen.getByTestId('provider-map')).toBeTruthy();
  });
});
```

- [ ] **Step 2 : Lancer le test pour vérifier qu'il échoue**

```
cd mobile/provider && npx jest __tests__/screens/ProviderMap.test.tsx
```

Attendu : ÉCHEC — `Cannot find module '@/screens/components/ProviderMap'`.

- [ ] **Step 3 : Écrire le composant (rendu, région, replis)**

`mobile/provider/src/screens/components/ProviderMap.tsx` :

```tsx
import React, { useCallback, useMemo, useState } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { Button } from '@/ui';
import { useMissionInbox } from '@/missions';
import { useGpsWatcher } from '@/tracking';
import { loadMapModule } from '@/maps';
import { colors, spacing, typography, radius } from '@/theme';

/** Repli d'échelle pays centré sur Bruxelles, marché principal du projet. */
export const FALLBACK_REGION = {
  latitude: 50.85,
  longitude: 4.35,
  latitudeDelta: 2,
  longitudeDelta: 2,
};

type Position = { latitude: number; longitude: number };

export function ProviderMap() {
  const maps = useMemo(() => loadMapModule(), []);
  const [position, setPosition] = useState<Position | null>(null);
  const { permission } = useGpsWatcher(
    true,
    useCallback((pos) => setPosition({ latitude: pos.latitude, longitude: pos.longitude }), []),
  );
  const { data: assignments, isError, refetch } = useMissionInbox();

  const located = (assignments ?? []).filter(a => a.latitude != null && a.longitude != null);
  const unlocatedCount = (assignments ?? []).length - located.length;

  const region = useMemo(() => {
    if (position) return { ...position, latitudeDelta: 0.08, longitudeDelta: 0.08 };
    const first = located[0];
    if (first) {
      return {
        latitude: first.latitude as number,
        longitude: first.longitude as number,
        latitudeDelta: 0.08,
        longitudeDelta: 0.08,
      };
    }
    return FALLBACK_REGION;
  }, [position, located]);

  if (!maps) {
    return (
      <View style={styles.fallback} testID="map-fallback">
        <Text style={styles.fallbackText}>
          {position
            ? `Position : ${position.latitude.toFixed(5)}, ${position.longitude.toFixed(5)}`
            : 'Carte indisponible sur cet appareil.'}
        </Text>
      </View>
    );
  }

  const { MapView } = maps;

  return (
    <View style={styles.container}>
      <MapView style={styles.map} testID="provider-map" region={region} />

      <View style={styles.overlay} pointerEvents="box-none">
        {permission === 'denied' && (
          <Text style={styles.notice} testID="map-permission-notice">
            Position indisponible — autorise l'accès à ta localisation pour te voir sur la carte.
          </Text>
        )}
        {unlocatedCount > 0 && (
          <Text style={styles.notice}>
            {unlocatedCount} mission{unlocatedCount > 1 ? 's' : ''} sans localisation
          </Text>
        )}
        {!isError && located.length === 0 && (
          <Text style={styles.notice}>Aucune mission en attente</Text>
        )}
        {isError && (
          <View style={styles.errorRow}>
            <Text style={styles.notice}>Missions non chargées.</Text>
            <Button label="Réessayer" onPress={() => void refetch()} size="sm" variant="secondary" />
          </View>
        )}
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  map: { flex: 1 },
  overlay: { position: 'absolute', top: spacing.sm, left: spacing.sm, right: spacing.sm, gap: spacing.xs },
  notice: {
    alignSelf: 'flex-start',
    backgroundColor: '#fff',
    borderRadius: radius.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.xs,
    fontSize: typography.fontSize.xs,
    color: colors.surface[700],
    overflow: 'hidden',
  },
  errorRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  fallback: {
    flex: 1,
    backgroundColor: colors.surface[100],
    alignItems: 'center',
    justifyContent: 'center',
    padding: spacing.md,
  },
  fallbackText: { fontSize: typography.fontSize.xs, color: colors.surface[500], textAlign: 'center' },
});
```

Les tokens utilisés ici sont vérifiés existants : `colors.surface[700]` (`#404040`), `radius.sm`/`radius.pill`, `shadows.xs`.

- [ ] **Step 4 : Lancer le test pour vérifier qu'il passe**

```
cd mobile/provider && npx jest __tests__/screens/ProviderMap.test.tsx
```

Attendu : 5 tests PASS.

- [ ] **Step 5 : Commit**

```bash
git add mobile/provider/src/screens/components/ProviderMap.tsx mobile/provider/__tests__/screens/ProviderMap.test.tsx
git commit -m "feat(mobile): add ProviderMap with defensive fallbacks and visible degradations"
```

---

### Task 7 : `ProviderMap` — marqueurs, callout, navigation

**Files:**
- Modify: `mobile/provider/src/screens/components/ProviderMap.tsx`
- Modify: `mobile/provider/__tests__/screens/ProviderMap.test.tsx`

**Interfaces:**
- Produit : un `Marker` par mission géolocalisée, `testID` = `mission-marker-<booking_id>` ; le tap du callout navigue vers `MissionDetail` avec `{ missionId: booking_id }`.

- [ ] **Step 1 : Ajouter les tests qui échouent**

Ajouter dans `__tests__/screens/ProviderMap.test.tsx` (le `MOCK_ASSIGNMENT` est celui aligné en Task 3) :

```tsx
  const GEOLOCATED = {
    id: 2, mission_id: 20, assignment_status: 'assigned', expires_at: null, remaining_seconds: null,
    booking_id: 200, service_name: 'Peinture', client_name: 'Paul Klee', address: '10 Rue des Arts',
    city: 'Gent', postal_code: '9000', scheduled_date: '2026-06-15', scheduled_time: '14:00',
    latitude: 51.0543, longitude: 3.7174, created_at: '2026-06-14T09:00:00Z',
  };
  const UNLOCATED = { ...GEOLOCATED, id: 3, booking_id: 201, latitude: null, longitude: null };

  it('trace un marqueur par mission géolocalisée', async () => {
    apiMock.reset();
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [GEOLOCATED, UNLOCATED] });

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByTestId('mission-marker-200')).toBeTruthy());
    expect(screen.queryByTestId('mission-marker-201')).toBeNull();
    expect(screen.getByText('1 mission sans localisation')).toBeTruthy();
  });

  it('affiche le service et le client dans le callout', async () => {
    apiMock.reset();
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [GEOLOCATED] });

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByText('Peinture')).toBeTruthy());
    expect(screen.getByText('Paul Klee')).toBeTruthy();
  });
```

- [ ] **Step 2 : Lancer les tests pour vérifier qu'ils échouent**

```
cd mobile/provider && npx jest __tests__/screens/ProviderMap.test.tsx
```

Attendu : ÉCHEC — aucun `mission-marker-200` n'est rendu.

- [ ] **Step 3 : Rendre les marqueurs**

Dans `ProviderMap.tsx`, récupérer aussi `Marker` et `Callout` du module, obtenir `navigation`, et remplir le `MapView` :

```tsx
import { useNavigation } from '@react-navigation/native';
import { distanceKmTo, formatDistance } from '@/tracking';
// ...
  const navigation = useNavigation<any>();
  const { MapView, Marker, Callout } = maps;
// ...
      <MapView style={styles.map} testID="provider-map" region={region}>
        {located.map(a => {
          const km = distanceKmTo(position, a);
          return (
            <Marker
              key={a.booking_id}
              testID={`mission-marker-${a.booking_id}`}
              coordinate={{ latitude: a.latitude as number, longitude: a.longitude as number }}
            >
              <Callout onPress={() => navigation.navigate('MissionDetail', { missionId: a.booking_id })}>
                <View style={styles.callout}>
                  <Text style={styles.calloutService}>{a.service_name}</Text>
                  <Text style={styles.calloutClient}>{a.client_name}</Text>
                  {km != null && (
                    <Text style={styles.calloutDistance}>{formatDistance(km * 1000)}</Text>
                  )}
                </View>
              </Callout>
            </Marker>
          );
        })}
      </MapView>
```

Ajouter les styles correspondants :

```tsx
  callout: { minWidth: 160, padding: spacing.xs },
  calloutService: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.semibold, color: colors.surface[900] },
  calloutClient: { fontSize: typography.fontSize.xs, color: colors.surface[600], marginTop: 2 },
  calloutDistance: { fontSize: typography.fontSize.xs, color: colors.brand[600], marginTop: 2 },
```

- [ ] **Step 4 : Lancer les tests pour vérifier qu'ils passent**

```
cd mobile/provider && npx jest __tests__/screens/ProviderMap.test.tsx
```

Attendu : 7 tests PASS.

- [ ] **Step 5 : Ajouter le test de navigation et le faire passer**

Remplacer le mock de navigation du fichier de test par une version observable, puis ajouter :

```tsx
const mockNavigate = jest.fn();
jest.mock('@react-navigation/native', () => ({ useNavigation: () => ({ navigate: mockNavigate }) }));

  it('navigue vers le détail au tap du callout', async () => {
    apiMock.reset();
    apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [GEOLOCATED] });

    render(<ProviderMap />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByTestId('map-callout'));
    fireEvent.press(screen.getByTestId('map-callout'));

    expect(mockNavigate).toHaveBeenCalledWith('MissionDetail', { missionId: 200 });
  });
```

Importer `fireEvent` depuis `@testing-library/react-native`. Lancer :

```
cd mobile/provider && npx jest __tests__/screens/ProviderMap.test.tsx
```

Attendu : 8 tests PASS.

- [ ] **Step 6 : Commit**

```bash
git add mobile/provider/src/screens/components/ProviderMap.tsx mobile/provider/__tests__/screens/ProviderMap.test.tsx
git commit -m "feat(mobile): plot pending missions as map markers with callout navigation"
```

---

### Task 8 : `PresencePill`

**Files:**
- Create: `mobile/provider/src/screens/components/PresencePill.tsx`
- Test: `mobile/provider/__tests__/screens/PresencePill.test.tsx` (créer)

**Interfaces:**
- Produit : `<PresencePill onPress={() => void} />`, testID `presence-pill`. N'écrit jamais le statut.

- [ ] **Step 1 : Écrire le test qui échoue**

```tsx
import React from 'react';
import { fireEvent, render, screen } from '@testing-library/react-native';

const mockStatus = { current: 'online' };
jest.mock('@/presence', () => ({
  usePresence: () => ({ status: mockStatus.current, error: null, isPending: false, setPresenceStatus: jest.fn(), goOnline: jest.fn() }),
}));

import { PresencePill } from '@/screens/components/PresencePill';

describe('PresencePill', () => {
  it('affiche le libellé du statut courant', () => {
    mockStatus.current = 'on_break';
    render(<PresencePill onPress={jest.fn()} />);
    expect(screen.getByText('En pause')).toBeTruthy();
  });

  it('appelle onPress au tap', () => {
    const onPress = jest.fn();
    mockStatus.current = 'online';
    render(<PresencePill onPress={onPress} />);
    fireEvent.press(screen.getByTestId('presence-pill'));
    expect(onPress).toHaveBeenCalled();
  });
});
```

- [ ] **Step 2 : Vérifier l'échec**

```
cd mobile/provider && npx jest __tests__/screens/PresencePill.test.tsx
```

Attendu : ÉCHEC — module introuvable.

- [ ] **Step 3 : Écrire le composant**

```tsx
import React from 'react';
import { Text, TouchableOpacity, StyleSheet } from 'react-native';
import { PulseDot } from '@/ui';
import { usePresence } from '@/presence';
import type { PresenceStatus } from '@/presence/types';
import { colors, spacing, typography, radius, shadows } from '@/theme';

const labels: Record<PresenceStatus, string> = {
  online: 'En ligne',
  busy: 'Occupé',
  on_break: 'En pause',
  offline: 'Hors ligne',
};

const variants: Record<PresenceStatus, 'success' | 'urgent' | 'primary'> = {
  online: 'success',
  busy: 'urgent',
  on_break: 'primary',
  offline: 'primary',
};

/**
 * Affichage seul : la pilule n'écrit jamais le statut. Le seul chemin d'écriture reste
 * PresenceToggle, dans le sheet — un point d'écriture unique, donc pas d'état divergent.
 */
export function PresencePill({ onPress }: { onPress: () => void }) {
  const { status } = usePresence();

  return (
    <TouchableOpacity style={styles.pill} onPress={onPress} testID="presence-pill" accessibilityRole="button">
      {status !== 'offline' && <PulseDot variant={variants[status]} />}
      <Text style={styles.label}>{labels[status]}</Text>
    </TouchableOpacity>
  );
}

const styles = StyleSheet.create({
  pill: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    alignSelf: 'center',
    backgroundColor: '#fff',
    borderRadius: radius.pill,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    ...shadows.xs,
  },
  label: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.semibold, color: colors.surface[900] },
});
```

- [ ] **Step 4 : Vérifier le passage**

```
cd mobile/provider && npx jest __tests__/screens/PresencePill.test.tsx
```

Attendu : 2 tests PASS.

- [ ] **Step 5 : Commit**

```bash
git add mobile/provider/src/screens/components/PresencePill.tsx mobile/provider/__tests__/screens/PresencePill.test.tsx
git commit -m "feat(mobile): add read-only PresencePill for the dashboard map overlay"
```

---

### Task 9 : `DashboardActionsSheet` + racine gesture-handler

**Files:**
- Create: `mobile/provider/src/screens/components/DashboardActionsSheet.tsx`
- Modify: `mobile/provider/App.tsx:86-97`
- Test: `mobile/provider/__tests__/screens/DashboardActionsSheet.test.tsx` (créer)

**Interfaces:**
- Produit : `<DashboardActionsSheet ref={sheetRef} />`, où `sheetRef` est une ref vers le `BottomSheet` gorhom (`expand()` / `close()`). Contient `PresenceToggle`, les 2 `KPICard`, les 4 accès rapides et « Voir toutes les missions ».

- [ ] **Step 1 : Écrire le test qui échoue**

```tsx
import React from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react-native';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import MockAdapter from 'axios-mock-adapter';

jest.mock('expo-secure-store', () => ({
  getItemAsync: jest.fn().mockResolvedValue(null),
  setItemAsync: jest.fn().mockResolvedValue(undefined),
  deleteItemAsync: jest.fn().mockResolvedValue(undefined),
}));
jest.mock('@react-native-community/netinfo', () => ({
  addEventListener: jest.fn(() => () => undefined),
  fetch: jest.fn().mockResolvedValue({ isConnected: true }),
}));

const mockNavigate = jest.fn();
jest.mock('@react-navigation/native', () => ({ useNavigation: () => ({ navigate: mockNavigate }) }));

// Le sheet gorhom est remplacé par un conteneur simple : on teste le contenu et le câblage,
// pas l'animation native.
jest.mock('@/ui', () => {
  const actual = jest.requireActual('@/ui');
  const { View } = require('react-native');
  const React = require('react');
  return { ...actual, BottomSheet: React.forwardRef(({ children }: any, _ref: any) => <View>{children}</View>) };
});

import { apiClient } from '@/api';
import { DashboardActionsSheet } from '@/screens/components/DashboardActionsSheet';

const apiMock = new MockAdapter(apiClient);

function makeWrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return ({ children }: { children: React.ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  );
}

beforeEach(() => {
  apiMock.reset();
  mockNavigate.mockClear();
  apiMock.onGet('/provider/assignments/inbox').reply(200, { data: [] });
  apiMock.onGet('/provider/wallet/balance').reply(200, { available: 150, pending: 0, currency: 'EUR' });
  apiMock.onGet('/provider/presence-v2').reply(200, { data: { status: 'offline' } });
});

describe('DashboardActionsSheet', () => {
  it('contient les quatre accès rapides et les boutons de présence', async () => {
    render(<DashboardActionsSheet />, { wrapper: makeWrapper() });

    await waitFor(() => expect(screen.getByText('Disponibilités')).toBeTruthy());
    expect(screen.getByText('Badges')).toBeTruthy();
    expect(screen.getByText('Revenus')).toBeTruthy();
    expect(screen.getByText('Messagerie')).toBeTruthy();
    expect(screen.getByText('Occupé')).toBeTruthy();
  });

  it('navigue vers l onglet Revenus', async () => {
    render(<DashboardActionsSheet />, { wrapper: makeWrapper() });

    await waitFor(() => screen.getByText('Revenus'));
    fireEvent.press(screen.getByText('Revenus'));

    expect(mockNavigate).toHaveBeenCalledWith('MainTabs', { screen: 'Earnings' });
  });

  it('affiche les KPIs', async () => {
    render(<DashboardActionsSheet />, { wrapper: makeWrapper() });
    await waitFor(() => expect(screen.getByText('Missions en attente')).toBeTruthy());
    expect(screen.getByText('Solde disponible')).toBeTruthy();
  });
});
```

- [ ] **Step 2 : Vérifier l'échec**

```
cd mobile/provider && npx jest __tests__/screens/DashboardActionsSheet.test.tsx
```

Attendu : ÉCHEC — module introuvable.

- [ ] **Step 3 : Écrire le composant**

```tsx
import React, { forwardRef } from 'react';
import { View, Text, TouchableOpacity, StyleSheet } from 'react-native';
import type GorhomBottomSheet from '@gorhom/bottom-sheet';
import { useNavigation } from '@react-navigation/native';
import { BottomSheet, KPICard, Skeleton, Button, Divider } from '@/ui';
import { PresenceToggle } from '@/screens/components/PresenceToggle';
import { useMissionInbox } from '@/missions';
import { useWalletBalance } from '@/earnings';
import { colors, spacing, typography, radius, shadows } from '@/theme';

type QuickAction = { label: string; screen: string; params?: object };

const QUICK_ACTIONS: QuickAction[] = [
  { label: 'Disponibilités', screen: 'Availability' },
  { label: 'Badges', screen: 'Badges' },
  // L'onglet Revenus vit dans MainTabs : sans le param imbriqué, le tap ne fait rien.
  { label: 'Revenus', screen: 'MainTabs', params: { screen: 'Earnings' } },
  { label: 'Messagerie', screen: 'ProviderChatList' },
];

export const DashboardActionsSheet = forwardRef<GorhomBottomSheet>((_props, ref) => {
  const navigation = useNavigation<any>();
  const { data: assignments, isLoading: loadingMissions } = useMissionInbox();
  const { data: wallet, isLoading: loadingWallet } = useWalletBalance();

  const go = (action: QuickAction) => {
    if (action.params) navigation.navigate(action.screen, action.params);
    else navigation.navigate(action.screen);
  };

  return (
    <BottomSheet ref={ref} snapPoints={['60%', '90%']}>
      <Text style={styles.sectionTitle} accessibilityRole="header">Statut</Text>
      <PresenceToggle />

      <Divider />

      <View style={styles.kpiRow}>
        {loadingMissions || loadingWallet ? (
          <>
            <Skeleton width="48%" height={80} />
            <Skeleton width="48%" height={80} />
          </>
        ) : (
          <>
            <KPICard
              title="Missions en attente"
              value={assignments?.length ?? 0}
              tone={(assignments?.length ?? 0) > 0 ? 'warning' : 'neutral'}
            />
            <KPICard
              title="Solde disponible"
              value={wallet && wallet.available != null ? `${wallet.available.toFixed(0)} ${wallet.currency ?? ''}`.trim() : '—'}
              tone="success"
            />
          </>
        )}
      </View>

      <Text style={styles.sectionTitle} accessibilityRole="header">Accès rapide</Text>
      <View style={styles.quickActions}>
        {QUICK_ACTIONS.map(action => (
          <TouchableOpacity key={action.label} style={styles.quickCard} onPress={() => go(action)}>
            <Text style={styles.quickLabel}>{action.label}</Text>
          </TouchableOpacity>
        ))}
      </View>

      <Button
        label="Voir toutes les missions"
        onPress={() => navigation.navigate('MainTabs', { screen: 'Missions' })}
        variant="secondary"
        fullWidth
      />
    </BottomSheet>
  );
});

DashboardActionsSheet.displayName = 'DashboardActionsSheet';

const styles = StyleSheet.create({
  sectionTitle: {
    fontSize: typography.fontSize.lg,
    fontWeight: typography.fontWeight.semibold,
    color: colors.surface[800],
    marginTop: spacing.md,
    marginBottom: spacing.sm,
  },
  kpiRow: { flexDirection: 'row', gap: spacing.sm, marginVertical: spacing.md },
  quickActions: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm, marginBottom: spacing.md },
  quickCard: { width: '48%', backgroundColor: '#fff', borderRadius: radius.md, padding: spacing.md, ...shadows.xs, alignItems: 'center' },
  quickLabel: { fontSize: typography.fontSize.sm, fontWeight: typography.fontWeight.medium, color: colors.brand[600] },
});
```

- [ ] **Step 4 : Vérifier le passage**

```
cd mobile/provider && npx jest __tests__/screens/DashboardActionsSheet.test.tsx
```

Attendu : 3 tests PASS.

- [ ] **Step 5 : Ajouter `GestureHandlerRootView` à la racine**

Dans `mobile/provider/App.tsx`, importer :

```tsx
import { GestureHandlerRootView } from 'react-native-gesture-handler';
```

et envelopper le retour de `App()` :

```tsx
  return (
    <GestureHandlerRootView style={{ flex: 1 }}>
      <ErrorBoundary>
        <QueryClientProvider client={queryClient}>
          <AuthProvider>
            <RealtimeProvider>
              <AppInner />
            </RealtimeProvider>
          </AuthProvider>
        </QueryClientProvider>
      </ErrorBoundary>
    </GestureHandlerRootView>
  );
```

Sans ce wrapper, un sheet gorhom s'affiche mais ne répond à aucun geste — échec silencieux.

- [ ] **Step 6 : Vérifier que la racine n'a rien cassé**

```
cd mobile/provider && npx jest __tests__/App.test.tsx
```

Attendu : PASS.

- [ ] **Step 7 : Commit**

```bash
git add mobile/provider/src/screens/components/DashboardActionsSheet.tsx mobile/provider/App.tsx mobile/provider/__tests__/screens/DashboardActionsSheet.test.tsx
git commit -m "feat(mobile): add dashboard actions bottom sheet and wire gesture-handler root"
```

---

### Task 10 : Assembler le `DashboardScreen`

**Files:**
- Modify: `mobile/provider/src/screens/DashboardScreen.tsx` (réécriture)
- Modify: `mobile/provider/__tests__/screens/Dashboard.interaction.test.tsx` (migration)

**Interfaces:**
- Consomme : `ProviderMap` (Tasks 6-7), `PresencePill` (Task 8), `DashboardActionsSheet` (Task 9).

- [ ] **Step 1 : Migrer le test d'interaction du dashboard**

Les cas « Disponibilités », « Badges », « Messagerie », « Revenus » et « Voir toutes les missions » testent désormais le contenu du sheet : **les supprimer** de `Dashboard.interaction.test.tsx` (ils sont couverts par `DashboardActionsSheet.test.tsx`), et remplacer le fichier de test par les cas propres au nouvel écran :

```tsx
  it('rend la carte sur la page', async () => {
    render(<DashboardScreen />, { wrapper: makeWrapper() });
    await waitFor(() => expect(screen.getByTestId('provider-map-slot')).toBeTruthy());
  });

  it('ne rend plus la grille d accès rapides sur la page', async () => {
    render(<DashboardScreen />, { wrapper: makeWrapper() });
    await waitFor(() => screen.getByTestId('provider-map-slot'));
    expect(screen.queryByText('Disponibilités')).toBeNull();
    expect(screen.queryByText('Badges')).toBeNull();
  });

  it('ouvre le sheet au tap sur Actions', async () => {
    render(<DashboardScreen />, { wrapper: makeWrapper() });
    await waitFor(() => screen.getByLabelText('Actions'));
    fireEvent.press(screen.getByLabelText('Actions'));
    expect(mockExpand).toHaveBeenCalled();
  });
```

Ajouter en tête du fichier les stubs nécessaires :

```tsx
const mockExpand = jest.fn();
jest.mock('@/screens/components/ProviderMap', () => {
  const { View } = require('react-native');
  return { ProviderMap: () => <View testID="provider-map-slot" /> };
});
jest.mock('@/screens/components/DashboardActionsSheet', () => {
  const React = require('react');
  const { View } = require('react-native');
  return {
    DashboardActionsSheet: React.forwardRef((_p: any, ref: any) => {
      if (ref) ref.current = { expand: mockExpand, close: jest.fn() };
      return <View testID="actions-sheet" />;
    }),
  };
});
```

Conserver le test « renders greeting with provider first name ».

- [ ] **Step 2 : Vérifier l'échec**

```
cd mobile/provider && npx jest __tests__/screens/Dashboard.interaction.test.tsx
```

Attendu : ÉCHEC — `provider-map-slot` absent, et « Disponibilités » encore présent sur la page.

- [ ] **Step 3 : Réécrire l'écran**

```tsx
import React, { useCallback, useRef } from 'react';
import { View, Text, StyleSheet } from 'react-native';
import type GorhomBottomSheet from '@gorhom/bottom-sheet';
import { Screen, Avatar, Button } from '@/ui';
import { useAuth } from '@/auth';
import { ProviderMap } from '@/screens/components/ProviderMap';
import { PresencePill } from '@/screens/components/PresencePill';
import { DashboardActionsSheet } from '@/screens/components/DashboardActionsSheet';
import { colors, spacing, typography } from '@/theme';

export function DashboardScreen() {
  const { user } = useAuth();
  const sheetRef = useRef<GorhomBottomSheet>(null);

  const openSheet = useCallback(() => sheetRef.current?.expand(), []);

  return (
    <Screen testID="dashboard-screen">
      <View style={styles.hero}>
        <View style={styles.heroLeft}>
          <Text style={styles.greeting}>Bonjour{user?.name ? `, ${user.name.split(' ')[0]}` : ''}</Text>
          <Text style={styles.role}>{user?.email}</Text>
        </View>
        <Avatar name={user?.name ?? '?'} size={48} />
      </View>

      <View style={styles.mapWrap}>
        <ProviderMap />
      </View>

      <View style={styles.floating} pointerEvents="box-none">
        <PresencePill onPress={openSheet} />
        <Button label="Actions" onPress={openSheet} fullWidth size="lg" />
      </View>

      <DashboardActionsSheet ref={sheetRef} />
    </Screen>
  );
}

const styles = StyleSheet.create({
  hero: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center', marginVertical: spacing.md },
  heroLeft: { flex: 1 },
  greeting: { fontSize: typography.fontSize['2xl'], fontWeight: typography.fontWeight.bold, color: colors.surface[900] },
  role: { fontSize: typography.fontSize.sm, color: colors.surface[500], marginTop: 2 },
  mapWrap: { flex: 1, borderRadius: 12, overflow: 'hidden' },
  floating: { position: 'absolute', left: spacing.md, right: spacing.md, bottom: spacing.lg, gap: spacing.sm },
});
```

`<Screen>` est utilisé **sans** `scroll` : la carte occupe l'espace restant.

- [ ] **Step 4 : Vérifier le passage**

```
cd mobile/provider && npx jest __tests__/screens/Dashboard.interaction.test.tsx
```

Attendu : PASS.

- [ ] **Step 5 : Commit**

```bash
git add mobile/provider/src/screens/DashboardScreen.tsx mobile/provider/__tests__/screens/Dashboard.interaction.test.tsx
git commit -m "feat(mobile): make the provider dashboard map-first with an actions sheet"
```

---

### Task 11 : Seeder de dév et vérification finale

**Files:**
- Create: `database/seeders/DevProviderMissionSeeder.php`
- Test: exécution manuelle + suites complètes

**Interfaces:**
- Produit : `php artisan db:seed --class=DevProviderMissionSeeder` crée un booking géocodé, sa mission et une assignation `assigned` non expirée pour l'e-mail fourni.

- [ ] **Step 1 : Écrire le seeder**

```php
<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\MissionAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Fixture de développement : une mission géolocalisée assignée à un prestataire, afin que la
 * carte du dashboard ait quelque chose à afficher. La base de dév n'a aucune mission.
 *
 * Usage : php artisan db:seed --class=DevProviderMissionSeeder
 *         PROVIDER_EMAIL=autre@exemple.test php artisan db:seed --class=DevProviderMissionSeeder
 */
class DevProviderMissionSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('PROVIDER_EMAIL', 'test@test.com');
        $provider = User::where('email', $email)->first();

        if (! $provider) {
            $this->command->error("Prestataire introuvable : {$email}");

            return;
        }

        $client = User::where('role', 'client')->first() ?? User::factory()->create(['name' => 'Client Démo']);

        $booking = Booking::create([
            'booking_reference' => 'CUX-'.strtoupper(Str::random(6)),
            'customer_user_id' => $client->id,
            'client_id' => $client->id,
            'address' => '10 Rue des Arts',
            'city' => 'Bruxelles',
            'postal_code' => '1000',
            'scheduled_date' => now()->addDay()->toDateString(),
            'scheduled_time' => '10:00:00',
            'status' => 'confirme',
            'currency' => 'EUR',
            'priority' => 'normal',
            'booking_mode' => 'scheduled',
        ]);

        $mission = Mission::create([
            'booking_id' => $booking->id,
            'status' => 'planned',
            'planned_start_at' => now()->addDay(),
            'start_lat' => 50.8466,
            'start_lng' => 4.3528,
        ]);

        MissionAssignment::factory()->create([
            'mission_id' => $mission->id,
            'user_id' => $provider->id,
            'role_on_mission' => 'lead',
            'assignment_status' => 'assigned',
            'assigned_at' => now(),
            'expires_at' => now()->addHours(2),
        ]);

        $this->command->info("Mission #{$mission->id} assignée à {$email} (Bruxelles, 50.8466 / 4.3528).");
    }
}
```

- [ ] **Step 2 : Exécuter le seeder**

```
php artisan db:seed --class=DevProviderMissionSeeder
```

Attendu : le message de confirmation avec l'id de mission.

- [ ] **Step 3 : Vérifier le payload réel de l'API**

Le serveur de dév doit écouter sur le LAN (`php artisan serve --host=0.0.0.0 --port=8000`). Créer un token pour le prestataire puis appeler l'inbox :

```
php artisan tinker --execute="echo App\Models\User::where('email','test@test.com')->first()->createToken('plan-check')->plainTextToken;"
```

puis, avec ce token :

```
curl -s -H "Accept: application/json" -H "Authorization: Bearer <TOKEN>" http://192.168.1.18:8000/api/provider/assignments/inbox
```

Attendu : `data[0]` contient `service_name`, `client_name`, `latitude: 50.8466`, `longitude: 4.3528` **à la racine**. C'est la vérification contre l'API réelle, pas contre un mock. Supprimer ensuite le token :

```
php artisan tinker --execute="echo Laravel\Sanctum\PersonalAccessToken::where('name','plan-check')->delete();"
```

- [ ] **Step 4 : Suites complètes et typecheck**

```
php artisan test --filter="Assignment|Presence"
cd mobile/provider && npx jest && npx tsc --noEmit -p tsconfig.json
cd ../client && npx jest && npx tsc --noEmit -p tsconfig.json
```

Attendu : tout vert côté provider et backend. Côté client, la seule erreur `tsc` tolérée est la préexistante `src/screens/DisputesScreen.tsx:139` (`radius.full`) ; **aucun** fichier de `mobile/client/` ne doit apparaître dans `git status`.

- [ ] **Step 5 : Vérifier sur l'appareil**

Recharger l'app provider (`r` dans Metro), ouvrir le dashboard. Attendu : la carte s'affiche, un marqueur à Bruxelles, le callout donne le service et le client, le bouton `Actions` ouvre le sheet et le sheet se ferme par glissement vers le bas.

Si la carte est grise : c'est la clé Google Maps manquante, connue et hors périmètre (voir la section « Hors périmètre » de la spec).

- [ ] **Step 6 : Commit**

```bash
git add database/seeders/DevProviderMissionSeeder.php
git commit -m "chore(dev): add seeder for a geolocated provider mission"
```

---

## Auto-revue du plan

**Couverture de la spec.** Chaque section de la spec est portée par au moins une tâche : contrat backend → T1 ; distance dérivée + type → T2 ; écrans réalignés et mocks réparés → T3 ; moteur de carte + garde défensif → T4 ; permission GPS → T5 ; `ProviderMap` et ses dégradations → T6-T7 ; `PresencePill` → T8 ; modal + racine gesture-handler → T9 ; assemblage → T10 ; seeder de dév + vérification réelle → T11. Le hors-périmètre (clé Google Maps, polylignes, clustering, app client) n'a volontairement aucune tâche.

**Cohérence des types.** Les clés du payload de T1 (`booking_id`, `service_name`, `client_name`, `latitude`, `longitude`) sont celles du type de T2, du mock de T3, des marqueurs de T7 et du callout de T7. `distanceKmTo(from, to)` garde la même signature en T2, T3 et T7. `loadMapModule()` renvoie `{ MapView, Marker, Callout }` en T4, et T6/T7 ne déstructurent rien d'autre. `useGpsWatcher` renvoie `{ permission }` en T5, consommé sous ce nom en T6.

**Points de vigilance signalés dans les étapes** plutôt que découverts à l'exécution : `customer_user_id` obligatoire dans le `select` du booking (T1 step 3) ; ne pas rafistoler les mocks en T2 step 8, c'est l'objet de T3 ; `GestureHandlerRootView` sans lequel le sheet échoue en silence (T9 step 5) ; `npx expo install` doit donner exactement `1.27.2`, sinon s'arrêter (T4 step 1).

**Tokens de thème vérifiés** avant rédaction : `colors.surface[700]`, `colors.brand[600]`, `radius.sm`, `radius.md`, `radius.pill`, `shadows.xs` existent tous dans `shared/src/theme`. Aucun token inventé dans le plan.
