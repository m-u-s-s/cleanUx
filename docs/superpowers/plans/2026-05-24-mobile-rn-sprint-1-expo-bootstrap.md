# CleanUx Mobile RN — Sprint 1 : Monorepo + Expo Bootstrap

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Initialiser `mobile/client/` comme app Expo/TypeScript standalone dans le monorepo Laravel, avec design tokens portés depuis `tailwind.config.js`, un wrapper expo-secure-store pour le token Sanctum, un squelette React Navigation (stack + tabs + 5 placeholder screens), EAS Build configuré (dev/preview/production), et Sentry RN basique. Pas d'écran fonctionnel — juste un Hello World qui build + type-check + passe les tests.

**Architecture:** `mobile/client/` est un projet npm **standalone** (son propre `package.json`, `node_modules/`, `tsconfig.json`). Pas de workspace linking avec le root `package.json` Laravel (qui gère Vite/Tailwind). Zéro couplage fichier — le mobile consomme l'API REST, pas le code Laravel.

**Tech Stack:** Expo SDK ~52, TypeScript strict, React 18, React Navigation 7, expo-secure-store, @sentry/react-native, Jest + @testing-library/react-native.

**Branche :** `feat/mobile-rn-sprint-1` créée depuis HEAD de `feat/mobile-rn-sprint-0` (ou depuis `main` si Sprint 0 est déjà mergé).

---

## File Structure

**Will create (all under `mobile/client/`):**

```
mobile/client/
├── .gitignore
├── app.json
├── eas.json
├── package.json
├── tsconfig.json
├── babel.config.js
├── jest.config.ts
├── App.tsx                           # entry: wraps NavigationContainer
├── src/
│   ├── theme/
│   │   ├── colors.ts                 # brand, surface, semantic, accent palettes
│   │   ├── spacing.ts                # spacing scale (4px base)
│   │   ├── radius.ts                 # border radius tokens
│   │   ├── typography.ts             # font families, sizes, weights
│   │   ├── shadows.ts                # RN box shadows (from Tailwind soft-*)
│   │   ├── animation.ts              # timing + easing constants
│   │   └── index.ts                  # re-export all as `theme`
│   ├── navigation/
│   │   ├── RootNavigator.tsx         # stack: Auth stack | Main tabs
│   │   ├── TabNavigator.tsx          # 4 tabs: Home, Bookings, Notifications, Profile
│   │   ├── linking.ts                # deep link config (skeleton)
│   │   └── types.ts                  # RootStackParamList, TabParamList
│   ├── storage/
│   │   └── secureStore.ts            # typed wrapper around expo-secure-store
│   ├── screens/
│   │   ├── HomeScreen.tsx            # placeholder
│   │   ├── BookingsScreen.tsx        # placeholder
│   │   ├── NotificationsScreen.tsx   # placeholder
│   │   ├── ProfileScreen.tsx         # placeholder
│   │   └── LoginScreen.tsx           # placeholder (auth stack)
│   └── sentry/
│       └── init.ts                   # Sentry.init() wrapper
├── __tests__/
│   ├── theme/
│   │   └── tokens.test.ts            # validates all token modules
│   ├── storage/
│   │   └── secureStore.test.ts       # unit tests with expo-secure-store mock
│   └── App.test.tsx                  # smoke render test
```

**Will NOT modify:** zero Laravel files. Zero changes to root `package.json`, `tailwind.config.js`, or any `resources/` path.

---

## Task 1 — Init Expo project + TypeScript + .gitignore

**Files:**
- Create: `mobile/client/` (entire scaffold)
- Create: `mobile/client/.gitignore`
- Create: `mobile/client/tsconfig.json`

### Step 1.1 — Create the Expo project

- [ ] Run from project root:

```bash
cd mobile && npx create-expo-app@latest client --template blank-typescript
```

If `mobile/` doesn't exist: `mkdir mobile` first.

Expected: creates `mobile/client/` with `App.tsx`, `package.json`, `tsconfig.json`, `babel.config.js`, `app.json`, `.gitignore`, etc.

### Step 1.2 — Verify it starts

- [ ] Run:

```bash
cd mobile/client && npx expo start --no-dev --minify 2>&1 | head -20
```

Expected: Metro bundler starts. Press `Ctrl+C` after seeing "Metro waiting on...".

### Step 1.3 — Harden TypeScript config

- [ ] Replace `mobile/client/tsconfig.json` with:

