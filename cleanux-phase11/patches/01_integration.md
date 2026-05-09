# Phase 11 — Patches manuels d'intégration

## 1. routes/api.php — ajouter les nouvelles routes

Remplace le contenu actuel de `routes/api.php` par :

```php
<?php

use App\Http\Controllers\Api\EmployeeMissionTrackingController;
use App\Http\Controllers\Api\ProviderMissionAssignmentController;
use App\Http\Controllers\Api\ProviderPresenceController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {

    // Mission tracking (existant Phase 0)
    Route::post('/missions/{mission}/tracking/start', [EmployeeMissionTrackingController::class, 'start']);
    Route::post('/mission-tracking-sessions/{session}/push', [EmployeeMissionTrackingController::class, 'push']);
    Route::post('/mission-tracking-sessions/{session}/stop', [EmployeeMissionTrackingController::class, 'stop']);

    // Phase 11 — Provider presence
    Route::prefix('provider/presence')->group(function () {
        Route::post('/online',    [ProviderPresenceController::class, 'online']);
        Route::post('/offline',   [ProviderPresenceController::class, 'offline']);
        Route::post('/heartbeat', [ProviderPresenceController::class, 'heartbeat']);
        Route::get('/me',         [ProviderPresenceController::class, 'me']);
    });

    // Phase 11 — Mission accept/decline
    Route::prefix('provider/assignments')->group(function () {
        Route::get('/inbox',           [ProviderMissionAssignmentController::class, 'inbox']);
        Route::get('/{assignment}',    [ProviderMissionAssignmentController::class, 'show']);
        Route::post('/{assignment}/accept',  [ProviderMissionAssignmentController::class, 'accept']);
        Route::post('/{assignment}/decline', [ProviderMissionAssignmentController::class, 'decline']);
    });
});
```

## 2. routes/web.php (ou routes/provider.php si tu en as un) — page web d'offre

Ajouter :

```php
use App\Livewire\Provider\MissionOfferPage;

Route::middleware(['auth'])->group(function () {
    Route::get('/provider/missions/{assignment}/offer', MissionOfferPage::class)
        ->name('provider.missions.offer');
});
```

## 3. app/Console/Kernel.php — scheduler pour cleanup stale presence

Dans la méthode `schedule()`, ajouter :

```php
protected function schedule(Schedule $schedule): void
{
    // ... tes autres tâches ...

    // Phase 11 — Auto-offline les prestataires fantômes (toutes les minutes)
    $schedule->command('presence:cleanup')->everyMinute()->withoutOverlapping();
}
```

S'assurer que cron tourne sur le serveur :

```cron
* * * * * cd /chemin/vers/cleanux && php artisan schedule:run >> /dev/null 2>&1
```

## 4. config/queue.php — vérifier la queue

Le job `EscalateMissionAssignmentJob` est planifié avec `->delay(15s)`. Pour ça
il faut une queue qui supporte les delays :

```ini
# .env
QUEUE_CONNECTION=database  # ou redis (préférable en prod)
```

Si pas encore créé, lancer :

```bash
php artisan queue:table
php artisan migrate
```

Et avoir un worker tournant :

```bash
php artisan queue:work --queue=default
# OU mieux : Supervisor en prod
```

## 5. app/Models/User.php — ajouter la relation providerProfile (si absente)

Vérifier :

```bash
grep "function providerProfile" app/Models/User.php
```

Si absent, ajouter dans `User.php` :

```php
public function providerProfile()
{
    return $this->hasOne(\App\Models\ProviderProfile::class);
}
```

## 6. routes/channels.php — autorisation du channel presence

Pour que le channel `providers.presence` (sur lequel `ProviderPresenceChanged`
est broadcasté) soit accessible aux admins :

```php
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('providers.presence', function ($user) {
    // Seuls les admins / dispatchers peuvent écouter
    return $user && (
        $user->role === 'admin' ||
        $user->role === 'dispatcher' ||
        method_exists($user, 'isPlatformAdmin') && $user->isPlatformAdmin()
    );
});
```

(Adapter selon ton système de rôles).

## 7. Composant UI — placer le toggle online/offline

Sur la page dashboard prestataire (`resources/views/...`), ajouter :

```blade
<livewire:provider.provider-presence-toggle />
```

## 8. Ré-utilisation : déclencher le dispatch au moment opportun

Le `MissionDispatchService::dispatchToNextProvider()` doit être appelé quand
une mission a besoin d'être assignée. Selon ton flow actuel :

