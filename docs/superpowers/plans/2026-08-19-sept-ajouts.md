# Les sept ajouts, et la passe graphique

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans.

**Goal :** livrer les sept ajouts validés par le porteur, puis reprendre visuellement toutes les
pages de mission.

**Spec :** `docs/superpowers/specs/2026-08-18-mission-terrain-design.md` § 7.4

## Le constat qui change l'ordre du plan

**Trois des sept existent déjà côté serveur et sont injoignables.** Mesuré le 2026-08-19 :

| Module | État serveur | Atteignable ? |
|---|---|---|
| **Appel masqué** ① | complet — contrôleur, service, provider abstrait + mock, table, routes des deux côtés | **nulle part** : ni mobile, ni web |
| **Renfort** ② | `MissionReinforcementRequest` + `TeamLeadOperationsService` | web, chef d'équipe uniquement |
| **SOS** *(hors sept)* | complet | corrigé au plan 4 |

Le travail n'est donc pas de construire, mais de **brancher**. C'est la même famille de défaut que
`service_name` au premier jour : du travail livré que personne ne peut atteindre.

## Global Constraints

- **Aucune couleur ni espacement en dur.** Jetons des deux côtés, mode sombre traité.
- **La carte de la plateforme reste la carte principale** — décision du porteur. La navigation
  externe est un bouton secondaire.
- `useReducedMotion` et annonces de lecteur d'écran sur tout ce qui bouge ou vibre.
- Rien de ce qui existe ne disparaît.

---

### Task 1 : ① l'appel masqué, branché des deux côtés
- Mobile : un bouton « Appeler » sur la page terrain et dans « Ma mission », qui ouvre le numéro
  relais rendu par l'API.
- Web : le même, sur les deux pages de mission.
- **Rien à écrire côté serveur** : `MaskedCallService` répond déjà.

### Task 2 : ② le renfort depuis le terrain
- API prestataire `POST /provider/missions/{mission}/reinforcement`
- Le bouton sur la page terrain, et l'aiguillage du questionnaire d'annulation y mène déjà.

### Task 3 : ③ navigation externe + route active sur la carte d'accueil
- Bouton secondaire « Ouvrir dans Plans / Waze ».
- `ProviderMap` affiche la route active, pas seulement les marqueurs.

### Task 4 : ⑦ le minuteur de retard
- Le retard est déjà MESURABLE (`CancellationAnswerVerifier::leProviderEstEnRetard()`).
- Il manque l'annonce automatique au client et les trois issues : attendre, reprogrammer, annuler
  sans frais — cette dernière existe déjà comme motif exempté.

### Task 5 : ⑥ consigne d'accès de dernière minute
- Le client pousse une consigne pendant le trajet ; elle atterrit sur la fiche d'accès existante.

### Task 6 : ⑤ partager le suivi avec un tiers
- Lien signé, à durée de vie limitée, portant la carte et l'ETA — et rien d'autre.

### Task 7 : ④ la file d'attente hors-ligne
- **En dernier, et c'est délibéré** : elle touche le chemin de l'argent. Clés d'idempotence
  strictes ; la clôture ne doit jamais capturer deux fois.

### Task 8 : la passe graphique
- Toutes les pages de mission, de l'offre reçue à la clôture, des deux côtés, web et mobile.

### Task 9 : suites ciblées, `tsc`, jest ×2, PHPStan sans chemin
