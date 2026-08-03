# Mode sombre mobile — assainissement puis traitement verre

> **Pour les exécutants :** ce plan s'exécute tâche par tâche. Chaque case `- [ ]` est une étape de
> 2 à 5 minutes. Le portail de vérification doit être vert avant de passer à la tâche suivante.

**But :** rendre le mode sombre réellement utilisable sur les deux applications mobiles, puis lui
donner le traitement verre — fond nuit avec gouttes, cartes et boutons translucides.

**Architecture :** `useThemeColors()` devient la source unique des couleurs. Les feuilles de style
gardent la mise en page (statique) ; les couleurs passent en style en ligne, alimentées par le
hook. Un test refuse toute nouvelle couleur codée en dur. La couche verre s'ajoute ensuite :
Skia pour le fond, `expo-blur` pour les surfaces, `expo-linear-gradient` pour les liserés.

**Pile :** Expo SDK 56, React Native 0.85, TypeScript strict, Jest + Testing Library.

## Contraintes globales

- Expo a changé : consulter <https://docs.expo.dev/versions/v56.0.0/> avant d'ajouter une
  dépendance native (`mobile/client/AGENTS.md`).
- Les teintes de nuit viennent de `colors.mode.showcase` (`night #070b14`, `nightSoft #0c1322`,
  `panel #111a2e`, `text #e8eefc`, `muted #93a4c6`) et la lueur de `colors.brand[500]` (`#6366f1`).
  **Ne pas introduire de seconde palette sombre.**
- **Le mode clair n'est pas modifié.** Toute tâche qui change un rendu en clair est hors sujet.
- Réglages initiaux issus de l'aperçu validé : **28 gouttes, flou 18 px, voile 0.06, lueur 0.30**.
- `useReducedMotion` (déjà dans `@/ui`) est respecté partout où quelque chose bouge.
- Commentaires et libellés en français, expliquant le POURQUOI.
- Portail par tâche : `npm run typecheck` et `npm test` dans **les deux** applications.

## Le patron de migration, à appliquer partout

`StyleSheet.create` est évalué une fois au chargement du module : il ne peut pas connaître le
thème. Le patron retenu — celui qu'emploient déjà les 13 fichiers thème-conscients — sépare les
deux natures :

```tsx
// AVANT — la couleur est figée dans la feuille de style
const styles = StyleSheet.create({
  titre: { fontSize: 18, fontWeight: '700', color: colors.surface[900] },
});

// APRÈS — la feuille garde la MISE EN PAGE, le hook fournit la COULEUR
export function MonEcran() {
  const theme = useThemeColors();

  return <Text style={[styles.titre, { color: theme.text }]}>Titre</Text>;
}

const styles = StyleSheet.create({
  titre: { fontSize: 18, fontWeight: '700' },   // plus aucune couleur ici
});
```

Ce n'est donc **pas un remplacement de texte** : chaque fichier demande de retirer la couleur de
la feuille et de la réinjecter au point d'usage.

---

## Fichiers

**Créés**
- `mobile/provider/__tests__/theme/noHardcodedColors.test.ts` — le garde-fou
- `mobile/shared/src/ui/LuxeBackground.tsx` — fond nuit + gouttes
- `mobile/shared/src/ui/GlassSurface.tsx` — panneau translucide réutilisable
- `mobile/shared/src/theme/glass.ts` — jetons de la matière verre

**Modifiés**
- `mobile/shared/src/theme/useThemeColors.ts` — jetons étendus
- `mobile/shared/src/ui/Button.tsx` — variante `glass`
- `mobile/shared/src/ui/*.tsx` et les écrans des deux apps — migration

---

### Tâche 0 : lever le doute sur Skia

**Pourquoi en premier :** c'est le seul inconnu du chantier. Le lever coûte quinze minutes ;
découvrir l'incompatibilité après avoir écrit `LuxeBackground` coûte la tâche entière.

- [ ] **Étape 1 : installer**

```bash
cd mobile/provider && npx expo install @shopify/react-native-skia
```

- [ ] **Étape 2 : écrire le test de fumée**

`mobile/provider/__tests__/theme/skiaDisponible.test.ts` :

```ts
/**
 * Skia est une dépendance NATIVE sur une pile récente (Expo 56 / RN 0.85). Ce test ne prouve pas
 * qu'elle rend correctement sur un appareil — il prouve qu'elle se charge, ce qui suffit à
 * décider entre les gouttes procédurales et le repli en texture.
 */
it('le module Skia se charge', () => {
  const skia = require('@shopify/react-native-skia');

  expect(typeof skia.Canvas).toBe('function');
  expect(typeof skia.Circle).toBe('function');
});
```

