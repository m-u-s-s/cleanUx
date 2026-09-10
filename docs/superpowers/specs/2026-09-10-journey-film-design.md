# « Brio — Le Film » : la section « De la réservation à la poignée de main »

Spec de conception — 2026-09-10.

Remplace le scrollytelling 3D de `resources/views/partials/journey.blade.php` par un film
photoréaliste de 14 plans, scrubbé au scroll, rendu dans un moteur WebGL, suivi d'une invitation
à télécharger les deux applications dont l'admin pilote les liens.

---

## 1. Pourquoi

La section actuelle explique le parcours d'une mission avec des dessins SVG et un monde 3D à
neuf stations (`resources/js/home-journey-core.js`). C'est propre, et c'est illustratif : un
visiteur voit des pictogrammes, pas une plateforme qui fonctionne. Sur une place de marché de
services, ce que le visiteur achète est **la confiance** — et la confiance ne se dessine pas,
elle se filme.

Objectif : en quelques secondes de scroll, le visiteur comprend ce que fait Brio, voit la preuve
que la mission est tracée de bout en bout, et sait où télécharger l'application qui le concerne.

## 2. Contraintes posées par l'utilisateur

1. **Photoréalisme documentaire.** Aucun personnage de synthèse, aucune illustration, aucun
   rendu 3D stylisé. Des gens qui ont l'air vrais, sur le terrain.
2. **Un seul mouvement continu** : galaxie → Terre → ville → rue → téléphone → mission →
   poignée de main → retrait jusqu'aux étoiles.
3. **Garder les facultés du moteur 3D** (profondeur, lumière, matière, post-traitement) mais
   **supprimer le globe et les neuf stations**.
4. **Séquence d'images sur canvas** pour le scrub, pas une balise `<video>`.
5. **14 plans**, budget ~630 crédits de génération vidéo.
6. Liens de téléchargement **pilotables par l'admin** : magasins, lien unique, QR, et textes.

## 3. Direction artistique

L'identité Brio (`public/images/brand/brio-provider-dark-512.png`) est du **marbre noir veiné
d'or** portant un **anneau doré** et le mot « Brio » en Allura. Cette identité ne se peint pas
par-dessus le réel : elle devient **la lumière et l'étalonnage**.

- **Palette** : noir d'encre (`#0b0f1a`), or ancien et ambre (`--cx-amber #ffb648`,
  `--cx-amber-deep #ff8a3d`). Le seul froid autorisé est le bleu de la Terre, qui arrive comme
  un événement au plan 2, et le vert de validation (`--brio-success #059669`) aux plans 9 et 11.
- **Lumière** : froide et immense en haut (l'espace, l'échelle), chaude et serrée en bas (un
  seuil de porte, une main). La bascule tombe au plan 9, quand on sonne.
- **Grammaire de caméra** : une seule descente. L'image de fin de chaque plan est l'image de
  début du suivant (`start_image` / `end_image`), donc aucune coupe franche. Le plan 14 rembobine
  la descente entière en un seul retrait.
- **Aucun texte cuit dans la vidéo**, sauf les écrans de téléphone. Titres, légendes et badges
  sont du HTML par-dessus : nets à tout DPI, traduits en six langues, lus par les lecteurs
  d'écran, modifiables sans regénérer un plan à 45 crédits.

### 3.1 Le bloc de style, répété dans chaque plan

> shot on ARRI Alexa 35 with vintage fast prime lenses, shallow depth of field, natural motion
> blur, fine 35mm film grain, true deep blacks lifted only by warm practical light, photoreal
> documentary cinematography, real people with real skin texture, visible pores and natural
> asymmetry, no beauty filter, no digital smoothing, no CGI look, no 3D render look, no
> illustration, no cartoon, no on-screen text, no subtitles, no watermark, no logos

## 4. Les 14 plans