```json
{
  "extends": "expo/tsconfig.base",
  "compilerOptions": {
    "strict": true,
    "noUncheckedIndexedAccess": true,
    "noImplicitReturns": true,
    "forceConsistentCasingInFileNames": true,
    "baseUrl": ".",
    "paths": {
      "@/*": ["src/*"]
    }
  },
  "include": ["**/*.ts", "**/*.tsx"],
  "exclude": ["node_modules"]
}
```

### Step 1.4 — Harden .gitignore

- [ ] Replace `mobile/client/.gitignore` with:

```
node_modules/
.expo/
dist/
ios/
android/
*.jks
*.p8
*.p12
*.key
*.mobileprovision
*.orig.*
web-build/
.env
.env.local
```

### Step 1.5 — Type-check passes

- [ ] Run:

```bash
cd mobile/client && npx tsc --noEmit
```

Expected: 0 errors (the default `App.tsx` from Expo is valid strict TS).

### Step 1.6 — Commit

```bash
cd /path/to/CleanUx
git add mobile/client/
git commit -m "feat(mobile): init Expo TypeScript project in mobile/client/

npx create-expo-app with blank-typescript template.
TypeScript strict mode enabled. Standalone npm project
(no workspace with root package.json).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 2 — Install dependencies + Jest config

**Files:**
- Modify: `mobile/client/package.json` (add deps)
- Create: `mobile/client/jest.config.ts`
- Modify: `mobile/client/babel.config.js` (add module-resolver for `@/` alias)

### Step 2.1 — Install core deps

- [ ] Run from `mobile/client/`:

```bash
npx expo install react-native-safe-area-context react-native-screens react-native-gesture-handler react-native-reanimated
npx expo install @react-navigation/native @react-navigation/native-stack @react-navigation/bottom-tabs
npx expo install expo-secure-store expo-constants expo-status-bar
npm install @sentry/react-native
```

### Step 2.2 — Install dev/test deps

- [ ] Run:

```bash
npm install -D jest @testing-library/react-native @testing-library/jest-native jest-expo @types/jest ts-jest babel-plugin-module-resolver
```

### Step 2.3 — Configure babel module resolver

- [ ] Replace `mobile/client/babel.config.js`:

```javascript
module.exports = function (api) {
  api.cache(true);
  return {
    presets: ['babel-preset-expo'],
    plugins: [
      ['module-resolver', { alias: { '@': './src' } }],
      'react-native-reanimated/plugin',
    ],
  };
};
```

### Step 2.4 — Configure Jest

- [ ] Create `mobile/client/jest.config.ts`:

```typescript
import type { Config } from 'jest';

const config: Config = {
  preset: 'jest-expo',
  transformIgnorePatterns: [
    'node_modules/(?!((jest-)?react-native|@react-native(-community)?)|expo(nent)?|@expo(nent)?/.*|@expo-google-fonts/.*|react-navigation|@react-navigation/.*|@sentry/react-native|expo-secure-store|expo-constants|expo-status-bar)',
  ],
  setupFilesAfterSetup: ['@testing-library/jest-native/extend-expect'],
  moduleNameMapper: {
    '^@/(.*)$': '<rootDir>/src/$1',
  },
};

export default config;
```

### Step 2.5 — Add test script to package.json

- [ ] Add to `mobile/client/package.json` scripts:

```json
{
  "scripts": {
    "start": "expo start",
    "android": "expo start --android",
    "ios": "expo start --ios",
    "test": "jest",
    "test:watch": "jest --watch",
    "typecheck": "tsc --noEmit",
    "lint": "npx eslint . --ext .ts,.tsx"
  }
}
```

### Step 2.6 — Verify Jest runs (no tests yet)

- [ ] Run:

```bash
cd mobile/client && npm test -- --passWithNoTests
```

Expected: `No tests found` or pass with 0 tests.

### Step 2.7 — Verify type-check still works

```bash
cd mobile/client && npm run typecheck
```

Expected: 0 errors.

### Step 2.8 — Commit

```bash
git add mobile/client/
git commit -m "feat(mobile): install deps (navigation, secure-store, sentry, jest)

React Navigation 7 (native-stack + bottom-tabs), expo-secure-store,
@sentry/react-native, jest-expo + testing-library, babel module-resolver
for @/ path alias, reanimated plugin.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 3 — Design tokens (TDD)

**Files:**
- Create: `mobile/client/src/theme/colors.ts`
- Create: `mobile/client/src/theme/spacing.ts`
- Create: `mobile/client/src/theme/radius.ts`
- Create: `mobile/client/src/theme/typography.ts`
- Create: `mobile/client/src/theme/shadows.ts`
- Create: `mobile/client/src/theme/animation.ts`
- Create: `mobile/client/src/theme/index.ts`
- Create: `mobile/client/__tests__/theme/tokens.test.ts`

