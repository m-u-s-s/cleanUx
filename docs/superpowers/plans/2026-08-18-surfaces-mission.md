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

---

## État à l'issue de cette passe — 2026-08-18

**Vert :** 348 tests mission (PHP) · 453 mobile client · 650 mobile provider · `tsc` propre des deux
côtés · PHPStan sans argument de chemin : 0 erreur.

| Tâche | État | Détail |
|---|---|---|
| 1 · hooks client | ✅ | to-do list et révision de devis dans `booking/onsite.ts` |
| 2 · feuille « Ma mission » | ✅ | `MissionSheet` + 6 tests |
| 3 · bouton sous la carte | ✅ | + correction de la couleur en dur de l'encart ETA |
| 4 · « Gérer ma mission » | ✅ | `MissionQuoteRevisionCard` + `MissionTodoCard`, 12 tests |
| 5 · terrain prestataire | ⚠️ **partiel** | voir ci-dessous |
| 6 · web client | ✅ | `GererMaMission` + 5 tests, garde et `#[Locked]` |
| 7 · web prestataire | ❌ | les cinq blocs manquants |
| 8 · annuler par rôle | ❌ | le questionnaire est servi, il lui manque ses écrans |

### Tâche 5 — ce qui a été fait, et ce qui a été écarté

**Fait :** le moteur est servi par le payload (`engine`), la page terrain le lit, le nouveau devis
y arrive — moteur à domicile seulement, et AVANT le supplément parce qu'il est avant lui dans le
temps. Le supplément n'est plus **monté** sur une course. Le SOS, la messagerie et le litige
deviennent atteignables depuis le terrain.

**Écarté, et c'est un choix :** la spec disait « trois écrans ». La page fait 789 lignes et porte
huit outils que le porteur veut tous conserver ; la découper en trois fichiers aurait déplacé du
code sans rien ajouter, avec le risque d'en perdre en route. Une page **composée par moteur** donne
le même résultat visible — chaque moteur voit ses options et seulement les siennes — sans toucher à
ce qui marche. Les sections neuves, elles, naissent en fichiers séparés.

**Le renfort depuis le terrain** appartient au plan 5 : c'est la proposition ② des sept ajouts, et
elle demande une API que le prestataire n'a pas encore.

---

## Plan 4 terminé côté web — 2026-08-18 (seconde passe)

**Vert :** 821 tests PHP (API + missions + moteur) · 453 mobile client · 650 mobile prestataire ·
`tsc` propre des deux côtés · PHPStan sans argument de chemin : 0 erreur.

| Tâche | État |
|---|---|
| 1 → 6 | ✅ *(première passe)* |
| 7 · web prestataire | ✅ les cinq blocs : fiche d'accès, imprévu, supplément, compteur, nouveau devis |
| 8 · annuler par rôle | ✅ **web** — un composant pour les deux rôles, avec l'aiguillage |
| 8 · annuler par rôle | ❌ **mobile** — l'API sert le questionnaire, il manque les écrans |

### Ce que le dépôt a rattrapé tout seul

`ReservationIntervenantTest` a refusé `MissionFieldTools` : *« qui intervient » ne se déduit pas
d'une colonne*. Trois colonnes ont porté cette question et ont été fusionnées ; les relire à la main
aurait recréé une quatrième réponse, qui aurait divergé au premier renfort de société.
`Mission::estIntervenant()` fait autorité — le garde-fou a fait exactement son travail.

### Le choix confirmé par le porteur

**Une seule page terrain**, composée par moteur, plutôt que trois fichiers. Chaque moteur voit ses
options et seulement les siennes : le supplément et le nouveau devis ne sont pas **rendus** sur une
course, le compteur se rend nul de lui-même hors mission horaire. Pas grisés — pas montés : un
formulaire visible et inerte se remplit quand même, et le refus arrive après la saisie.