**Cas A** : Mission déjà créée par `MissionFromRendezVousSyncService` →
ajouter à la fin de la création :

```php
// Dans MissionFromRendezVousSyncService ou un Observer
if ($mission->status === 'planned' && ! $mission->assignments()->exists()) {
    app(\App\Services\Dispatch\MissionDispatchService::class)
        ->dispatchToNextProvider($mission);
}
```

**Cas B** : Bookings ASAP → dispatcher dès création :

```php
// Dans CreateBookingAction.php après création de la mission
if ($booking->booking_mode === 'asap') {
    app(\App\Services\Dispatch\MissionDispatchService::class)
        ->dispatchToNextProvider($mission);
}
```

## 9. Tests

```bash
php artisan migrate
php artisan test --filter=Phase11Test
```

19 tests doivent passer :
- 7 sur ProviderPresenceService (online, offline, heartbeat, stale cleanup, near, error cases)
- 8 sur MissionDispatchService (createOffer, accept, accept-cancels-others, decline, expire-already-accepted, expired-offer-rejected, etc.)
- 4 sur API endpoints

## 10. Logs et debug

Le service log :
- `MissionDispatchService: offre créée` à chaque createOffer
- `MissionDispatchService: assignment accepté` / `refusé` / `expiré`
- `MissionDispatchService: aucun candidat trouvé`
- `Provider auto-offline (stale heartbeat)`

Suivi en dev :

```bash
tail -f storage/logs/laravel.log | grep -E "MissionDispatch|Provider auto"
```

## 11. Démo runtime end-to-end

1. **Prestataire passe online** :
   - Aller sur dashboard prestataire
   - Cliquer "🟢 Passer en ligne"
   - Autoriser géolocalisation
   - Bullet vert + animation ping

2. **Créer une mission ASAP** côté client :
   - Bouton "Réserver maintenant"
   - Mission créée → dispatch automatique

3. **Notification reçue** côté prestataire :
   - Push système si Phase 8 appliquée
   - Sinon : recharger /provider/dashboard

4. **Page offre s'ouvre** : timer countdown 15s, boutons accepter/refuser

5. **Si accept** : mission devient "assigned", tracking peut commencer

6. **Si decline ou timeout** : nouvelle offre dispatchée au prestataire suivant

## 12. Limites connues

- **Heartbeat 30s** = consommation batterie/data. Pour PWA mobile, à terme
  prévoir un heartbeat adaptatif (60s en background, 15s en foreground).
- **Géolocation web** : moins fiable que GPS natif. Sur mobile, viser une vraie
  app native (Phase 12+) pour le vrai-temps prestataire.
- **15s de timeout** : peut être trop court si le prestataire est en train de
  conduire. À paramétrer dans `MissionDispatchService::RESPONSE_TIMEOUT_SECONDS`
  ou en config.
- **Pas de cap d'escalation** : si tous les prestataires refusent, la mission
  reste en `planned`. Phase 11.1 pourrait ajouter un fallback "personne dispo →
  notif admin pour intervention manuelle".
- **AiDispatchService inchangé** : il continue à scorer **tous** les
  prestataires, pas seulement les `is_online`. Pour vraiment Uber-style, il
  faudrait modifier `AiDispatchService::rankEmployees()` pour filtrer
  `where('is_online', true)`. Voir patch optionnel ci-dessous.

## 13. Patch optionnel — filtrer AiDispatchService par is_online

Pour ne dispatcher qu'aux prestataires en ligne (vrai mode Uber), modifier
`app/Services/Dispatch/AiDispatchService.php` :

```php
// Au début de rankEmployees(), après le check de zone :
return $this->availability
    ->sortedEligibleEmployeesForZone((int) $rdv->service_zone_id)
    ->filter(function (User $employee) use ($rdv, $duration) {
        // Phase 11 — Filtre online si mode ASAP ou si feature flag activé
        if ($rdv->booking_mode === 'asap') {
            $profile = $employee->providerProfile;
            if (! $profile || ! $profile->is_online) {
                return false;
            }
        }
        return $this->availability->employeeIsAvailableForSlot(
            $employee->id,
            // ... rest of existing code
        );
    })
    // ...
```

Cette modif est **optionnelle** : selon ton modèle business, tu peux préférer
dispatcher aussi aux prestataires offline (pour qu'ils voient l'offre quand ils
re-ouvrent l'app). À toi de choisir.