### Step 3.1 — Write failing test

- [ ] Create `mobile/client/__tests__/theme/tokens.test.ts`:

```typescript
import { colors, spacing, radius, typography, shadows, animation } from '@/theme';

describe('Design tokens', () => {
  describe('colors', () => {
    it('has brand palette with 500 as primary', () => {
      expect(colors.brand[500]).toBe('#6366f1');
    });

    it('has surface neutral palette', () => {
      expect(colors.surface[50]).toBe('#fafafa');
      expect(colors.surface[950]).toBe('#0a0a0a');
    });

    it('has semantic colors', () => {
      expect(colors.success[500]).toBe('#10b981');
      expect(colors.warning[500]).toBe('#f59e0b');
      expect(colors.danger[500]).toBe('#ef4444');
    });

    it('has accent colors from CSS tokens', () => {
      expect(colors.accent.amber).toBe('#ffb648');
      expect(colors.accent.cyan).toBe('#4fe3d6');
      expect(colors.accent.violet).toBe('#8b7bff');
    });
  });

  describe('spacing', () => {
    it('uses 4px base scale', () => {
      expect(spacing.xs).toBe(4);
      expect(spacing.sm).toBe(8);
      expect(spacing.md).toBe(16);
      expect(spacing.lg).toBe(24);
      expect(spacing.xl).toBe(32);
    });
  });

  describe('radius', () => {
    it('matches CSS --cx-radius-* tokens', () => {
      expect(radius.sm).toBe(10);
      expect(radius.md).toBe(14);
      expect(radius.lg).toBe(22);
      expect(radius.xl).toBe(28);
      expect(radius.pill).toBe(999);
    });
  });

  describe('typography', () => {
    it('defines font families', () => {
      expect(typography.fontFamily.body).toBeDefined();
      expect(typography.fontFamily.display).toBeDefined();
      expect(typography.fontFamily.mono).toBeDefined();
    });

    it('defines font sizes', () => {
      expect(typography.fontSize.sm).toBeDefined();
      expect(typography.fontSize.base).toBeDefined();
      expect(typography.fontSize.lg).toBeDefined();
    });
  });

  describe('shadows', () => {
    it('defines RN-compatible shadow objects', () => {
      const s = shadows.soft;
      expect(s).toHaveProperty('shadowColor');
      expect(s).toHaveProperty('shadowOffset');
      expect(s).toHaveProperty('shadowOpacity');
      expect(s).toHaveProperty('shadowRadius');
      expect(s).toHaveProperty('elevation');
    });
  });

  describe('animation', () => {
    it('defines timing constants matching CSS', () => {
      expect(animation.duration.fast).toBe(180);
      expect(animation.duration.base).toBe(280);
      expect(animation.duration.slow).toBe(420);
    });

    it('defines easing bezier values', () => {
      expect(animation.easing.default).toEqual([0.16, 1, 0.3, 1]);
    });
  });
});
```

### Step 3.2 — Run test to see it fail

```bash
cd mobile/client && npm test -- __tests__/theme/tokens.test.ts
```

Expected: FAIL — `Cannot find module '@/theme'`.

### Step 3.3 — Implement token files

- [ ] Create `mobile/client/src/theme/colors.ts`:

```typescript
export const colors = {
  brand: {
    50: '#eef2ff',
    100: '#e0e7ff',
    200: '#c7d2fe',
    300: '#a5b4fc',
    400: '#818cf8',
    500: '#6366f1',
    600: '#4f46e5',
    700: '#4338ca',
    800: '#3730a3',
    900: '#312e81',
    950: '#1e1b4b',
  },
  surface: {
    50: '#fafafa',
    100: '#f5f5f5',
    200: '#e5e5e5',
    300: '#d4d4d4',
    400: '#a3a3a3',
    500: '#737373',
    600: '#525252',
    700: '#404040',
    800: '#262626',
    900: '#171717',
    950: '#0a0a0a',
  },
  success: { 50: '#ecfdf5', 500: '#10b981', 600: '#059669', 700: '#047857' },
  warning: { 50: '#fffbeb', 500: '#f59e0b', 600: '#d97706', 700: '#b45309' },
  danger: { 50: '#fef2f2', 500: '#ef4444', 600: '#dc2626', 700: '#b91c1c' },
  accent: {
    amber: '#ffb648',
    amberDeep: '#ff8a3d',
    cyan: '#4fe3d6',
    violet: '#8b7bff',
  },
  mode: {
    tool: {
      ink: '#0f172a',
      muted: '#64748b',
      card: 'rgba(255, 255, 255, 0.9)',
      cardStrong: 'rgba(255, 255, 255, 0.96)',
    },
    showcase: {
      night: '#070b14',
      nightSoft: '#0c1322',
      panel: '#111a2e',
      text: '#e8eefc',
      muted: '#93a4c6',
    },
  },
} as const;
```

