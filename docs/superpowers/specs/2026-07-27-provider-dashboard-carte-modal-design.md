# Dashboard provider carte-first + modal d'actions — Design Spec

**Date :** 2026-07-27
**Statut :** Design approuvé (avant plan d'implémentation)
**Branche prévue :** `feat/provider-dashboard-map` (off `main`)
**Périmètre :** `mobile/provider` + 1 sérialiseur backend. L'app client n'est pas touchée.

---

## Contexte & objectif

Le `DashboardScreen` de l'app prestataire est aujourd'hui une liste scrollable de blocs : hero, boutons
de présence, 2 KPIs, aperçu de 2 missions, et une grille de 4 accès rapides. Aucune carte : l'app
provider n'embarque **aucune bibliothèque de cartographie**, et le seul endroit qui en aurait besoin
(`TrackingScreen`) affiche un rectangle gris avec les coordonnées en texte.

Objectif : faire du dashboard un écran **carte-first** de type « application chauffeur » — la carte est
l'écran, les missions en attente sont des marqueurs, et **tous les boutons de la page** se replient
derrière un unique bouton `Actions` qui ouvre un modal.

## Décisions (validées)

1. **Layout : carte plein écran.** La carte occupe l'espace sous un hero compact. Statut en pilule
   flottante, bouton `Actions` flottant. Les KPIs, les boutons de présence, les accès rapides et
   « Voir toutes les missions » vont **tous** dans le modal. Les missions en attente restent visibles
   sur la page, sous forme de marqueurs.
2. **Moteur de carte : `react-native-maps`**, la même bibliothèque et la même version que l'app client
   (`1.27.2`, épinglée par Expo SDK 56). Écartés : `expo-maps` (absent d'Expo Go, exigerait un
   development build immédiatement) et WebView + Leaflet (ajouterait `react-native-webview` au provider,
   gestes et performance en retrait, rendu divergent du client).
3. **Modal : le `BottomSheet` partagé (gorhom)** plutôt qu'un `Modal` React Native. La dépendance est
   déjà installée dans le provider mais totalement inutilisée ; le drag-to-close correspond au layout
   validé. Coût : ajouter `GestureHandlerRootView` à la racine de `provider/App.tsx`.
4. **La tab bar est conservée.** La demande portait sur les boutons *de la page*, pas sur la navigation
   principale (Dashboard / Missions / Revenus / Profil).

## État des lieux (vérifié)

**Cartographie.** `mobile/provider/package.json` n'a ni `react-native-maps` ni `react-native-webview` ;
il a `expo-location`, `expo-task-manager` et les permissions Android nécessaires déjà déclarées
(`app.json` : `ACCESS_FINE_LOCATION`, `ACCESS_COARSE_LOCATION`, `ACCESS_BACKGROUND_LOCATION`,
`FOREGROUND_SERVICE_LOCATION`). `mobile/client/package.json` a `react-native-maps@1.27.2` et
`react-native-webview@13.16.1`, utilisés dans `client/src/screens/MissionTrackingScreen.tsx:3`
(`MapView`, `Marker`, `Polyline`, `PROVIDER_DEFAULT`). `mobile/node_modules/expo/bundledNativeModules.json`
liste `react-native-maps: 1.27.2`, `react-native-webview: 13.16.1` et `expo-maps: ~56.0.6` — les versions
du client sont donc bien celles gérées par Expo. `provider/src/screens/TrackingScreen.tsx:130` porte le
commentaire « Map placeholder — real MapView requires expo-maps or react-native-maps » : le placeholder
texte existe déjà et sert de repli.

**Aucune clé Google Maps configurée**, ni dans `client/app.json` ni dans `provider/app.json`. En Expo Go
la carte s'affiche via la clé d'Expo ; en build autonome Android elle sera grise sans
`android.config.googleMaps.apiKey`. L'app client porte déjà ce trou latent.

**Modal.** `@gorhom/bottom-sheet` est dans les dépendances du provider mais **n'apparaît dans aucun
fichier source**, et il n'y a **aucun `GestureHandlerRootView`** dans l'app provider. Sans ce wrapper à
la racine, un sheet gorhom s'affiche mais ne répond pas aux gestes — échec silencieux.
`shared/src/ui/BottomSheet.tsx` expose `forwardRef<GorhomBottomSheet, { snapPoints, children, onClose }>`
avec backdrop et `enablePanDownToClose` : il se pilote par ref.

**Le contrat de l'inbox est rompu (découvert pendant la planification).**
`ProviderMissionAssignmentController::serializeAssignment()` (ligne 137) renvoie une structure
**imbriquée** : `{ id, mission_id, assignment_status, assigned_at, expires_at, remaining_seconds,
mission: {…}, booking: {…} }`. Le type TS `MissionAssignment` (`provider/src/missions/types.ts`) déclare
au contraire tout **à plat** : `booking_id`, `service_name`, `client_name`, `address`, `city`,
`scheduled_date`, `scheduled_time`, `distance_km`. Et `client_name` comme `distance_km` ne sont renvoyés
**à aucun niveau** par l'API.

Conséquence, déjà en production : `DashboardScreen:54-58` (cartes d'aperçu) et
`MissionInboxScreen:40-49` (toute la liste des missions) affichent du vide sur chaque champ, et
`navigate('MissionDetail', { missionId: a.booking_id })` transmet `undefined`. Les suites sont vertes
parce que les mocks (`Dashboard.interaction.test.tsx`, `MissionInbox.interaction.test.tsx`) reproduisent
la forme plate imaginaire : **le test encode la fiction**. Le défaut est resté invisible parce que ces
blocs ne s'affichent que si `pendingCount > 0` et que la base de dev contient 0 mission et 0 assignation.

**Coordonnées des missions.** `missions.start_lat`, `start_lng`, `end_lat`, `end_lng` existent et sont
**déjà** dans l'eager-load de `inbox()` (ligne 49) — simplement non sérialisées hors mode `detailed`
(lignes 172-175). C'est la source à utiliser. `bookings.destination_lat/destination_lng` existent aussi
mais valent `NULL` sur les 4 bookings de la base de dev.

**Surface de rupture du sérialiseur.** Un seul test backend assert la forme du payload
(`tests/Feature/Phase11/ProviderMissionAssignmentControllerTest.php`) : `data.0.id`,
`data.0.assignment_status`, `data.0.mission_id` pour l'inbox, et `data.mission.id` pour `show()`. Côté
mobile, **seul `inbox` est consommé** — `show()` n'est appelé par aucun écran. Séparer les deux
sérialiseurs laisse donc `show()` et son test intacts.

**Données de dev absentes.** 0 `missions`, 0 `mission_assignments`, 0 booking géocodé. Sans fixture, la
carte n'a aucun marqueur à afficher.

**GPS.** `provider/src/tracking/hooks.ts:33` expose `useGpsWatcher(enabled, onPosition)`. Il demande la
permission foreground et, en cas de refus, fait un `return` nu (ligne 44) : **l'appelant ne peut pas
savoir que la permission a été refusée**.

**Acquis récents réutilisés tels quels.** `PresenceToggle` gère déjà l'erreur serveur et l'état pending
(correctif présence du 2026-07-26) ; l'accès « Revenus » navigue déjà correctement vers
`MainTabs { screen: 'Earnings' }` (correctif de navigation imbriquée du 2026-07-27).

---

## Architecture cible

```
provider/src/screens/DashboardScreen.tsx          (réécrit : carte + calques)
provider/src/screens/components/
    ProviderMap.tsx                               (nouveau)
    DashboardActionsSheet.tsx                     (nouveau)
    PresencePill.tsx                              (nouveau)
    PresenceToggle.tsx                            (inchangé, consommé par le sheet)
provider/src/maps/module.ts                       (nouveau : chargement défensif de react-native-maps)
provider/src/tracking/distance.ts                 (nouveau : haversine extrait de TrackingScreen)
provider/src/missions/types.ts                    (contrat aligné)
provider/src/screens/MissionInboxScreen.tsx       (champs réalignés + distance dérivée)
provider/App.tsx                                  (+ GestureHandlerRootView)
provider/__mocks__/react-native-maps.tsx          (nouveau, pour jest)

app/Http/Controllers/Api/ProviderMissionAssignmentController.php   (2 sérialiseurs séparés)
database/seeders/DevProviderMissionSeeder.php                      (nouveau)
```

### `DashboardScreen`

Passe de `<Screen scroll>` à un conteneur `flex: 1` non scrollable :

1. hero compact (salutation + `Avatar`) — conservé tel quel ;
2. `<ProviderMap />` en `flex: 1` ;
3. `<PresencePill onPress={openSheet} />` en `position: absolute` bas ;
4. bouton `Actions` en `position: absolute` sous la pilule ;
5. `<DashboardActionsSheet ref={sheetRef} />`.

L'écran ne conserve que la ref du sheet et le `useAuth()` du hero. Toutes les requêtes de données
descendent dans les sous-composants, qui consomment chacun leur propre hook — le fichier reste lisible et
chaque morceau est testable isolément.

### `ProviderMap`

Seul détenteur du `MapView`. Responsabilités : centrer la vue, tracer ma position, tracer un marqueur par
mission en attente géolocalisée, router vers le détail au tap.

`react-native-maps` est chargé derrière un **garde défensif** reprenant le motif de
`shared/src/push/availability.ts` : si le module ne se résout pas, le composant rend le placeholder texte
avec les coordonnées courantes au lieu de planter l'écran. C'est ce qui rend l'ajout de dépendance
native sans risque pour le flux Expo Go.

Tap sur un marqueur → `Callout` affichant `service_name` et `client_name` (fournis par le payload plat
réparé) plus la distance **dérivée** via `distance.ts` depuis la position GPS courante → tap sur le
callout →
`navigation.navigate('MissionDetail', { missionId: assignment.booking_id })`, exactement la cible
qu'utilisent déjà les cartes d'aperçu supprimées.

**Région de repli** quand aucune position n'est disponible et qu'aucune mission n'est géolocalisée :
constante explicite centrée sur Bruxelles (`latitude: 50.85`, `longitude: 4.35`, deltas 2.0 — échelle
pays), cohérente avec le marché belge du projet. Pas de région calculée au hasard.

### `DashboardActionsSheet`

`BottomSheet` partagé, `snapPoints` `['60%', '90%']`, fermé au départ. Contenu dans l'ordre du layout
validé : `Statut` (`<PresenceToggle />`), les 2 `KPICard` (missions en attente, solde disponible) avec
leurs `Skeleton` de chargement, les 4 accès rapides (Disponibilités, Badges, Revenus, Messagerie), puis
`Voir toutes les missions`. Chaque action ferme le sheet avant de naviguer.

### `PresencePill`

`PulseDot` + libellé issus de `usePresence()`. La pilule **n'écrit jamais** le statut : son `onPress` ne
fait qu'ouvrir le sheet, où se trouve le seul et unique chemin d'écriture de la présence
(`PresenceToggle`). Un seul point d'écriture, donc pas de divergence d'état possible.

---

## Changement backend — aligner le contrat de l'inbox

Décision : **réparer le contrat avant de construire la carte**. Les marqueurs et leur callout ont besoin
des mêmes champs que les deux écrans déjà cassés ; les corriger séparément reviendrait à construire la
carte sur une fiction.

`serializeAssignment()` est scindé en deux méthodes à responsabilité unique :

- **`serializeForList()`** (inbox) — payload **plat**, aligné sur ce que les écrans consomment déjà :
  `id`, `mission_id`, `assignment_status`, `expires_at`, `remaining_seconds`, `booking_id`,
  `service_name`, `client_name`, `address`, `city`, `postal_code`, `scheduled_date`, `scheduled_time`,
  `latitude`, `longitude`. Les coordonnées viennent de `mission.start_lat` / `start_lng` ; `client_name`
  de `booking.customer.name` (relation `Booking::customer()` → `belongsTo(User::class,
  'customer_user_id')`), à ajouter à l'eager-load.
- **`serializeForDetail()`** (`show()`) — conserve **exactement** la forme imbriquée actuelle, donc
  `show()` et son test existant ne bougent pas. Aucun écran mobile ne consomme cet endpoint.

Les trois assertions de l'inbox dans le test Phase 11 (`data.0.id`, `data.0.assignment_status`,
`data.0.mission_id`) survivent telles quelles au passage au plat.

**`distance_km` n'existe pas côté serveur et n'y sera pas ajoutée.** La distance est **dérivée côté
mobile** depuis la position GPS vive vers les coordonnées de la mission — plus juste qu'une distance
calculée serveur depuis une position de présence potentiellement périmée. L'implémentation haversine
existe déjà en local dans `provider/src/screens/TrackingScreen.tsx:23` : elle est extraite vers
`provider/src/tracking/distance.ts` et partagée par la carte et la liste des missions. Le champ
`distance_km` disparaît donc du type TS, remplacé par `latitude?` / `longitude?`.

**Mocks à réparer.** `Dashboard.interaction.test.tsx` et `MissionInbox.interaction.test.tsx` définissent
un `MOCK_ASSIGNMENT` à la forme plate fictive. Ils doivent être réalignés sur le payload réel — sinon les
suites resteraient vertes sur un contrat qui n'existe pas, ce qui est précisément le défaut d'origine.

## Données de dev — seeder dédié

`database/seeders/DevProviderMissionSeeder.php` (nouveau) : crée un booking géocodé, sa mission avec
`start_lat`/`start_lng`, et une `mission_assignment` au statut `assigned` non expirée pour un prestataire
donné (paramétrable par e-mail, défaut `test@test.com`). Versionné et rejouable, il rend la carte
observable en local — la base de dev n'a aujourd'hui aucune mission.

## Flux de données

| Donnée | Source | Consommateur |
|---|---|---|
| Missions en attente + coordonnées | `useMissionInbox()` (existant) | marqueurs, KPI « missions en attente » |
| Statut de présence | `usePresence()` (existant) | `PresencePill`, `PresenceToggle` |
| Solde | `useWalletBalance()` (existant) | KPI « solde disponible » |
| Position GPS | `useGpsWatcher(true, cb)` (existant, étendu) | centrage + marqueur « moi » |

**Extension additive de `useGpsWatcher`** : il retourne désormais l'état de permission
(`'granted' | 'denied' | 'pending'`) pour que la carte puisse expliquer une absence de position. Son
usage actuel dans `TrackingScreen` ignore la valeur de retour et reste inchangé.

## Dégradations — rien de silencieux

| Cas | Comportement |
|---|---|
| `react-native-maps` ne se résout pas | placeholder texte avec les coordonnées courantes |
| Permission GPS refusée | carte centrée sur la 1ʳᵉ mission géolocalisée, sinon région neutre ; bandeau « Position indisponible » |
| Mission sans coordonnées | non tracée, mais comptée : « N mission(s) sans localisation » |
| Erreur de `useMissionInbox` | la carte s'affiche quand même + bandeau d'erreur avec `Réessayer` |
| Aucune mission en attente | carte centrée sur ma position + mention « Aucune mission en attente » |

Cette table est la contrainte de conception principale : chaque échec doit être **visible et
récupérable**. C'est la leçon des deux correctifs précédents — une présence qui échouait en silence et
un écran Revenus qui affichait « 0 € » sur un 403.

## Tests

**Mobile** (`provider/__tests__/`), avec `__mocks__/react-native-maps.tsx` déclaré dans
`moduleNameMapper` de `jest.config.ts`, comme les autres modules natifs :

- `ProviderMap` : rend le placeholder si le module est absent ; un marqueur par mission géolocalisée ;
  les missions sans coordonnées ne sont pas tracées mais sont comptées ; tap marqueur → `MissionDetail`
  avec le bon `booking_id` ; bandeau d'erreur + retry quand l'inbox échoue ; mention « Aucune mission en
  attente » quand l'inbox est vide ; repli sur la région Bruxelles sans position ni mission géolocalisée.
- `DashboardActionsSheet` : fermé au départ ; `Actions` l'ouvre ; contient les 4 accès rapides et les
  boutons de présence ; « Revenus » navigue vers `MainTabs { screen: 'Earnings' }`.
- `DashboardScreen` : la carte est montée ; la grille d'accès rapides n'est plus rendue sur la page.

- `MissionInboxScreen` : chaque champ du payload réel s'affiche réellement (le test doit échouer sur
  l'ancien code, qui rendait du vide), et la distance dérivée apparaît quand la position est connue.
- `distance.ts` : haversine sur deux points connus ; `null` si la position ou les coordonnées manquent.

**Backend** : `ProviderMissionAssignmentController` — l'inbox renvoie le payload plat complet
(`service_name`, `client_name`, `latitude`, `longitude`, …) ; `latitude`/`longitude` valent `null` quand
la mission n'est pas géocodée ; `show()` conserve sa forme imbriquée et son test d'origine passe toujours.

Chaque test doit être **vérifié rouge avant d'être vert** (confrontation à la version pré-correctif),
conformément à ce qui a été appliqué sur `EarningsScreen`.

## Hors périmètre (volontairement)

Polylignes d'itinéraire, clustering de marqueurs, tuiles hors-ligne, suivi de position en arrière-plan
depuis le dashboard, et modification de l'app client. **La clé `android.config.googleMaps.apiKey`** est
hors périmètre en tant que tâche go-live distincte : elle conditionne les builds autonomes Android des
**deux** apps, pas ce changement d'écran.

## Risques

| Risque | Mitigation |
|---|---|
| `react-native-maps` indisponible dans le Expo Go installé | garde défensif + repli placeholder ; `expo-dev-client` est déjà une dépendance du provider si un development build devient nécessaire |
| Le sheet gorhom ne réagit pas aux gestes | `GestureHandlerRootView` ajouté à la racine, couvert par le test « `Actions` ouvre le sheet » |
| Carte grise en build autonome Android | tracé explicitement comme tâche go-live ci-dessus ; sans impact en Expo Go et en development build |
| Perte d'accès aux missions en un tap | les marqueurs et « Voir toutes les missions » couvrent les deux besoins ; la tab bar Missions reste |
| Changer le payload de l'inbox casse un consommateur oublié | `show()` est isolé dans son propre sérialiseur ; côté mobile seul `inbox` est appelé (vérifié par recherche sur `provider/assignments`) ; les 3 assertions inbox du test Phase 11 survivent au passage au plat |
| Les mocks réalignés cachent une nouvelle divergence | le seeder de dév fournit un chemin de vérification contre l'API réelle, pas seulement contre des mocks |