Chaque plan porte un point fort de la plateforme. Les prompts sont donnés en anglais : les
modèles de génération y sont nettement plus précis, et aucun de ces mots n'atterrit à l'écran.

| # | Titre | Point fort porté |
|---|---|---|
| 01 | La galaxie | l'accroche |
| 02 | La Terre | 9 pays |
| 03 | L'Europe de nuit | 30+ métiers, un maillage réel |
| 04 | La rue | chez vous, ce soir |
| 05 | Le téléphone de la cliente | réserver prend 30 secondes |
| 06 | Le devis IA | fourchette de prix en 10 s, prix avant identité |
| 07 | Brio Provider accepte | un pro vérifié KYC et assuré |
| 08 | Le trajet | position et ETA en temps réel |
| 09 | Le QR de départ | mission horodatée, présence prouvée |
| 10 | Le travail | le métier, fait correctement |
| 11 | Le QR de fin | validé des deux côtés |
| 12 | Le paiement libéré | séquestre libéré quand c'est fait |
| 13 | La poignée de main | la confiance |
| 14 | Le retrait | la boucle se referme |

Les prompts intégraux de chaque plan vivent dans
`docs/superpowers/specs/2026-09-10-journey-film-prompts.md` — ils sont longs, et ils doivent
pouvoir être rejoués tels quels pour refaire un plan sans rejouer le reste.

## 5. Architecture technique

### 5.1 Trois couches

**Couche A — le moteur WebGL** (`resources/js/journey-film-core.js`)
three.js est conservé. La scène perd `buildGlobe()`, `buildStations()` et `STATION_GAP`, et
devient :
- un **plan unique plein cadre** texturé par la frame courante (`THREE.CanvasTexture` réalimentée) ;
- un **champ de poussière d'or** (`THREE.Points`, ~1200 points, dérive lente, parallaxe liée au scroll) ;
- un **shader de post-traitement** : grain argentique animé, vignette, aberration chromatique
  douce sur les bords, bloom sur les hautes lumières, courbure imperceptible.

**Couche B — le décodeur de frames** (`resources/js/journey-film-frames.js`)
- **350 frames** (14 plans × 25), en deux tailles : 1280×720 et 768×432 ;
- **AVIF seul**. Le repli WebP prévu a été abandonné : le poster et le stepper couvrent déjà
  les navigateurs sans AVIF, et doubler 700 fichiers pour ~4 % du parc ne se défendait pas.
  La détection se fait en décodant une image AVIF d'un pixel au démarrage ;
- chargement **par vagues de chapitre** : la vague 1 (25 frames, ~500 Ko en bureau, ~235 Ko en
  mobile) suffit à démarrer, la vague k+1 se précharge pendant la traversée du chapitre k ;
- `scroll → index` par ScrollTrigger, **fondu entre les deux frames voisines** sur un canvas 2D
  — c'est ce canvas, et lui seul, qui monte au GPU. Garder 350 textures 1280×720 en mémoire
  graphique coûterait 1,2 Go ;
- maintien de la dernière frame décodée tant que la suivante n'est pas prête (jamais de trou noir) ;
- l'attente de la première image est **événementielle, jamais un sondage en `requestAnimationFrame`** :
  rAF ne tourne pas dans un onglet que le navigateur ne peint pas, et un sondage y restait
  bloqué à vie sans même honorer son propre délai de garde.

**Couche C — le récit HTML**
Titre, légende du plan courant, barre de progression, puces — puis, hors film, les deux cartes
de téléchargement.

### 5.2 Les quatre replis, dans l'ordre

1. **Pas de WebGL** → le même décodeur dessine dans un `<canvas>` 2D. On perd le grain et la
   poussière, on garde le film.
2. **Réseau coupé / décodage impossible** → le poster (frame 1) et le stepper `<ol>` existant.
3. **`prefers-reduced-motion`** → **aucune frame chargée**, stepper statique. C'est déjà le
   comportement de `home-journey.js:22` ; il est conservé tel quel.