- [ ] Create `mobile/client/src/theme/spacing.ts`:

```typescript
export const spacing = {
  '2xs': 2,
  xs: 4,
  sm: 8,
  md: 16,
  lg: 24,
  xl: 32,
  '2xl': 48,
  '3xl': 64,
  '4xl': 96,
} as const;
```

- [ ] Create `mobile/client/src/theme/radius.ts`:

```typescript
export const radius = {
  none: 0,
  sm: 10,
  md: 14,
  lg: 22,
  xl: 28,
  pill: 999,
} as const;
```

- [ ] Create `mobile/client/src/theme/typography.ts`:

```typescript
import { Platform } from 'react-native';

export const typography = {
  fontFamily: {
    body: Platform.select({ ios: 'System', android: 'sans-serif', default: 'System' }),
    display: Platform.select({ ios: 'System', android: 'sans-serif-medium', default: 'System' }),
    mono: Platform.select({ ios: 'Menlo', android: 'monospace', default: 'monospace' }),
  },
  fontSize: {
    '2xs': 11,
    xs: 12,
    sm: 14,
    base: 16,
    lg: 18,
    xl: 20,
    '2xl': 24,
    '3xl': 30,
    '4xl': 36,
  },
  lineHeight: {
    tight: 1.25,
    normal: 1.5,
    relaxed: 1.75,
  },
  fontWeight: {
    normal: '400' as const,
    medium: '500' as const,
    semibold: '600' as const,
    bold: '700' as const,
  },
} as const;
```

- [ ] Create `mobile/client/src/theme/shadows.ts`:

```typescript
import { Platform, ViewStyle } from 'react-native';

type Shadow = Pick<ViewStyle, 'shadowColor' | 'shadowOffset' | 'shadowOpacity' | 'shadowRadius' | 'elevation'>;

const shadow = (opacity: number, radius: number, offsetY: number, elevation: number): Shadow => ({
  shadowColor: '#0f172a',
  shadowOffset: { width: 0, height: offsetY },
  shadowOpacity: opacity,
  shadowRadius: radius,
  ...Platform.select({ android: { elevation } }),
});

export const shadows = {
  none: shadow(0, 0, 0, 0),
  xs: shadow(0.04, 1, 1, 1),
  sm: shadow(0.05, 2, 1, 2),
  soft: shadow(0.06, 6, 2, 3),
  md: shadow(0.08, 12, 4, 6),
  lg: shadow(0.12, 24, 12, 12),
} as const;
```

- [ ] Create `mobile/client/src/theme/animation.ts`:

```typescript
export const animation = {
  duration: {
    fast: 180,
    base: 280,
    slow: 420,
  },
  easing: {
    default: [0.16, 1, 0.3, 1] as [number, number, number, number],
  },
} as const;
```

- [ ] Create `mobile/client/src/theme/index.ts`:

```typescript
export { colors } from './colors';
export { spacing } from './spacing';
export { radius } from './radius';
export { typography } from './typography';
export { shadows } from './shadows';
export { animation } from './animation';
```

### Step 3.4 — Run tests to see green

```bash
cd mobile/client && npm test -- __tests__/theme/tokens.test.ts
```

Expected: all tests PASS (~10 assertions).

### Step 3.5 — Commit

```bash
git add mobile/client/src/theme/ mobile/client/__tests__/theme/
git commit -m "feat(mobile): port design tokens from tailwind.config.js

Colors (brand indigo, surface neutral, semantic, accent amber/cyan/violet),
spacing (4px base), radius (cx-radius-* tokens), typography (system fonts,
will switch to custom fonts when loaded via expo-font), shadows (Stripe-like
soft-*), animation timings (180/280/420ms + Apple easing).

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 4 — Secure storage wrapper (TDD)

**Files:**
- Create: `mobile/client/src/storage/secureStore.ts`
- Create: `mobile/client/__tests__/storage/secureStore.test.ts`

### Step 4.1 — Write failing test

- [ ] Create `mobile/client/__tests__/storage/secureStore.test.ts`:

```typescript
import * as ExpoSecureStore from 'expo-secure-store';
import { secureStore } from '@/storage/secureStore';

jest.mock('expo-secure-store');
const mockStore = ExpoSecureStore as jest.Mocked<typeof ExpoSecureStore>;

