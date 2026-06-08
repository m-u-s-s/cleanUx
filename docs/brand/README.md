# brio — Brand kit

> **brio** — la marketplace multi-métiers à la demande **et** sous contrat.
> Baseline : *« Chaque métier, avec brio. »* — EN : *« Any job, done with brio. »*

## Fichiers

| Fichier | Usage |
|---|---|
| `brio-icon.svg` | App icon (iOS/Android), avatar, carré arrondi 256px |
| `brio-favicon.svg` | Favicon / petites tailles (mark simplifié, 64px) |
| `brio-icon-mono.svg` | Mark monochrome (tampon, gravure, fond uni) — change le `fill` |
| `brio-wordmark.svg` | Logo horizontal, fond clair |
| `brio-wordmark-dark.svg` | Logo horizontal, fond sombre |

Tous en **SVG vectoriel** = redimensionnable à l'infini, sans perte.

## Palette (alignée au design system du produit)

| Rôle | Hex |
|---|---|
| Indigo (primaire) | `#6366f1` → `#4f46e5` |
| Violet (dégradé) | `#8b7bff` |
| Ambre (accent / étincelle) | `#ffb648` · `#f59e0b` |
| Ardoise (texte) | `#0f172a` |
| Fond sombre | `#0b1020` |

Dégradé du mark : indigo `#6366f1` → violet `#8b7bff` (diagonale). Étincelle d'accent : ambre `#ffb648`.

## Typographie

- **Space Grotesk** (700) pour le wordmark et les titres (déjà dans la stack produit).
- **Figtree / Inter** pour le texte courant.

> Le wordmark utilise `<text>` avec Space Grotesk. Pour une version 100 % indépendante de la police
> (impression, partenaires), vectorise le texte (« outline / convertir en tracé ») dans un éditeur, ou
> demande-moi la version path-only.

## Le symbole

Une **étincelle 4 branches** = le *brio* (excellence) **et** l'étincelle du *match* instantané
client ↔ pro (le cœur du dispatch). L'accent ambre = l'étincelle humaine. Posé sur le dégradé
indigo→violet déjà en production : la marque et le produit parlent d'une seule voix.

## Exporter en PNG

Le SVG est le format maître. Pour générer des PNG (stores, réseaux) :

```bash
# via rsvg-convert (librsvg)
rsvg-convert -w 1024 -h 1024 brio-icon.svg -o brio-icon-1024.png

# ou via Inkscape
inkscape brio-icon.svg --export-type=png -w 1024 -h 1024 -o brio-icon-1024.png

# ou n'importe quel convertisseur SVG→PNG en ligne
```

Tailles utiles : app icon `1024×1024`, favicon `32/180/512`, réseaux `400×400`.

## Domaines

`brio.com` (BRIO, jouets, depuis 1994) et toutes les combinaisons courantes
(`getbrio`/`trybrio`/`usebrio`/`brioapp`/`briohq`/`briohome`, `brio.be`) sont **prises**.
Pistes ownables : un TLD « métier » (`brio.work`, `brio.team`, `brio.pro`, `brio.app`) intégré à la
marque, ou une variante coinée à `.com` propre. **Vérifier la marque** (EUIPO / BOIP Benelux) avant
de t'engager : BRIO = classe 28 (jouets) ; une marketplace de services vise plutôt les classes 35/39/42
— coexistence souvent possible mais à faire valider.