- [ ] **Étape 3 : lancer**

Run : `cd mobile/provider && npx jest __tests__/theme/skiaDisponible.test.ts`

**Si le test passe** → la tâche 7 écrit les gouttes en Skia.
**S'il échoue** → désinstaller (`npm uninstall @shopify/react-native-skia`), supprimer ce test, et
la tâche 7 écrit le repli en texture. **Noter le choix dans le message de commit** : c'est
l'information que quelqu'un cherchera dans six mois.

- [ ] **Étape 4 : commit** — `chore(mobile): trancher la disponibilité de Skia sur Expo 56`

---

### Tâche 1 : étendre les jetons de thème

**Fichiers :** Modifier `mobile/shared/src/theme/useThemeColors.ts` ·
Test `mobile/provider/__tests__/theme/jetons.test.ts`

**Interfaces produites :** `useThemeColors()` rend, en plus de l'existant —
`glass`, `glassStrong`, `glassBorder`, `textOnGlass`, `mutedOnGlass`, `glow`, `isDark`.

- [ ] **Étape 1 : écrire le test qui échoue**

```ts
import { renderHook } from '@testing-library/react-native';
import { useThemeColors } from '@/theme/useThemeColors';

jest.mock('@/theme/useColorScheme', () => ({
  useColorScheme: () => ({ colorScheme: 'dark', mode: 'dark', setMode: jest.fn() }),
}));

describe('jetons de thème en mode sombre', () => {
  it('expose les jetons dont la couche verre a besoin', () => {
    const { result } = renderHook(() => useThemeColors());

    for (const jeton of ['glass', 'glassStrong', 'glassBorder', 'textOnGlass', 'mutedOnGlass', 'glow']) {
      expect(result.current).toHaveProperty(jeton);
      expect(String(result.current[jeton as keyof typeof result.current])).not.toBe('');
    }
  });

  it('dit s’il fait sombre, pour que les composants n’aient pas à le redéduire', () => {
    // Sans ce drapeau, chaque composant réimporterait useColorScheme et comparerait la chaîne —
    // trois occasions de se tromper au lieu d'une.
    expect(useThemeColorsIsDark()).toBe(true);
  });

  it('les surfaces de verre sont translucides, jamais opaques', () => {
    const { result } = renderHook(() => useThemeColors());

    // Une surface opaque n'est plus du verre : elle masquerait le fond au lieu de le filtrer.
    expect(result.current.glass).toMatch(/rgba\(/);
    expect(result.current.glassBorder).toMatch(/rgba\(/);
  });
});

function useThemeColorsIsDark(): boolean {
  const { result } = renderHook(() => useThemeColors());

  return result.current.isDark;
}
```

- [ ] **Étape 2 : lancer, constater l'échec** —
      `cd mobile/provider && npx jest __tests__/theme/jetons.test.ts`

- [ ] **Étape 3 : étendre le hook**

```ts
export function useThemeColors() {
  const { colorScheme } = useColorScheme();
  const isDark = colorScheme === 'dark';
  const nuit = colors.mode.showcase;

  return {
    isDark,

    // Jetons existants — inchangés, sauf le fond sombre qui adopte la palette nuit du projet
    // plutôt que le gris neutre : c'est elle qui porte l'ambiance validée.
    bg: isDark ? nuit.night : colors.surface[50],
    card: isDark ? nuit.panel : '#ffffff',
    cardElevated: isDark ? nuit.nightSoft : '#ffffff',
    text: isDark ? nuit.text : colors.surface[900],
    textSecondary: isDark ? nuit.muted : colors.surface[600],
    textMuted: isDark ? 'rgba(147, 164, 198, 0.72)' : colors.surface[400],
    border: isDark ? 'rgba(232, 238, 252, 0.10)' : colors.surface[200],
    inputBg: isDark ? 'rgba(232, 238, 252, 0.06)' : colors.surface[50],

    /*
     * La matière verre.
     *
     * `glass` porte un PLANCHER d'opacité : sous ce seuil, un texte posé dessus devient illisible
     * dès que quelque chose de clair défile derrière. Le contraste est garanti par le voile, pas
     * supposé par le flou — un flou ne fonce rien, il mélange.
     */
    glass: isDark ? 'rgba(232, 238, 252, 0.06)' : 'rgba(255, 255, 255, 0.72)',
    glassStrong: isDark ? 'rgba(232, 238, 252, 0.10)' : 'rgba(255, 255, 255, 0.86)',
    glassBorder: isDark ? 'rgba(232, 238, 252, 0.14)' : 'rgba(15, 23, 42, 0.08)',
    textOnGlass: isDark ? nuit.text : colors.surface[900],
    mutedOnGlass: isDark ? nuit.muted : colors.surface[600],
    glow: isDark ? 'rgba(99, 102, 241, 0.30)' : 'transparent',
  };
}
```

