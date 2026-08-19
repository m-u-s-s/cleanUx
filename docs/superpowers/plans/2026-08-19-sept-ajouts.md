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

---

## État — 2026-08-19

**Vert :** 369 tests PHP mission · 460 mobile client · 650 mobile prestataire · `tsc` propre des
deux côtés · PHPStan sans argument de chemin : 0 erreur.

| Ajout | État |
|---|---|
| ① Appel masqué | ✅ **branché** mobile + web, des deux côtés. Rien à écrire côté serveur : tout existait |
| ② Renfort depuis le terrain | ✅ service, API, boutons mobile + web, côte à côte avec la révision |
| ③ Navigation externe | ✅ bouton **secondaire** — la carte de la plateforme reste principale |
| ③ Route active sur la carte d'accueil | ✅ + **le GET qui manquait** au module de suivi |
| ④ File d'attente hors-ligne | ✅ **en dernier**, parce qu'elle touche le chemin de l'argent — et c'est bien là que le piège était |
| ⑤ Partage du suivi à un tiers | ✅ tout existait sauf **l'appelant mobile** : lien signé 12 h, page publique pauvre, exposée sur le web depuis toujours |
| ⑥ Consigne d'accès de dernière minute | ✅ mobile + web, sans fenêtre et détachée en tête de fiche |
| ⑦ Minuteur de retard | ✅ le retard était mesurable depuis toujours ; **l'annonce** manquait, et cinq colonnes de créneau ne bougeaient pas ensemble |
| ⑧ Passe graphique | ✅ `CarteDeMission` sur `GlassSurface` (4 cartes converties) + le mode sombre des 3 blades de mission |

### Le constat confirmé *(écrit à mi-parcours — le compte final est SEPT, voir le verdict en fin de fichier)*

**Trois modules complets et injoignables**, découverts en trois passes : le SOS (plan 4), le renfort
et l'appel masqué (ici). Aucun ne demandait d'être écrit — tous demandaient d'être **branchés**.
C'est la famille de défaut la plus coûteuse de ce dépôt : du travail livré, testé, payé, et que
personne ne peut atteindre.

### Une colonne réveillée au passage

`mission_reinforcement_requests.required_people` est **NOT NULL** et absente de `$fillable` : toute
demande posée par ce chemin échouait au niveau SQL. Elle n'avait jamais servi parce que le seul
écrivain — le centre du chef d'équipe — la laissait vide. Avec `provider_team_id` et `needed_at`,
qui se perdaient en silence, ce sont trois colonnes qui reprennent du service.


### Seconde passe — 2026-08-19

**Quatre ajouts sur sept.** 371 tests PHP mission · 460 mobile client · 652 mobile prestataire ·
`tsc` propre · PHPStan : 0 erreur.

**Un quatrième trou de lecture trouvé** : le module de suivi n'avait **que des écritures** —
démarrer, pinguer, terminer. Le prestataire ne pouvait relire nulle part la session qu'il avait
ouverte : son écran la gardait en mémoire locale, et une application relancée l'oubliait. Le `GET`
sert deux besoins d'un coup — la route sur la carte d'accueil, et la reprise après relance.

**Deux décisions notées dans le code**, parce qu'elles ne se devinent pas :

- la consigne de dernière minute **n'a pas de fenêtre**, contrairement à la to-do list. Un digicode
  qui change à 17 h doit pouvoir se dire à 17 h : c'est le prestataire qu'elle dépanne, pas le
  client qu'elle avantage ;
- elle vit dans **sa propre colonne**. Écrire dans le carnet de lieux ferait lire un code du jour à
  quelqu'un d'autre la semaine suivante.

**Restent :** ⑤ partage du suivi, ⑦ minuteur de retard, ④ file d'attente hors-ligne, ⑧ passe
graphique.


---

## Verdict de fin de plan — 2026-08-19

**Huit ajouts sur huit. Les cinq plans du programme sont clos.**

| Mesure | Résultat |
|---|---|
| Suite PHP complète | **7225 passés**, 10 ignorés, **0 échec** (2571 s) |
| PHPStan (sans argument de chemin) | **0 erreur** |
| Jest mobile client / prestataire | **474** / **659** |
| `tsc` client / prestataire | propre / propre |

### Les quatre échecs du run précédent, et ce qu'ils disaient

Aucun n'a été contourné ; les quatre venaient de ce programme.

1. **`QuestionnaireCenter` n'était pas gardé au niveau composant.** La route portait la middleware
   admin ; ce dépôt exige EN PLUS la garde sur le composant, parce qu'un composant Livewire
   s'atteint par son point d'entrée propre. C'est le test de complétude sur le namespace — pas une
   relecture — qui l'a dit.
2. et 3. **`MissionExecutionBoardCoverageBatch10Test` exigeait des tâches dès le montage.** Il
   mesurait l'ancien monde, celui où `ensureChecklist()` posait six lignes de gabarit toutes
   obligatoires. L'assertion change de SENS — le porte-liste naît vide, il appartient au client —
   avec son témoin positif : dès qu'une tâche est posée, elle bloque bien et le tableau de bord
   sait la cocher.
4. **Le minuteur de retard manquait à l'inventaire d'ordonnanceur.** `DeploiementFilesEtOrdonnanceurTest`
   relit `Kernel.php` et exige que chaque tâche planifiée figure dans `QUEUE_CRON_SCHEDULER.md`. Ce
   garde existe parce que l'inventaire a déjà nommé deux commandes inexistantes : l'ops cherchait
   des tâches fantômes et ne voyait pas les vraies manquer.

### Ce que les quatre derniers ajouts ont trouvé

- **`bookings` porte CINQ colonnes pour une seule heure** — `date`, `heure`, `scheduled_date`,
  `scheduled_time`, `scheduled_at` — et la reprogrammation n'en déplaçait que deux. Mesuré : un
  rendez-vous du 10 septembre 10 h déplacé au 12 à 14 h gardait l'ancien créneau sur les trois
  autres. Le barème d'annulation lit `scheduled_at` en premier : un client qui décalait d'une
  semaine puis annulait était facturé au palier « moins de 24 h », **calculé contre le créneau
  qu'il venait d'abandonner**.
- **La file hors-ligne confondait deux échecs.** Le réseau absent se retente ; le serveur qui
  refuse ne se retente pas — et repartait pourtant à chaque reconnexion, pour toujours.
- **La clôture n'entre PAS dans cette file**, et ce n'est pas un oubli : elle consomme un code de
  fin à usage unique et déclenche l'encaissement. Rejouée plus tard, elle échouerait sur un code
  déjà consommé — après avoir laissé croire au prestataire qu'il avait terminé et quitté les lieux.

### Le compte final des modules injoignables : SEPT

SOS · renfort · appel masqué · **la lecture** du module de suivi · le partage du suivi ·
`offlineAwareMutation` · `OfflineBanner`. Deux variantes plus discrètes que « aucun appelant » sont
apparues en chemin : le module qui n'a **que des écritures**, et celui atteignable **d'un seul
côté**.

### Périmètres nommés et laissés dehors

- Le **mode sombre du reste du web client** (2 blades sur l'ensemble le gèrent) — chantier distinct.
- **Trois sanctions client sur quatre** : elles vivent dans la politique d'annulation et le moteur
  de commande ; seul le blocage temporaire est branché ici.
- **Le signal S2** de la spec (taux de révision contre la médiane des confrères) — corroboratif.
- **Les photos hors-ligne** : la file ne transporte que du JSON.