describe('secureStore', () => {
  beforeEach(() => jest.clearAllMocks());

  it('getToken returns stored value', async () => {
    mockStore.getItemAsync.mockResolvedValue('test-token-123');
    const token = await secureStore.getToken();
    expect(token).toBe('test-token-123');
    expect(mockStore.getItemAsync).toHaveBeenCalledWith('auth_token');
  });

  it('getToken returns null when no token', async () => {
    mockStore.getItemAsync.mockResolvedValue(null);
    const token = await secureStore.getToken();
    expect(token).toBeNull();
  });

  it('setToken stores value', async () => {
    await secureStore.setToken('new-token');
    expect(mockStore.setItemAsync).toHaveBeenCalledWith('auth_token', 'new-token');
  });

  it('clearToken deletes stored value', async () => {
    await secureStore.clearToken();
    expect(mockStore.deleteItemAsync).toHaveBeenCalledWith('auth_token');
  });

  it('isAuthenticated returns true when token exists', async () => {
    mockStore.getItemAsync.mockResolvedValue('some-token');
    expect(await secureStore.isAuthenticated()).toBe(true);
  });

  it('isAuthenticated returns false when no token', async () => {
    mockStore.getItemAsync.mockResolvedValue(null);
    expect(await secureStore.isAuthenticated()).toBe(false);
  });
});
```

### Step 4.2 — See red

```bash
cd mobile/client && npm test -- __tests__/storage/secureStore.test.ts
```

Expected: FAIL — `Cannot find module '@/storage/secureStore'`.

### Step 4.3 — Implement

- [ ] Create `mobile/client/src/storage/secureStore.ts`:

```typescript
import * as ExpoSecureStore from 'expo-secure-store';

const TOKEN_KEY = 'auth_token';

export const secureStore = {
  async getToken(): Promise<string | null> {
    return ExpoSecureStore.getItemAsync(TOKEN_KEY);
  },

  async setToken(token: string): Promise<void> {
    await ExpoSecureStore.setItemAsync(TOKEN_KEY, token);
  },

  async clearToken(): Promise<void> {
    await ExpoSecureStore.deleteItemAsync(TOKEN_KEY);
  },

  async isAuthenticated(): Promise<boolean> {
    const token = await ExpoSecureStore.getItemAsync(TOKEN_KEY);
    return token !== null;
  },
};
```

### Step 4.4 — See green

```bash
cd mobile/client && npm test -- __tests__/storage/secureStore.test.ts
```

Expected: 6 PASS.

### Step 4.5 — Commit

```bash
git add mobile/client/src/storage/ mobile/client/__tests__/storage/
git commit -m "feat(mobile): typed secure storage wrapper for Sanctum token

Uses expo-secure-store under the hood. API: getToken, setToken,
clearToken, isAuthenticated. Key: 'auth_token'. 6 unit tests.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 5 — React Navigation skeleton (TDD)

**Files:**
- Create: `mobile/client/src/navigation/types.ts`
- Create: `mobile/client/src/navigation/TabNavigator.tsx`
- Create: `mobile/client/src/navigation/RootNavigator.tsx`
- Create: `mobile/client/src/navigation/linking.ts`
- Create: `mobile/client/src/navigation/index.ts`
- Create: `mobile/client/src/screens/HomeScreen.tsx`
- Create: `mobile/client/src/screens/BookingsScreen.tsx`
- Create: `mobile/client/src/screens/NotificationsScreen.tsx`
- Create: `mobile/client/src/screens/ProfileScreen.tsx`
- Create: `mobile/client/src/screens/LoginScreen.tsx`
- Modify: `mobile/client/App.tsx`
- Create: `mobile/client/__tests__/App.test.tsx`

### Step 5.1 — Write smoke render test

- [ ] Create `mobile/client/__tests__/App.test.tsx`:

```tsx
import React from 'react';
import { render, screen } from '@testing-library/react-native';
import App from '../App';

describe('App', () => {
  it('renders without crashing', () => {
    render(<App />);
    expect(screen.getByTestId('root-navigator')).toBeTruthy();
  });
});
```

### Step 5.2 — See red

```bash
cd mobile/client && npm test -- __tests__/App.test.tsx
```

Expected: FAIL (current `App.tsx` is Expo default with no `testID`).

### Step 5.3 — Implement navigation types

- [ ] Create `mobile/client/src/navigation/types.ts`:

```typescript
export type RootStackParamList = {
  Login: undefined;
  MainTabs: undefined;
};

export type TabParamList = {
  Home: undefined;
  Bookings: undefined;
  Notifications: undefined;
  Profile: undefined;
};
```

