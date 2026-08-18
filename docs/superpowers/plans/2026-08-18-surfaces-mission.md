# Surfaces de mission — « Ma mission », les trois pages terrain, et la parité web

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans.

**Goal :** donner au client une porte d'entrée unique vers tout ce qui concerne sa mission, éclater
la page terrain du prestataire en trois pages pilotées par le moteur, et amener le web au niveau du
mobile des deux côtés.

**Spec :** `docs/superpowers/specs/2026-08-18-mission-terrain-design.md` §§ 5 et 6

## Global Constraints

- **Aucune couleur ni espacement en dur.** Jetons `useThemeColors` / `spacing` / `radius` /
  `shadows` côté mobile, classes du système côté web. Mode sombre traité partout.
- **Réutiliser les composants du projet** : `BottomSheet`, `GlassSurface`, `LuxeBackground`,
  `MissionClockBar`, `OsmMap`. Un écran qu'on reconnaît comme rapporté est un écran raté.
- **Rien de ce qui existe ne disparaît** : chaque option de la page terrain actuelle atterrit dans
  la page de son moteur.
- `useReducedMotion` et les annonces de lecteur d'écran sur tout ce qui bouge ou vibre.
- **La carte de la plateforme reste la carte principale** ; la navigation externe est secondaire.

## Ce qui existe et qu'on ne réécrit pas

| Existant | Où |
|---|---|
| La feuille du bas, motif de l'accueil | `HomeActionsSheet` + `BottomSheet` de `@brio/shared` |
| Le « déroulé de l'intervention » | `OnSiteScreen` — devient « Gérer ma mission » |
| Le suivi carte client | `MissionTrackingScreen` |
| Le compteur horaire | `MissionClockBar` + `useMissionClock` |
| La carte web client | `livewire/client/mission-live-tracking` |
| Les codes QR web | `livewire/client/mission-qr-codes` |
| La page terrain web, en 7 partiels | `livewire/employe/mission-field/*` |
| Le cycle de vie web complet | `Livewire/Employe/MissionActions` |
| Le SOS | `SafetyScreen` — à rendre atteignable |
| Le renfort | `TeamLeadOperationsService` — à rendre atteignable |

---

### Task 1 : les hooks client de la to-do list et du devis
- Modify: `mobile/client/src/booking/onsite.ts`
- Produces : `useTodoList`, `useAjouterTache`, `useRetirerTache`, `useRevisionDeDevis`,
  `useRepondreALaRevision`

### Task 2 : la feuille « Ma mission »
- Create: `mobile/client/src/screens/components/MissionSheet.tsx`
- Un APERÇU, pas le contenu : ce qui attend une réponse, et deux raccourcis.

### Task 3 : le bouton sous la carte
- Modify: `mobile/client/src/screens/MissionTrackingScreen.tsx`
- + bouton « Ma mission », + feuille, et **correction de `rgba(255,255,255,0.92)` en dur** (ligne
  258) qui donne un rectangle blanc sur une carte de nuit.

### Task 4 : « Gérer ma mission »
- Modify: `mobile/client/src/screens/OnSiteScreen.tsx`
- Ordre imposé par l'urgence : devis révisé, suppléments, prolongation, codes, to-do, avancement,
  photos, joindre & signaler.

### Task 5 : les trois pages terrain du prestataire
- Modify: `mobile/provider/src/screens/MissionFieldScreen.tsx` — devient l'aiguilleur
- Create: `FieldDomicile`, `FieldHoraire`, `FieldVehicule`
- + SOS et renfort atteignables depuis le terrain

### Task 6 : le web client — carte + panneau
- Modify: `resources/views/livewire/client/mission-tracking.blade.php`
- Create: `App\Livewire\Client\GererMaMission` + vue

### Task 7 : le web prestataire — les blocs manquants
- Modify: `resources/views/livewire/employe/mission-field-page.blade.php` + partiels
- + imprévu, supplément, fiche d'accès, compteur, nouveau devis

### Task 8 : annuler, par rôle
- Le questionnaire servi par l'API reçoit ses écrans, des deux côtés, web et mobile.

### Task 9 : suite ciblée, `tsc`, jest mobile, PHPStan sans chemin
