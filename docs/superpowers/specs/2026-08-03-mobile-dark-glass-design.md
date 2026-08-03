# Mode sombre mobile — assainissement, puis traitement verre

**Date :** 2026-08-03
**Statut :** validé (choix utilisateur : réparer d'abord, gouttes Skia, les quatre surfaces, les deux apps, ambiance « nuit profonde à lueur de marque »)

## Le problème, et ce qu'il n'est pas

La demande est un fond sombre luxueux, un effet verre avec gouttes d'eau, et des boutons
translucides. La reconnaissance a montré qu'on ne peut pas la satisfaire directement.

**Le mode sombre existe et ne fonctionne quasiment pas.** `useColorScheme` et `useThemeColors`
sont en place, `AppearanceScreen` propose bien les trois modes, et `Screen` bascule le fond. Mais
sur **137 fichiers d'interface** (88 provider+shared, 49 client), **7 seulement** consultent
`useThemeColors()`. Les autres écrivent leurs couleurs en dur : **~139 occurrences** de textes
quasi-noirs (`colors.surface[900]`) et de fonds blancs.

Le résultat visible : en mode sombre, le fond devient sombre et les textes restent noirs.
`AppearanceScreen` en est l'illustration — l'écran qui propose « Sombre » devient illisible dès
qu'on le choisit.

Poser une couche de verre là-dessus produirait quelque chose de très beau et toujours illisible.
D'où l'ordre : assainir, puis habiller.

**Aucune dépendance graphique n'est installée** — ni `expo-blur`, ni `expo-linear-gradient`, ni
`react-native-svg`, ni Skia.

## Ce sur quoi on s'appuie plutôt que d'inventer

`config` du thème porte déjà une palette nuit, issue du travail éditorial :
`colors.mode.showcase` = `night #070b14`, `nightSoft #0c1322`, `panel #111a2e`, `text #e8eefc`,
`muted #93a4c6`. L'ambiance retenue — nuit profonde à lueur de marque — s'y adosse, avec la lueur
prise dans `colors.brand` (indigo `#6366f1`). Introduire une seconde palette sombre concurrente
ferait diverger deux définitions du même noir.

## Architecture

### Lot 1 — assainir (prérequis)

**Étendre `useThemeColors()`** avec les jetons qui manquent aujourd'hui et que le lot 2 exigera :
surface de verre, bordure lumineuse, texte sur verre, voile de superposition.

**Migrer les 137 fichiers** vers ces jetons. Mécanique, mais volumineux : chaque
`color: colors.surface[900]` devient `color: theme.text`, chaque fond blanc devient `theme.card`.

**Le garde-fou qui fait tenir la migration** — un test qui échoue si un composant écrit une
couleur en dur là où un jeton existe. Sans lui, la dérive qui a produit ces 139 occurrences
recommencera : personne ne l'a fait exprès, c'est simplement ce qui arrive quand rien ne s'y
oppose. Le test liste ses exceptions légitimes (la palette elle-même, les mocks, les couleurs de
marque non thématiques) plutôt que de les deviner.

### Lot 2 — habiller, en mode sombre uniquement

Trois technologies, chacune là où elle est la bonne. Ce découpage est le cœur de la conception :

| Surface | Rendu par | Pourquoi celle-ci |
|---|---|---|
| Fond d'écran | `@shopify/react-native-skia` | Dégradé nuit + gouttes procédurales avec réfraction et ruissellement lent. Un seul canvas par écran. |
| Cartes, boutons, barres | `expo-blur` | Ces surfaces floutent **ce qui est derrière elles** — c'est la définition de `BlurView`. Skia redessinerait au lieu de flouter. |
| Bords lumineux | `expo-linear-gradient` | Le liseré clair-en-haut / sombre-en-bas qui fait lire « verre » plutôt que « rectangle gris ». |

**Skia ne dessine que le fond.** Le mettre aussi sous chaque carte multiplierait les canvas sans
rien apporter : une carte translucide a besoin de flouter l'arrière-plan, pas de le reproduire.

**Composants livrés :**
- `LuxeBackground` — le canvas Skia : dégradé nuit, lueur de marque diffuse en haut, gouttes.
- `GlassSurface` — le panneau translucide réutilisable (flou + voile + bord lumineux).
- La variante `glass` du `Button` existant, plutôt qu'un nouveau composant : les appelants gardent
  la même interface, et une variante de plus ne fait pas diverger deux boutons.
- Le traitement des barres — onglets, en-têtes, feuilles — par `GlassSurface`.

### Les garde-fous, parce que c'est une application de terrain

1. **Le mode clair n'est pas touché.** Le luxe est un traitement du sombre ; le clair garde son
   design actuel. Un prestataire en plein soleil a besoin de contraste, pas de translucidité.
2. **`useReducedMotion` est respecté** — le kit UI l'expose déjà. Mouvement réduit : gouttes
   figées, aucun ruissellement.
3. **Un repli explicite.** L'app provider affiche une carte et des listes virtualisées ; si le
   canvas coûte trop cher, `LuxeBackground` retombe sur un dégradé statique **sans changer la
   structure de l'écran** — le repli ne doit pas déplacer un seul élément.
4. **Le contraste reste mesuré, pas supposé.** Un texte sur verre translucide peut passer sous le
   seuil lisible selon ce qui défile dessous. Le voile sous le flou a une opacité plancher qui
   garantit le contraste du texte quel que soit l'arrière-plan.

## Le risque que la spec ne peut pas écarter

**La compatibilité de `@shopify/react-native-skia` avec Expo SDK 56 / React Native 0.85 n'est pas
vérifiée.** C'est une dépendance native lourde sur une pile récente.

La première tâche d'implémentation est donc : installer Skia et faire tourner un canvas minimal.
Si ça ne passe pas, on bascule sur des **gouttes en texture** — une image PNG en superposition,
rendu très proche, coût d'exécution nul — sans avoir écrit la couche par-dessus. Le reste de la
conception ne change pas : seul le corps de `LuxeBackground` diffère.

## Ce qui n'est pas fait

- **Aucune refonte de la maquette.** On change les couleurs et les matières, pas les dispositions.
- **L'administration mobile n'a pas de traitement particulier.** Elle hérite du thème comme le
  reste ; une console d'administration n'a pas besoin de gouttes d'eau.
- **Le mode clair ne reçoit pas d'équivalent verre.** Ce serait un second design à maintenir.