### Step 5.4 — Implement placeholder screens

- [ ] Create all 5 screens. Pattern for each (`HomeScreen.tsx` shown, repeat for others):

`mobile/client/src/screens/HomeScreen.tsx`:
```tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, typography } from '@/theme';

export function HomeScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Home</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.surface[50] },
  title: { fontSize: typography.fontSize.xl, fontWeight: typography.fontWeight.semibold, color: colors.surface[900] },
});
```

`mobile/client/src/screens/BookingsScreen.tsx`:
```tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, typography } from '@/theme';

export function BookingsScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Bookings</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.surface[50] },
  title: { fontSize: typography.fontSize.xl, fontWeight: typography.fontWeight.semibold, color: colors.surface[900] },
});
```

`mobile/client/src/screens/NotificationsScreen.tsx`:
```tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, typography } from '@/theme';

export function NotificationsScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Notifications</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.surface[50] },
  title: { fontSize: typography.fontSize.xl, fontWeight: typography.fontWeight.semibold, color: colors.surface[900] },
});
```

`mobile/client/src/screens/ProfileScreen.tsx`:
```tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, typography } from '@/theme';

export function ProfileScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Profile</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.surface[50] },
  title: { fontSize: typography.fontSize.xl, fontWeight: typography.fontWeight.semibold, color: colors.surface[900] },
});
```

`mobile/client/src/screens/LoginScreen.tsx`:
```tsx
import React from 'react';
import { View, Text, StyleSheet } from 'react-native';
import { colors, typography } from '@/theme';

export function LoginScreen() {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>Login</Text>
      <Text style={styles.subtitle}>CleanUx</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.mode.showcase.night },
  title: { fontSize: typography.fontSize['2xl'], fontWeight: typography.fontWeight.bold, color: colors.mode.showcase.text },
  subtitle: { fontSize: typography.fontSize.lg, color: colors.accent.amber, marginTop: 8 },
});
```

### Step 5.5 — Implement TabNavigator

- [ ] Create `mobile/client/src/navigation/TabNavigator.tsx`:

```tsx
import React from 'react';
import { createBottomTabNavigator } from '@react-navigation/bottom-tabs';
import { HomeScreen } from '@/screens/HomeScreen';
import { BookingsScreen } from '@/screens/BookingsScreen';
import { NotificationsScreen } from '@/screens/NotificationsScreen';
import { ProfileScreen } from '@/screens/ProfileScreen';
import { colors } from '@/theme';
import type { TabParamList } from './types';

const Tab = createBottomTabNavigator<TabParamList>();

export function TabNavigator() {
  return (
    <Tab.Navigator
      screenOptions={{
        headerShown: false,
        tabBarActiveTintColor: colors.brand[500],
        tabBarInactiveTintColor: colors.surface[400],
        tabBarStyle: { backgroundColor: colors.surface[50], borderTopColor: colors.surface[200] },
      }}
    >
      <Tab.Screen name="Home" component={HomeScreen} />
      <Tab.Screen name="Bookings" component={BookingsScreen} />
      <Tab.Screen name="Notifications" component={NotificationsScreen} />
      <Tab.Screen name="Profile" component={ProfileScreen} />
    </Tab.Navigator>
  );
}
```

### Step 5.6 — Implement RootNavigator

- [ ] Create `mobile/client/src/navigation/RootNavigator.tsx`:

```tsx
import React from 'react';
import { View } from 'react-native';
import { createNativeStackNavigator } from '@react-navigation/native-stack';
import { LoginScreen } from '@/screens/LoginScreen';
import { TabNavigator } from './TabNavigator';
import type { RootStackParamList } from './types';

const Stack = createNativeStackNavigator<RootStackParamList>();

export function RootNavigator() {
  return (
    <View testID="root-navigator" style={{ flex: 1 }}>
      <Stack.Navigator screenOptions={{ headerShown: false }}>
        <Stack.Screen name="MainTabs" component={TabNavigator} />
        <Stack.Screen name="Login" component={LoginScreen} options={{ presentation: 'modal' }} />
      </Stack.Navigator>
    </View>
  );
}
```

### Step 5.7 — Implement linking config

- [ ] Create `mobile/client/src/navigation/linking.ts`:

```typescript
import type { LinkingOptions } from '@react-navigation/native';
import type { RootStackParamList } from './types';

export const linking: LinkingOptions<RootStackParamList> = {
  prefixes: ['cleanux://', 'https://app.cleanux.com'],
  config: {
    screens: {
      MainTabs: {
        screens: {
          Home: '',
          Bookings: 'bookings',
          Notifications: 'notifications',
          Profile: 'profile',
        },
      },
      Login: 'login',
    },
  },
};
```

