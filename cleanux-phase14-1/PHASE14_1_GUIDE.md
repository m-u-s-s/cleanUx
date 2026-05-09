# Phase 14.1 — UI admin docs review + UI Livewire onboarding

> **Objectif** : finaliser l'UX du système onboarding livré en Phase 14, qui
> était API-first uniquement.
>
> Ajoute :
> 1. **Centre admin** pour valider/rejeter les documents KYC (avec preview)
> 2. **Liste admin** des prestataires en cours d'onboarding (avec approve final)
> 3. **Wizard prestataire** en 7 étapes, full UI Livewire

---

## 1. Architecture livrée

```
app/Livewire/Admin/Onboarding/
├── AdminOnboardingDocumentsCenter.php        ← /admin/onboarding-documents
└── AdminOnboardingProvidersList.php          ← /admin/onboarding-providers

app/Livewire/Provider/Onboarding/
└── ProviderOnboardingWizard.php              ← /provider/onboarding (wizard 7 étapes)

app/Http/Controllers/Admin/
└── OnboardingDocumentController.php          ← serve fichiers privés (URL signée)

resources/views/livewire/admin/onboarding/
├── admin-onboarding-documents-center.blade.php
└── admin-onboarding-providers-list.blade.php

resources/views/livewire/provider/onboarding/
└── provider-onboarding-wizard.blade.php

tests/Feature/Phase14_1/
└── Phase14_1Test.php                          ← 15 tests Livewire

patches/
└── 01_integration.md
```

## 2. Vue d'ensemble

### Côté admin (2 écrans)

#### `/admin/onboarding-documents` — Centre de validation des documents
- 3 cards counts (pending / approved / rejected) cliquables = filtres
- Recherche par nom/email du prestataire
- Filtre par type de document (carte ID, passeport, assurance...)
- Tableau avec actions :
  - 👁 Preview (modal avec iframe PDF ou img)
  - ✓ Approuver (avec confirmation)
  - ✕ Rejeter (modal avec motif obligatoire min 5 caractères)

#### `/admin/onboarding-providers` — Liste des prestataires
- 3 cards counts (in_progress / ready / verified)
- Tableau par prestataire :
  - Étape actuelle avec progress bar
  - Stats documents (✓ approved / ⏳ pending / ✕ rejected)
  - Statut Stripe Connect
  - Bouton "Approuver" si prêt (étape ≥ 5 et pas encore validé)
- Cliquer sur "Documents à valider" → redirige vers le centre docs

### Côté prestataire — Wizard 7 étapes

`/provider/onboarding`

```
[1. Profil] → [2. Identité] → [3. Fiscal] → [4. Assurance] → [5. Compétences] → [6. Stripe] → [7. Validation]
   ✓             ⏳              ⏳              ⏳              ⏳              ⏳              ⏳
```

Progress bar cliquable en haut (peut revenir en arrière sur étapes faites,
pas sauter en avant).

**Étape 1 (Profil)** : nom, phone, bio, photo upload  
**Étape 2 (Identité)** : 1 doc parmi {carte ID, passeport, titre séjour}  
**Étape 3 (Fiscal)** : numéro TVA / SIREN  
**Étape 4 (Assurance)** : upload PDF/image  
**Étape 5 (Compétences)** : checkboxes parmi 8 métiers + zones de travail  
**Étape 6 (Stripe)** : bouton "Configurer mon compte Stripe" (ouvre lien dans nouvel onglet) + "J'ai terminé"  
**Étape 7 (Validation)** : écran "En attente de validation admin" + récap statut docs

À chaque étape :
- Validation Livewire stricte (required, max sizes, mime types)
- Affichage du dernier document uploadé avec son statut (approuvé/rejeté/pending)
- Si rejeté : motif visible, le provider peut re-uploader

## 3. Stats Phase 14.1

| Composant | Lignes |
|---|---|
| `AdminOnboardingDocumentsCenter` | ~165 |
| `admin-onboarding-documents-center.blade` | ~225 |
| `AdminOnboardingProvidersList` | ~120 |
| `admin-onboarding-providers-list.blade` | ~195 |
| `ProviderOnboardingWizard` | ~290 |
| `provider-onboarding-wizard.blade` | ~395 |
| `OnboardingDocumentController` | ~50 |
| Tests Livewire (15) | ~265 |
| Patches + guide | ~280 |
| **Total Phase 14.1** | **~1985 lignes** |

## 4. Tests inclus (15)

