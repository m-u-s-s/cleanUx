# Annulation questionnée — le formulaire, ses vérifications, et son module admin

> **For agentic workers:** REQUIRED SUB-SKILL: superpowers:executing-plans.

**Goal :** remplacer le texte libre de l'annulation par un questionnaire dont chaque réponse est
soit vérifiable, soit engageante — et le rendre administrable depuis la console.

**Architecture :** `CancellationEngine::quote()` honore **déjà** `reason_code` contre
`cancellation_exempt_reasons` et pose `exempt_applied` + frais nuls. Il n'a jamais reçu de code.
Le questionnaire est donc la pièce manquante en AMONT, pas un moteur de plus.

**Spec :** `docs/superpowers/specs/2026-08-18-mission-terrain-design.md` § 8

## Global Constraints

- **Aucune suppression dure** d'une question ou d'une option : `deleted_at` + `is_active`. Une
  annulation passée a été décidée avec les questions d'alors.
- Le `code` d'une option est **stable et jamais réutilisé** — c'est lui qui vit dans `reason_code`.
- **Toute écriture administrative passe par un service**, jamais par une écriture de colonne
  (règle du dépôt pour la console).
- Un **instantané** du questionnaire montré est écrit dans `booking_cancellations_v2.metadata`.
- On ne pose que des questions **vérifiables ou engageantes**.

## Ce qui existe et qu'on ne refait pas

| Existant | Où |
|---|---|
| Exemption par `reason_code`, frais mis à zéro | `CancellationEngine::quote():77-88` |
| Politiques, paliers, motifs exemptés | `cancellation_policies` · `_tiers` · `_exempt_reasons` |
| Motifs semés : `force_majeure`, `medical_emergency`, `provider_no_show` | `CancellationPoliciesSeeder` |
| Pénalité « prestataire déjà en route » | `CancellationEngine:95-110` |
| API client et prestataire | `CancellationV2Controller` · `routes/api/v2-shared.php` |

## Ce qui est dormant et qu'on réveille

`cancellation_exempt_reasons.max_per_user_per_30d` est déclaré, semé (2 et 3), et **personne ne
l'applique**. C'est exactement la règle « pas la première fois, mais si c'est fréquent » du porteur.

---

### Task 1 : les deux tables du questionnaire
- Migration `2026_09_06_090000_creer_le_questionnaire_d_annulation.php`
- Modèles `CancellationQuestion`, `CancellationQuestionOption`

### Task 2 : le service, seule porte d'écriture
`CancellationQuestionnaireService` — `pour()`, et le CRUD administratif journalisé.

### Task 3 : les vérifications
`CancellationAnswerVerifier` — `provider_late` (planned_start_at vs statut réel), `gps_movement`
(la trace montre-t-elle un déplacement), `client_unreachable` (ping / SMS / appel).
Une option non vérifiée **n'est pas proposée** plutôt que d'être proposée puis refusée.

### Task 4 : le plafond des motifs exemptés
`max_per_user_per_30d` appliqué : au-delà, l'exemption ne joue plus et le palier normal s'applique.

### Task 5 : brancher le questionnaire sur l'annulation
`GET /api/.../cancellation-questionnaire` ; `reason_code` accepté par `clientExecute` et
`providerExecute` ; instantané écrit dans `metadata`.

### Task 6 : le questionnaire par défaut, semé
`CancellationQuestionnaireSeeder` — les options des § 8.3 et 8.4 de la spec, avec leurs
vérifications, leurs issues et leurs signaux d'entente.

### Task 7 : le module admin
`CancellationQuestionResource` + enregistrement dans `AdminConsoleServiceProvider` +
entrée `admin:admin.cancellation-questions.center` dans `config/modules.php`.

### Task 8 : refermer le report du plan 2
`refuser(stop)` peut désormais annuler avec un motif exempté : le client de bonne foi ne paie rien.

### Task 9 : suite ciblée, PHPStan sans chemin, `migrate --pretend`

---

## État à la fin du plan — 2026-08-18

**Terminé, vert, PHPStan propre.** 343 tests sur le périmètre mission, 137 sur l'annulation,
94 sur les annuaires de modules.

| Tâche | État | Fichiers |
|---|---|---|
| 1 · tables | ✅ | `2026_09_06_090000_creer_le_questionnaire_d_annulation.php` · `CancellationQuestion` · `CancellationQuestionOption` |
| 2 · service, seule porte d'écriture | ✅ | `CancellationQuestionnaireService` |
| 3 · vérifications | ✅ | `CancellationAnswerVerifier` (retard, déplacement, client injoignable) |
| 4 · plafond des motifs exemptés | ✅ | `CancellationExemptQuota` + 5 lignes dans `CancellationEngine::quote()` |
| 5 · questionnaire servi par l'API | ✅ | 2 routes `cancellation-questionnaire`, client et prestataire |
| 6 · questionnaire par défaut | ✅ | `CancellationQuestionnaireSeeder` — 3 questions, 11 réponses, 6 motifs exemptés |
| 7 · module admin | ✅ | `CancellationQuestionResource` · `QuestionnaireCenter` + vue · les deux annuaires |
| 8 · refermer le report du plan 2 | ✅ | `refuser(stop)` annule par le tuyau commun, gratuitement, plafonné à 2/30 j |

### Ce que le plan a réveillé

`max_per_user_per_30d` était déclarée, semée à 2 pour l'urgence médicale, et **appliquée par
personne** : le motif le plus généreux du barème exonérait autant de fois que voulu. Il mord
désormais — et le dépassement retire l'exemption, jamais le motif : on doit pouvoir relire qu'une
personne l'a invoqué six fois en un mois.

Et l'aperçu consulte le même plafond que l'exécution. Sans cela, l'écran aurait annoncé « 0 € »
pendant que le débit tombait : un montant montré doit être celui qu'on prélève.

### Ce qui reste au plan 4

Les **boutons** d'annulation par rôle, sur les écrans. Le questionnaire est servi par l'API des
deux côtés ; il lui manque ses surfaces — web et mobile, client et prestataire. C'est le lot des
surfaces, pas celui-ci.

**L'édition des OPTIONS** reste sur l'API de la console (mobile) : la page web liste, active,
désactive et retire, mais n'édite pas une réponse ligne à ligne. Une question porte un arbre —
vérification, issue, exemption, signal — que le rendu générique d'une liste ne sait pas montrer sans
mentir sur sa structure. Même choix que la grille tarifaire d'un métier, qui reste sur sa page.