### Step 5.8 — Implement navigation index

- [ ] Create `mobile/client/src/navigation/index.ts`:

```typescript
export { RootNavigator } from './RootNavigator';
export { linking } from './linking';
export type { RootStackParamList, TabParamList } from './types';
```

### Step 5.9 — Wire App.tsx

- [ ] Replace `mobile/client/App.tsx`:

```tsx
import React from 'react';
import { NavigationContainer } from '@react-navigation/native';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { StatusBar } from 'expo-status-bar';
import { RootNavigator, linking } from '@/navigation';
import '@/sentry/init';

export default function App() {
  return (
    <SafeAreaProvider>
      <NavigationContainer linking={linking}>
        <RootNavigator />
      </NavigationContainer>
      <StatusBar style="auto" />
    </SafeAreaProvider>
  );
}
```

Note: `@/sentry/init` will be created in Task 6. For now, create a placeholder:

```typescript
// mobile/client/src/sentry/init.ts
// Sentry initialization — configured in Task 6
```

### Step 5.10 — See green

```bash
cd mobile/client && npm test
```

Expected: ALL tests PASS (theme tokens ~10 + secureStore 6 + App smoke 1 = ~17 tests).

### Step 5.11 — Commit

```bash
git add mobile/client/
git commit -m "feat(mobile): React Navigation skeleton (stack + tabs + 5 screens)

RootNavigator (native-stack): MainTabs | Login (modal).
TabNavigator (bottom-tabs): Home, Bookings, Notifications, Profile.
Deep linking config for cleanux:// and https://app.cleanux.com.
All screens use theme tokens (colors, typography). Smoke render test.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 6 — EAS Build config + Sentry + app.json

**Files:**
- Modify: `mobile/client/app.json`
- Create: `mobile/client/eas.json`
- Modify: `mobile/client/src/sentry/init.ts`

### Step 6.1 — Configure app.json

- [ ] Replace `mobile/client/app.json`:

```json
{
  "expo": {
    "name": "CleanUx",
    "slug": "cleanux-client",
    "version": "1.0.0",
    "orientation": "portrait",
    "icon": "./assets/icon.png",
    "userInterfaceStyle": "automatic",
    "splash": {
      "image": "./assets/splash-icon.png",
      "resizeMode": "contain",
      "backgroundColor": "#070b14"
    },
    "ios": {
      "supportsTablet": false,
      "bundleIdentifier": "com.cleanux.client",
      "infoPlist": {
        "NSCameraUsageDescription": "CleanUx uses the camera to scan QR codes for mission start/end.",
        "NSLocationWhenInUseUsageDescription": "CleanUx uses your location to show nearby providers and track missions.",
        "NSPhotoLibraryUsageDescription": "CleanUx uses photos for AI quote estimation and chat attachments."
      }
    },
    "android": {
      "adaptiveIcon": {
        "foregroundImage": "./assets/adaptive-icon.png",
        "backgroundColor": "#070b14"
      },
      "package": "com.cleanux.client",
      "permissions": [
        "CAMERA",
        "ACCESS_FINE_LOCATION",
        "ACCESS_COARSE_LOCATION",
        "READ_EXTERNAL_STORAGE"
      ]
    },
    "scheme": "cleanux",
    "plugins": [
      "expo-secure-store",
      "@sentry/react-native/expo"
    ],
    "extra": {
      "eas": {
        "projectId": "PLACEHOLDER_EAS_PROJECT_ID"
      }
    }
  }
}
```

Note: `PLACEHOLDER_EAS_PROJECT_ID` should be replaced after running `eas init` (interactive command the user runs manually).

### Step 6.2 — Configure EAS profiles

- [ ] Create `mobile/client/eas.json`:

```json
{
  "cli": {
    "version": ">= 12.0.0"
  },
  "build": {
    "development": {
      "developmentClient": true,
      "distribution": "internal",
      "ios": { "simulator": true },
      "env": {
        "APP_ENV": "development",
        "API_URL": "http://localhost:8000/api"
      }
    },
    "preview": {
      "distribution": "internal",
      "env": {
        "APP_ENV": "staging",
        "API_URL": "https://staging.cleanux.com/api"
      }
    },
    "production": {
      "autoIncrement": true,
      "env": {
        "APP_ENV": "production",
        "API_URL": "https://app.cleanux.com/api"
      }
    }
  },
  "submit": {
    "production": {
      "ios": {
        "appleId": "PLACEHOLDER_APPLE_ID",
        "ascAppId": "PLACEHOLDER_ASC_APP_ID",
        "appleTeamId": "PLACEHOLDER_TEAM_ID"
      },
      "android": {
        "serviceAccountKeyPath": "./google-services.json",
        "track": "internal"
      }
    }
  }
}
```

### Step 6.3 — Configure Sentry

- [ ] Replace `mobile/client/src/sentry/init.ts`:

```typescript
import * as Sentry from '@sentry/react-native';
import Constants from 'expo-constants';