**Admin Documents Center (5)** :
- ✅ Renders OK
- ✅ Filtre par status fonctionne
- ✅ Approve doc met status='approved' + reviewed_by
- ✅ Reject doc avec motif met status='rejected' + reason
- ✅ Reject sans motif → erreur de validation

**Admin Providers List (4)** :
- ✅ Renders OK
- ✅ Counts par status calculés correctement
- ✅ Approuver onboarding marche si tous docs OK + Stripe actif
- ✅ Approuver échoue sans documents

**Provider Wizard (6)** :
- ✅ Renders pour user authentifié, crée auto le ProviderProfile
- ✅ Step 0 : validation requis sur name
- ✅ Step 0 : sauvegarde + advance vers step 1
- ✅ Step 1 : upload identity doc → DB + advance step 2
- ✅ Step 4 : validation au moins 1 skill
- ✅ Step 4 : sauvegarde skills
- ✅ Bonus : ne peut pas sauter en avant des étapes pas faites

## 5. Workflow d'application

```bash
unzip cleanux-phase14-1.zip
cd /chemin/vers/CleanUx
git checkout -b phase14-1/onboarding-ui

rsync -av cleanux-phase14-1/app/        app/
rsync -av cleanux-phase14-1/resources/  resources/
rsync -av cleanux-phase14-1/tests/      tests/

# Patches manuels (voir patches/01_integration.md):
# - routes/admin.php : 3 routes (docs center, providers list, file download)
# - routes/web.php : route /provider/onboarding
# - Menu admin : ajouter le lien onboarding (avec badge count)
# - (optionnel) LoginResponse : auto-redirect provider en cours d'onboarding

# Storage symlink (pour les photos)
php artisan storage:link

# Tests
php artisan test --filter=Phase14_1Test  # 15 tests verts

# Démo runtime
# 1. Login en admin → /admin/onboarding-providers (liste vide)
# 2. Login en user normal → /provider/onboarding (wizard)
# 3. Faire les 5 étapes (skip stripe en local)
# 4. Login admin → voir le provider dans "ready"
# 5. Aller dans /admin/onboarding-documents → preview + approve docs
# 6. Retour sur providers list → bouton "Approuver"
# 7. Login provider → wizard montre "Bienvenue dans CleanUx !"
```

## 6. Limites honnêtes

- **Pas de bulk actions** : approuver 10 docs un par un. Ajout facile (~50 lignes) si volume.
- **Pas de notification provider** quand un doc est approuvé/rejeté : à ajouter
  via `$provider->notify(new DocumentReviewedNotification(...))`. Reportée à Phase 14.2.
- **Pas d'historique structuré des actions admin** : le service stocke
  `reviewed_by`+`reviewed_at` mais pas d'audit log dédié. Brancher Phase 4 si besoin.
- **Photo profil pas affichée côté client** : stockée + uploadable mais pas
  affichée dans `MissionOfferPage` ou autre. Ajout `<img src="{{ asset('storage/'.$photo) }}">` 
  partout où on veut.
- **Wizard mobile UX** : fonctionne mais peut être amélioré (sticky progress bar, etc.).
- **Pas de "Skip étape"** : si tu veux qu'un provider puisse passer une étape
  optionnelle (ex: tax_id pas encore obtenu), à faire via un état "skipped" dédié.
- **Wizard ne gère pas les changements de mind** : si le provider veut changer son
  type de doc d'identité (passeport vs carte ID), il doit refaire l'upload. Le service
  archive l'ancien automatiquement.

## 7. Récap Phase 14 + 14.1

| Phase | Livré | Lignes |
|---|---|---|
| Phase 14 — Surge + Onboarding service + Cancellation fees | Backend + API | 2946 |
| **Phase 14.1 — UI onboarding (admin + wizard provider)** | **UI Livewire** | **~1985** |
| **Total Phase 14 complet** | | **~4931 lignes** |

L'onboarding est maintenant **complètement self-service** :
- ✅ Le provider s'inscrit, fait son wizard, upload ses docs sans intervention humaine
- ✅ L'admin valide en quelques clics avec preview
- ✅ Le système est notifications-ready (next step facile)

## 8. Ce qui reste

| À faire | Effort | Quand |
|---|---|---|
| Phase 14.2 (notifications doc reviewed, bulk actions, audit log) | ~400 lignes | Si volume important |
| **Phase 10 — White-label multi-tenant** | 2500-10500 lignes | Quand revendeurs prêts |

Pour Phase 10, dis "**Phase 10**" et je propose le plan détaillé avec les 2
options chiffrées (single-DB tenant_id que je recommande, vs stancl/tenancy).