- [ ] **Étape 4 : relancer, puis commit**

```bash
git add mobile/shared/src/theme/useThemeColors.ts mobile/provider/__tests__/theme/jetons.test.ts
git commit -m "feat(mobile): étendre les jetons de thème pour la matière verre"
```

---

### Tâche 2 : le garde-fou anti-couleur-en-dur

**Pourquoi AVANT la migration :** il donne la liste exacte des fichiers à traiter, et il rougit
tant qu'elle n'est pas vide. La migration est pilotée par lui, pas par une liste que je recopie.

**Fichiers :** Créer `mobile/provider/__tests__/theme/noHardcodedColors.test.ts`

- [ ] **Étape 1 : écrire le garde-fou**

```ts
/**
 * Aucune couleur codée en dur dans un composant.
 *
 * POURQUOI. Le mode sombre existait déjà et ne fonctionnait pas : sur 137 fichiers d'interface, 7
 * consultaient le thème. Les autres écrivaient `color: colors.surface[900]` — du quasi-noir, sur
 * un fond devenu sombre. Personne ne l'a fait exprès : c'est ce qui arrive quand rien ne s'y
 * oppose. Ce test est ce qui s'y oppose.
 *
 * CE QU'IL AUTORISE : les couleurs SÉMANTIQUES (succès, alerte, danger, marque) qui gardent leur
 * sens sur les deux fonds, et les fichiers listés en exception avec leur raison.
 */
import fs from 'fs';
import path from 'path';

const RACINE = path.join(__dirname, '..', '..', '..');

/** Fichiers autorisés à porter des couleurs en dur, et pourquoi. */
const EXCEPTIONS: Record<string, string> = {
  'shared/src/theme/colors.ts': 'la palette elle-même',
  'shared/src/theme/useThemeColors.ts': 'la fabrique de jetons',
  'shared/src/ui/authShell.tsx': 'écran d’accueil à identité visuelle propre, hors thème',
};

/** Familles sémantiques : leur sens ne dépend pas du fond. */
const SEMANTIQUES = /colors\.(success|warning|danger|brand|accent)\[/;

/** Ce qu'on traque : une couleur neutre figée sur une propriété de couleur. */
const INTERDIT =
  /(color|backgroundColor|borderColor|borderTopColor|borderBottomColor|tintColor)\s*:\s*(colors\.surface\[|'#|"#)/;

function fichiers(dossier: string): string[] {
  const sortie: string[] = [];

  for (const entree of fs.readdirSync(dossier, { withFileTypes: true })) {
    const complet = path.join(dossier, entree.name);

    if (entree.isDirectory()) {
      if (['node_modules', '__tests__', '__mocks__', '.expo'].includes(entree.name)) continue;
      sortie.push(...fichiers(complet));
    } else if (/\.tsx?$/.test(entree.name)) {
      sortie.push(complet);
    }
  }

  return sortie;
}

describe('couleurs codées en dur', () => {
  const cibles = ['provider/src', 'shared/src', 'client/src']
    .flatMap((d) => fichiers(path.join(RACINE, d)));

  it('la recherche couvre bien les trois arborescences', () => {
    // Un balayage qui ne trouve plus aucun fichier rendrait l'assertion suivante vraie pour une
    // mauvaise raison.
    expect(cibles.length).toBeGreaterThan(100);
  });

  it('aucun composant ne fige une couleur neutre', () => {
    const fautifs: string[] = [];

    for (const chemin of cibles) {
      const relatif = path.relative(RACINE, chemin).split(path.sep).join('/');
      if (EXCEPTIONS[relatif]) continue;

      const source = fs.readFileSync(chemin, 'utf8');

      source.split('\n').forEach((ligne, i) => {
        if (SEMANTIQUES.test(ligne)) return;
        if (INTERDIT.test(ligne)) fautifs.push(`${relatif}:${i + 1}`);
      });
    }

    expect(fautifs).toEqual([]);
  });
});
```