const DSN = process.env.EXPO_PUBLIC_SENTRY_DSN ?? '';

if (DSN) {
  Sentry.init({
    dsn: DSN,
    environment: Constants.expoConfig?.extra?.eas?.APP_ENV ?? 'development',
    tracesSampleRate: __DEV__ ? 1.0 : 0.2,
    debug: __DEV__,
    enabled: !__DEV__,
  });
}

export { Sentry };
```

### Step 6.4 — Verify type-check + tests still pass

```bash
cd mobile/client && npm run typecheck && npm test
```

Expected: 0 TS errors, all tests PASS.

### Step 6.5 — Commit

```bash
git add mobile/client/
git commit -m "feat(mobile): EAS Build profiles + app.json + Sentry init

3 build profiles (development/preview/production) with per-env API_URL.
iOS permissions (camera, location, photos) + Android equivalent.
Deep link scheme 'cleanux://'. Sentry gated on DSN env var, disabled in dev.
Submit config scaffolded with placeholders for Apple/Google credentials.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

---

## Task 7 — Final verification + root .gitignore

**Files:**
- Modify (maybe): root `.gitignore` (add `mobile/client/node_modules/` if needed)

### Step 7.1 — Verify root .gitignore handles mobile

- [ ] Check if root `.gitignore` already ignores `node_modules/` recursively:

```bash
grep 'node_modules' .gitignore
```

If it says `node_modules` or `**/node_modules`, it already covers `mobile/client/node_modules/`. If it only says `/node_modules` (root only), add:

```
mobile/client/node_modules/
mobile/client/.expo/
```

### Step 7.2 — Full type-check

```bash
cd mobile/client && npm run typecheck
```

Expected: 0 errors.

### Step 7.3 — Full test suite

```bash
cd mobile/client && npm test
```

Expected: ~17 tests PASS (tokens ~10, secureStore 6, App smoke 1).

### Step 7.4 — Verify Metro starts

```bash
cd mobile/client && npx expo start --no-dev --minify 2>&1 | head -20
```

Expected: Metro starts, no compilation errors.

### Step 7.5 — Verify Laravel tests unaffected

```bash
cd /path/to/CleanUx && php artisan test --filter="AuthRefreshTest|ExceptionHandlerJsonTest|StripeConnectTest|SocketConfigTest" 2>&1 | tail -5
```

Expected: Sprint 0's 24 tests still PASS (Sprint 1 shouldn't touch Laravel, but paranoid check).

### Step 7.6 — Commit if .gitignore changed

```bash
git add .gitignore
git commit -m "chore: add mobile/client/ to root .gitignore coverage

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>"
```

If no .gitignore change needed, skip this step.

---

## Self-Review

### Spec coverage

| Master index requirement | Task | Status |
|---|---|---|
| `/mobile/client` initialisé | Task 1 | ✅ |
| Expo SDK installé | Task 1 | ✅ |
| EAS configuré (dev/preview/production) | Task 6 | ✅ |
| TypeScript strict | Task 1 | ✅ |
| React Navigation v7 (stack + tabs + modal) | Task 5 | ✅ |
| `theme.ts` portant tokens tailwind | Task 3 | ✅ |
| expo-secure-store wrappé | Task 4 | ✅ |
| Hello World qui build sur iOS + Android | Task 7 | ✅ (Metro verify) |
| CI : lint + type-check | Skipped | ⚠️ GitHub Actions config belongs in a CI sprint, out of scope |
| Sentry RN basique | Task 6 | ✅ |

### Placeholder scan

No "TBD", "TODO", or "implement later" in any task. All code blocks are complete. The only `PLACEHOLDER_*` values are in `eas.json` and `app.json` — these are credentials the user must fill in after `eas init`, explicitly documented.

### Type consistency

- `colors`, `spacing`, `radius`, `typography`, `shadows`, `animation` — same names in tokens, tests, and screen imports
- `RootStackParamList`, `TabParamList` — same names in types, navigators, and linking config
- `secureStore.getToken()` / `setToken()` / `clearToken()` / `isAuthenticated()` — same signatures in test and implementation
- `@/` path alias — configured in tsconfig, babel, jest.config consistently