4. **`navigator.connection.saveData`** → poster et stepper.

### 5.3 Le pipeline d'assets

1. **2 fiches de casting** (`gpt_image_2`, 2K, qualité haute) : le plombier et la cliente.
   Sans elles, les visages changent d'un plan à l'autre.
2. **14 images-clés** (`gpt_image_2`, 2K, qualité haute), chacune référencée aux fiches de
   casting. L'image-clé *n+1* sert d'`end_image` au plan *n*.
3. **14 plans** (`flux_3_video`, 5 s, 1080p, `generate_audio: false`, `start_image` +
   `end_image`).
4. **ffmpeg 8.1.1** (présent en local), via `scripts/journey-film/construire-les-frames.sh` :
   recadrage 1920×1088 → 1920×1080 (le modèle rend sur un multiple de 16), extraction d'**exactement
   25 images par plan** (`select='not(mod(n,5))'` — un `fps=` aurait dérivé et décalé les
   chapitres), deux tailles, export AVIF, poster WebP + JPEG, `frames.json`.

   **Deux points du plan initial ont été abandonnés après mesure.** L'étalonnage chaud
   différentiel sur les plans 9 à 14 : inutile, le modèle a tenu le grade tout seul, et une
   courbe de plus n'aurait fait qu'écraser les noirs. La cadence variable : le film est écrit
   en mouvements lents et continus, une cadence constante suffit — et elle garantit que chaque
   chapitre tombe sur un multiple de 25.

Sortie : `public/images/journey-film/` + `frames.json` (nombre de frames, chapitres, ancres des
légendes).

### 5.4 Budget

| Poste | Quantité | Crédits |
|---|---|---|
| Fiches de casting | 2 × 6,5 | 13 |
| Images-clés | 14 × 6,5 | 91 |
| Reprise des 2 écrans sans accents | 2 × 6,5 | 13 |
| Plans vidéo | 14 × 45 | 630 |
| **Dépensé** | | **747 sur 2637** |

### 5.5 Poids servi — mesuré

| | Par chapitre | Film entier |
|---|---|---|
| Bureau (1280×720) | ~500 Ko | **7,0 Mo** |
| Mobile (768×432) | ~235 Ko | **3,3 Mo** |

**Jamais téléchargé par qui ne descend pas** : les vagues suivent le scroll. La section est sous
la ligne de flottaison, rien de tout cela n'entre dans le LCP, et `prefers-reduced-motion` comme
`saveData` ne chargent **aucune** frame — vérifié à 0 requête par `tools/visual-qa/verif-film.mjs`.

## 6. Le module « Applications mobiles »

### 6.1 Les liens — `Parametre`

Le magasin clé/valeur existe déjà (`app/Models/Parametre.php`). Neuf clés :