- [ ] **Étape 2 : lancer et CONSERVER la liste**

Run : `cd mobile/provider && npx jest __tests__/theme/noHardcodedColors.test.ts`
Attendu : échec, avec la liste complète `fichier:ligne`. **C'est la feuille de route des tâches 3
à 6.** La copier dans un fichier de travail.

- [ ] **Étape 3 : commit du garde-fou seul** — il est rouge, et c'est voulu : il décrit une dette
      qui existe déjà.

```bash
git add mobile/provider/__tests__/theme/noHardcodedColors.test.ts
git commit -m "test(mobile): refuser les couleurs codées en dur — rouge tant que la dette existe"
```

---

### Tâches 3 à 6 : la migration, par lots

Même recette pour chaque lot. Le garde-fou de la tâche 2 dit ce qui reste ; on s'arrête quand il
est vert.

| Tâche | Périmètre | Pourquoi cet ordre |
|---|---|---|
| **3** | `shared/src/ui/*` | Le point de levier : ces composants sont consommés par les deux apps. Les traiter d'abord réduit mécaniquement la dette des écrans. |
| **4** | `provider/src/screens/*` et `provider/src/**` | L'app la plus utilisée en conditions réelles. |
| **5** | `client/src/**` | Même traitement, app cliente. |
| **6** | Le reliquat que le garde-fou signale encore | Ce qui a été manqué, plus les fichiers à décider en exception. |

Pour chaque lot :

- [ ] **Étape 1 : lancer le garde-fou** et prendre les fichiers du lot dans sa liste.
- [ ] **Étape 2 : migrer fichier par fichier**, en appliquant le patron : la couleur sort de
      `StyleSheet.create`, le composant appelle `useThemeColors()`, la couleur revient en style en
      ligne au point d'usage.
- [ ] **Étape 3 : relancer le garde-fou** — la liste doit avoir raccourci d'autant.
- [ ] **Étape 4 : lancer les suites des deux apps.**

```bash
cd mobile/provider && npm run typecheck && npm test
cd ../client && npm run typecheck && npm test
```

- [ ] **Étape 5 : commit du lot** — message nommant le périmètre et le nombre d'occurrences
      retirées.

**Cas particulier à traiter dans le lot 4 :** `AppearanceScreen.tsx` écrit `colors.surface[900]`
sur ses libellés. C'est l'écran qui PROPOSE le mode sombre et devient illisible quand on le
choisit — le traiter en premier du lot, et le vérifier à l'œil dans les deux modes.

---

### Tâche 7 : le fond nuit

**Fichiers :** Créer `mobile/shared/src/ui/LuxeBackground.tsx` ·
Test `mobile/provider/__tests__/theme/LuxeBackground.test.tsx`

**Interfaces produites :**
`<LuxeBackground intensity?: number />` — occupe son parent en absolu, ne rend **rien** en mode
clair.

- [ ] **Étape 1 : écrire les tests qui échouent**

```tsx
describe('LuxeBackground', () => {
  it('ne rend rien en mode clair', () => {
    modeClair();
    const { toJSON } = render(<LuxeBackground />);

    // Le luxe est un traitement du SOMBRE. En clair, un prestataire au soleil a besoin de
    // contraste, pas de translucidité.
    expect(toJSON()).toBeNull();
  });

  it('rend le fond en mode sombre', () => {
    modeSombre();
    render(<LuxeBackground />);

    expect(screen.getByTestId('luxe-background')).toBeTruthy();
  });

  it('fige les gouttes quand le système demande un mouvement réduit', () => {
    modeSombre();
    mockReducedMotion(true);
    render(<LuxeBackground />);

    expect(screen.getByTestId('luxe-background').props.accessibilityLabel)
      .toContain('sans animation');
  });

  it('retombe sur un dégradé simple quand le rendu riche est indisponible', () => {
    modeSombre();
    mockSkiaIndisponible();
    render(<LuxeBackground />);

    // Le repli ne doit RIEN déplacer : même testID, mêmes dimensions, seule la matière change.
    expect(screen.getByTestId('luxe-background')).toBeTruthy();
  });
});
```