| Clé | Rôle |
|---|---|
| `apps_client_ios` | App Store, application client |
| `apps_client_android` | Google Play, application client |
| `apps_client_smartlink` | Lien unique client (redirige selon l'appareil) |
| `apps_prestataire_ios` | App Store, application prestataire |
| `apps_prestataire_android` | Google Play, application prestataire |
| `apps_prestataire_smartlink` | Lien unique prestataire |
| `apps_client_visible` | La carte client s'affiche (`0`/`1`) |
| `apps_prestataire_visible` | La carte prestataire s'affiche (`0`/`1`) |
| `apps_qr_actif` | Les QR sont rendus sur écran large (`0`/`1`) |

**Règle** : un lien vide masque son bouton. Aucun lien mort n'est jamais affiché au public.
Seules les URL `https://` sont acceptées à l'enregistrement.

### 6.2 Les textes — le système i18n existant

Les accroches vivent dans `lang/{fr,nl,en,de,es,it}/apps.php`. Elles sont donc **déjà**
modifiables depuis le centre de traductions admin (`app/Livewire/Admin/I18n/TranslationsCenter.php`,
qui écrit dans `translation_overrides`).

L'écran « Applications mobiles » édite **les mêmes clés au même endroit** : une seule source de
vérité (`translation_overrides`), deux portes d'entrée. On ne crée pas un second magasin de
textes — le dépôt en a déjà payé le prix ailleurs.

### 6.3 Les QR

Générés à la volée en SVG depuis le lien, mis en cache. Rien à téléverser, rien à synchroniser :
changer le lien change le QR.

### 6.4 L'écran admin

- `App\Livewire\Admin\ApplicationsMobiles`, route `/admin/applications-mobiles`.
- Garde `manage-platform`, **posée dans `boot()`** : `/livewire/update` ne rejoue aucun
  intermédiaire de route (voir `IdentiteLegale::boot()`).
- **Inscrit dans `config/modules.php`**, contexte `admin`, catégorie `plateforme`. Sans cette
  ligne l'écran est injoignable autrement qu'en tapant l'URL — c'est la famille de défauts
  dominante de ce dépôt, et `/admin/identite-legale` en souffre déjà.
- Descripteur `App\Admin\Resources\MobileAppsResource` pour que la console admin RN le couvre.

### 6.5 Le composant public

`<x-apps.telechargement>` — deux cartes de verre (client / prestataire), chacune avec son
accroche, ses boutons de magasin, son lien unique, et son QR sur écran large. Les jetons
`--brio-*` uniquement ; mode sombre traité ; `rel="noopener noreferrer"` sur chaque lien sortant.

## 7. Les dettes corrigées au passage

**`resources/css/journey.css`** est antérieure à la refonte de 2026-08 : `#6366f1`, `#0f172a`,
`#f8fafc` écrits en dur, mode sombre absent. Réécrite sur les jetons.

**`position: sticky` était cassé sur toute la plateforme sous 768 px.** `base.css` pose
`overflow-x: hidden` sur `body.cx-shell`. Sur grand écran c'est inoffensif : `html` y est
`visible`, donc l'overflow du corps **se propage à la fenêtre** et le corps reste inerte. Dès que
`responsive.css` pose `overflow-x: clip` sur `html` sous 768 px, la propagation cesse : le corps
devient un conteneur de défilement et `position: sticky` casse pour tous ses descendants.
Corrigé en rendant `overflow-x: visible` au corps sous 768 px — `html` clippe déjà. Les 12 pages
publiques du harnais mobile restent au vert.

**Le traducteur ne relisait pas un override dans la même requête.** `TranslationOverrideLoader`
vidait le cache applicatif, mais pas les groupes que le traducteur de Laravel garde en mémoire du
processus : un texte corrigé puis réaffiché servait encore l'ancienne valeur.

## 8. Tests

| Test | Ce qu'il prouve |
|---|---|
| `ApplicationsMobilesTest` | Les neuf clés se lisent et s'écrivent. Un non-admin est refusé **et** un admin passe — sans ce témoin positif, le test de refus mesurerait une panne. |
| `TelechargementAppsTest` | Lien vide → bouton absent. Lien rempli → bouton présent avec `rel="noopener"`. URL non-`https` refusée à l'enregistrement. |
| `JourneyFilmAssetsTest` | `frames.json` existe, son `count` égale le nombre de fichiers réellement présents, les 14 légendes existent dans les six locales. |
| Playwright (harness existant) | À 390 px et 1440 px : le poster s'affiche sans JS, le stepper s'affiche en `prefers-reduced-motion`, aucune barre de défilement horizontale. |

## 9. Ce qui n'est délibérément pas fait

- **Pas de son.** Une vidéo scrubbée au scroll est muette par nature ; générer de l'audio
  coûterait des crédits pour un flux que personne n'entendra.
- **Pas de version 4K.** Le gain est invisible sous 1440 px et triplerait le poids servi.
- **Pas de génération de frames à la volée côté serveur.** Les frames sont un asset de build,
  versionné comme tel.