- [ ] **Étape 2 : lancer, constater l'échec.**
- [ ] **Étape 3 : écrire le composant** — dégradé `nightSoft → night`, lueur de marque radiale en
      haut à droite (opacité 0.30), 28 gouttes. Seules les gouttes de rayon > 8 glissent : sur une
      vitre, les petites tiennent par tension superficielle, et les faire toutes descendre donne
      « des cercles qui tombent », pas de l'eau.
- [ ] **Étape 4 : relancer, typecheck, commit.**

---

### Tâche 8 : la surface de verre

**Fichiers :** Créer `mobile/shared/src/ui/GlassSurface.tsx` ·
Test `mobile/provider/__tests__/theme/GlassSurface.test.tsx`

**Interfaces produites :**
`<GlassSurface strong?: boolean style?: ViewStyle>{children}</GlassSurface>`

- [ ] **Étape 1 : écrire les tests** — en clair, rend une `View` ordinaire avec `theme.card` (pas
      de flou : le mode clair n'est pas touché) ; en sombre, un `BlurView` avec le voile et le
      liseré ; le voile n'est jamais absent, même quand le flou est indisponible.
- [ ] **Étape 2 : lancer, constater l'échec.**
- [ ] **Étape 3 : écrire le composant.**
- [ ] **Étape 4 : relancer, commit.**

---

### Tâche 9 : la variante `glass` du bouton

**Fichiers :** Modifier `mobile/shared/src/ui/Button.tsx` ·
Test `mobile/provider/__tests__/theme/ButtonGlass.test.tsx`

**Pourquoi une variante et non un composant :** `Button` porte déjà huit variantes et une
interface que tout le monde connaît. Un `GlassButton` séparé finirait par diverger sur la taille,
l'état de chargement ou l'accessibilité.

- [ ] **Étape 1 : écrire les tests** — la variante existe ; en mode clair elle se comporte comme
      `secondary` (le clair n'est pas touché) ; le libellé garde un contraste suffisant sur le
      voile ; `loading` et `disabled` fonctionnent comme sur les autres variantes.
- [ ] **Étape 2 : lancer, constater l'échec.**
- [ ] **Étape 3 : ajouter la variante.**
- [ ] **Étape 4 : relancer, commit.**

---

### Tâche 10 : les barres

**Fichiers :** Modifier `mobile/provider/src/navigation/TabNavigator.tsx`,
`mobile/provider/src/admin/AdminNavigator.tsx`, `mobile/shared/src/ui/BottomSheet.tsx`

- [ ] **Étape 1 : écrire le test** — en sombre, la barre d'onglets est transparente et pose une
      `GlassSurface` en fond ; en clair, elle garde son fond plein actuel.
- [ ] **Étape 2 : lancer, constater l'échec.**
- [ ] **Étape 3 : appliquer** aux deux barres d'onglets et aux feuilles.
- [ ] **Étape 4 : relancer, commit.**

---

### Tâche 11 : portail

- [ ] `cd mobile/provider && npm run typecheck && npm test`
- [ ] `cd mobile/client && npm run typecheck && npm test`
- [ ] Le garde-fou de la tâche 2 est **vert**.
- [ ] Vérification à l'œil sur appareil ou simulateur : basculer clair ↔ sombre sur l'accueil,
      une liste, un formulaire et l'écran Apparence. **Le mode clair doit être identique à
      avant** — c'est la contrainte la plus facile à casser sans s'en apercevoir.
- [ ] Commit du portail.

## Auto-revue du plan

- **Couverture de la spec.** Assainissement → tâches 1 à 6 ; garde-fou anti-dérive → tâche 2 ;
  fond Skia + repli → tâches 0 et 7 ; cartes/barres en `expo-blur` → tâches 8 et 10 ; boutons
  translucides → tâche 9 ; liseré → tâches 8 et 9 ; mode clair intouché → contrainte globale,
  vérifiée en tâche 11 ; `useReducedMotion` → tâche 7 ; repli sans déplacement → tâche 7 ;
  plancher de contraste → tâche 1 (`glass`) et tâche 8.
- **Cohérence des noms.** `useThemeColors()` rend `glass`, `glassStrong`, `glassBorder`,
  `textOnGlass`, `mutedOnGlass`, `glow`, `isDark` (tâche 1), consommés tels quels en 7, 8, 9, 10.
  `LuxeBackground` et `GlassSurface` gardent leurs signatures d'une tâche à l'autre.
- **Risque assumé.** La tâche 0 peut renvoyer « Skia indisponible ». Le plan ne change pas : seul
  le corps de `LuxeBackground` diffère, et sa signature comme ses tests restent identiques.
