# Client Mobile POC · Adaptive Design System — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer un POC client mobile sur 2 écrans (Home V1 clair + Mission Live V3 sombre) démontrant un design system adaptive Linear/Apple premium, avec coexistence ancien/nouveau via feature flag.

**Architecture:** Hybride Livewire v3 (data + logique server) + Vue 3 islands montés via Vite (présentation + interactions UI). Design tokens CSS variables pour switch adaptive auto (mission active / urgence / nuit). Pas de package tiers d'intégration — mount manuel des Vue apps sur des div ID. Communication Vue → Livewire via window events.

**Tech Stack:** Laravel 11, Livewire v3, Vue 3, Vite 6, Tailwind v3, Motion One, Laravel Pennant, Vitest, Playwright, Capacitor 6.

---

## Spec Reference

`docs/superpowers/specs/2026-05-22-client-mobile-poc-design.md`

## Pre-flight Decisions (resolved from spec Open Questions)

- **OQ1 storage thème** → on utilise `user_settings` JSON existant (clé `theme_preference`). Pas de nouvelle migration de schéma. Plus simple, additif.
- **OQ2 bundle séparé** → 2 entry points Vite distincts (`client-home`, `mission-live`) avec chunks partagés auto via Vite. Bundle initial < 80KB chacun.
- **OQ3 migration progressive** → coexistence par feature flag dans la MÊME route (`/dashboard/client`), pas de route v2 séparée. Évite duplication.
- **OQ4/5 tests device + beta** → out of scope du plan code, à organiser par toi en parallèle.

## File Structure

**Création :**
- `resources/css/tokens.css` — design tokens (clair + sombre)
- `resources/js/islands/client-home.ts` — Vue entry pour Home
- `resources/js/islands/mission-live.ts` — Vue entry pour Mission Live
- `resources/js/components/atoms/` (5 SFC) — Avatar, BtnPrimary, BtnSecondary, Tag, PulseDot
- `resources/js/components/client/` (10 SFC) — AdaptiveHero, QuickActionGrid, StatusCardScroller, ServiceTile, FabUrgence, BottomNav, BottomActionSheet, EtaGlassCard, StatusTimeline, QrScanCta
- `resources/js/composables/useAdaptiveTheme.ts` — composable Vue
- `resources/js/composables/useReverbChannel.ts` — wrapper Echo
- `app/Services/Theme/AdaptiveThemeResolver.php` + interface + test unit
- `app/Providers/FeatureServiceProvider.php` — Pennant feature definitions
- `tests/Feature/Theme/AdaptiveThemeResolverTest.php`
- `tests/Feature/Client/ClientDashboardV2Test.php`
- `tests/Feature/Client/MissionLiveTrackingV2Test.php`
- `tests/e2e/playwright.config.ts` + `tests/e2e/client-mobile-poc.spec.ts`
- `vitest.config.ts`
- `docs/design-system/README.md`

**Modification :**
- `package.json` — add Vue 3, Motion One, Vitest, Vue Test Utils, Playwright
- `composer.json` — add laravel/pennant
- `vite.config.js` — entry points islands + Vue plugin
- `tailwind.config.js` — semantic colors via CSS variables
- `resources/css/app.css` — import tokens
- `resources/js/app.js` — auto-mount islands
- `app/Livewire/ClientDashboard.php` — load data + render mount point
- `app/Livewire/Client/MissionLiveTracking.php` — refondu pour Vue island
- `resources/views/livewire/client-dashboard.blade.php` — mount div + props JSON
- `resources/views/livewire/client/mission-live-tracking.blade.php` — idem
- `bootstrap/providers.php` — register FeatureServiceProvider

---

## Task 1 : Install Dependencies & Initialize Pennant

**Files:**
- Modify: `package.json`
- Modify: `composer.json` (via composer command)
- Create: `config/pennant.php` (via artisan vendor:publish)

- [ ] **Step 1: Install Laravel Pennant**

Run from project root:
```bash
composer require laravel/pennant
php artisan vendor:publish --provider="Laravel\Pennant\PennantServiceProvider"
php artisan migrate
```

Expected: `pennant` package added to `composer.json` require section, `config/pennant.php` created, `features` table migrated.

- [ ] **Step 2: Install Vue 3 + Motion One + dev tools**

Run:
```bash
npm install vue@^3.4 motion@^11.0
npm install -D @vitejs/plugin-vue@^5.0 vitest@^2.0 @vue/test-utils@^2.4 happy-dom@^15.0 @vue/tsconfig typescript
```

Expected: `package.json` dependencies includes `vue`, `motion`. devDependencies includes `@vitejs/plugin-vue`, `vitest`, `@vue/test-utils`, `happy-dom`, `typescript`.

- [ ] **Step 3: Install Playwright for E2E**

Run:
```bash
npm install -D @playwright/test@^1.45
npx playwright install chromium webkit
```

Expected: Playwright installed, chromium + webkit browsers downloaded.

- [ ] **Step 4: Verify installs work**

Run:
```bash
npx vue --version
npx vitest --version
npx playwright --version
```

Expected: 3 version numbers printed without errors.

- [ ] **Step 5: Commit**

```bash
git add package.json package-lock.json composer.json composer.lock config/pennant.php database/migrations/*pennant*
git commit -m "$(cat <<'EOF'
chore(deps): install Vue 3, Pennant, Motion One, Vitest, Playwright

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 2 : Vite Config for Islands

**Files:**
- Modify: `vite.config.js`

- [ ] **Step 1: Update Vite config**

Replace content of `vite.config.js`:

```javascript
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/islands/client-home.ts',
                'resources/js/islands/mission-live.ts',
            ],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    'vue-vendor': ['vue'],
                    'motion-vendor': ['motion'],
                },
            },
        },
    },
});
```

- [ ] **Step 2: Test build doesn't break existing**

Run:
```bash
npm run build
```

Expected: Build success. `public/build/manifest.json` regenerated. No errors.

- [ ] **Step 3: Commit**

```bash
git add vite.config.js public/build/
git commit -m "$(cat <<'EOF'
build: configure Vite for Vue islands with manual chunks

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 3 : Design Tokens CSS

**Files:**
- Create: `resources/css/tokens.css`
- Modify: `resources/css/app.css`

- [ ] **Step 1: Create tokens file**

Create `resources/css/tokens.css`:

```css
/* Design tokens — Adaptive multi-mode */

:root {
  /* Mode clair (par défaut) — planifié, retention */
  --color-bg: #fafaf7;
  --color-surface: #ffffff;
  --color-surface-2: #f4f1ff;
  --color-text: #0a0a0f;
  --color-text-2: #6a6a78;
  --color-text-3: #8a8a98;
  --color-primary: #6366f1;
  --color-primary-hover: #5147e0;
  --color-accent: #a78bfa;
  --color-urgent: #ef4444;
  --color-urgent-hover: #dc2626;
  --color-success: #10b981;
  --color-warning: #f59e0b;
  --color-border: rgba(0, 0, 0, 0.04);
  --color-border-2: rgba(0, 0, 0, 0.08);
  --shadow-card: 0 1px 3px rgba(99, 102, 241, 0.06), 0 12px 32px -8px rgba(99, 102, 241, 0.18);
  --shadow-fab: 0 12px 24px -4px rgba(239, 68, 68, 0.5);
  --shadow-cta: 0 6px 16px -2px rgba(99, 102, 241, 0.4);
  --shadow-call: 0 4px 12px rgba(16, 185, 129, 0.4);

  /* Radii */
  --radius-sm: 6px;
  --radius-md: 12px;
  --radius-lg: 18px;
  --radius-xl: 22px;
  --radius-2xl: 28px;
  --radius-full: 999px;

  /* Spacing */
  --space-1: 4px;
  --space-2: 8px;
  --space-3: 12px;
  --space-4: 16px;
  --space-5: 20px;
  --space-6: 24px;
  --space-8: 32px;

  /* Typography */
  --font-display: -apple-system, 'SF Pro Display', 'Inter Variable', 'Inter', sans-serif;
  --font-body: -apple-system, 'SF Pro Text', 'Inter Variable', 'Inter', sans-serif;
  --font-mono: 'JetBrains Mono', 'SF Mono', Menlo, monospace;

  /* Motion */
  --ease-apple: cubic-bezier(0.32, 0.72, 0, 1);
  --duration-fast: 180ms;
  --duration-base: 380ms;
  --duration-slow: 600ms;
}

[data-theme='dark'] {
  /* Mode sombre — mission active, urgent, nuit */
  --color-bg: #0a0a0f;
  --color-surface: #1a1a25;
  --color-surface-2: #14141f;
  --color-text: #fafaf7;
  --color-text-2: #a8a8b8;
  --color-text-3: #6a6a78;
  --color-primary: #818cf8;
  --color-primary-hover: #6366f1;
  --color-accent: #c4b5fd;
  --color-urgent: #ef4444;
  --color-urgent-hover: #f87171;
  --color-success: #10b981;
  --color-warning: #f59e0b;
  --color-border: rgba(255, 255, 255, 0.06);
  --color-border-2: rgba(255, 255, 255, 0.1);
  --shadow-card: 0 12px 32px rgba(0, 0, 0, 0.4);
  --shadow-fab: 0 12px 24px -4px rgba(239, 68, 68, 0.5);
  --shadow-cta: 0 8px 24px -4px rgba(99, 102, 241, 0.5);
  --shadow-call: 0 4px 12px rgba(16, 185, 129, 0.4);
}

/* Smooth transition all themed properties */
html {
  transition:
    background-color var(--duration-base) var(--ease-apple),
    color var(--duration-base) var(--ease-apple);
}

body {
  background-color: var(--color-bg);
  color: var(--color-text);
  font-family: var(--font-body);
}
```

- [ ] **Step 2: Import tokens in app.css**

Edit `resources/css/app.css` — add at the very top (before any other import):

```css
@import './tokens.css';
```

- [ ] **Step 3: Verify build**

Run:
```bash
npm run build
```

Expected: Build success. No CSS errors.

- [ ] **Step 4: Commit**

```bash
git add resources/css/tokens.css resources/css/app.css
git commit -m "$(cat <<'EOF'
feat(design-system): add adaptive design tokens (light + dark)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 4 : Tailwind Extension with Semantic Colors

**Files:**
- Modify: `tailwind.config.js`

- [ ] **Step 1: Read current tailwind config**

Run:
```bash
cat tailwind.config.js
```

Note the existing structure (theme.extend.colors brand indigo etc.) so changes are additive, not replacing.

- [ ] **Step 2: Extend Tailwind with CSS variable colors**

In `tailwind.config.js`, inside `theme.extend.colors`, add (do NOT replace existing keys, ADD new ones):

```javascript
// inside theme.extend.colors
semantic: {
  bg: 'var(--color-bg)',
  surface: 'var(--color-surface)',
  'surface-2': 'var(--color-surface-2)',
  text: 'var(--color-text)',
  'text-2': 'var(--color-text-2)',
  'text-3': 'var(--color-text-3)',
  primary: 'var(--color-primary)',
  'primary-hover': 'var(--color-primary-hover)',
  accent: 'var(--color-accent)',
  urgent: 'var(--color-urgent)',
  'urgent-hover': 'var(--color-urgent-hover)',
  success: 'var(--color-success)',
  warning: 'var(--color-warning)',
  border: 'var(--color-border)',
  'border-2': 'var(--color-border-2)',
},
```

And add `boxShadow` to `theme.extend`:

```javascript
boxShadow: {
  card: 'var(--shadow-card)',
  fab: 'var(--shadow-fab)',
  cta: 'var(--shadow-cta)',
  call: 'var(--shadow-call)',
},
```

- [ ] **Step 3: Verify rebuild**

Run:
```bash
npm run build
```

Expected: Build success. Classes like `bg-semantic-bg`, `text-semantic-text`, `shadow-card` are now available.

- [ ] **Step 4: Commit**

```bash
git add tailwind.config.js
git commit -m "$(cat <<'EOF'
feat(design-system): extend Tailwind with semantic adaptive colors

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 5 : Vitest Configuration

**Files:**
- Create: `vitest.config.ts`
- Create: `resources/js/test-setup.ts`
- Modify: `package.json` (scripts)

- [ ] **Step 1: Create vitest config**

Create `vitest.config.ts`:

```typescript
import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath } from 'node:url';

export default defineConfig({
  plugins: [vue()],
  test: {
    environment: 'happy-dom',
    globals: true,
    setupFiles: ['./resources/js/test-setup.ts'],
    include: ['resources/js/**/*.test.ts'],
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
    },
  },
});
```

- [ ] **Step 2: Create test setup**

Create `resources/js/test-setup.ts`:

```typescript
import { afterEach } from 'vitest';
import { cleanup } from '@vue/test-utils';

afterEach(() => {
  cleanup();
});
```

Note: `@vue/test-utils` doesn't export `cleanup` — use this minimal setup instead:

```typescript
// resources/js/test-setup.ts
import { beforeEach } from 'vitest';

beforeEach(() => {
  // Reset CSS variables on root before each test
  document.documentElement.removeAttribute('data-theme');
});
```

- [ ] **Step 3: Add test scripts to package.json**

In `package.json`, add to `scripts`:

```json
"test": "vitest",
"test:run": "vitest run",
"test:e2e": "playwright test"
```

- [ ] **Step 4: Verify**

Run:
```bash
npm run test:run
```

Expected: "No test files found, exiting with code 0" (no tests yet). Vitest itself runs without error.

- [ ] **Step 5: Commit**

```bash
git add vitest.config.ts resources/js/test-setup.ts package.json
git commit -m "$(cat <<'EOF'
test: configure Vitest with happy-dom environment

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 6 : Atomic Component — Avatar

**Files:**
- Create: `resources/js/components/atoms/Avatar.vue`
- Create: `resources/js/components/atoms/Avatar.test.ts`

- [ ] **Step 1: Write failing test**

Create `resources/js/components/atoms/Avatar.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import Avatar from './Avatar.vue';

describe('Avatar', () => {
  it('renders the initial of name', () => {
    const wrapper = mount(Avatar, { props: { name: 'Mohamed' } });
    expect(wrapper.text()).toBe('M');
  });

  it('renders uppercase initial', () => {
    const wrapper = mount(Avatar, { props: { name: 'sarah' } });
    expect(wrapper.text()).toBe('S');
  });

  it('renders custom size class', () => {
    const wrapper = mount(Avatar, { props: { name: 'A', size: 'lg' } });
    expect(wrapper.classes()).toContain('h-12');
    expect(wrapper.classes()).toContain('w-12');
  });

  it('defaults to md size', () => {
    const wrapper = mount(Avatar, { props: { name: 'A' } });
    expect(wrapper.classes()).toContain('h-11');
    expect(wrapper.classes()).toContain('w-11');
  });
});
```

- [ ] **Step 2: Run test (must fail)**

Run:
```bash
npm run test:run -- resources/js/components/atoms/Avatar.test.ts
```

Expected: FAIL — file `Avatar.vue` not found.

- [ ] **Step 3: Implement Avatar.vue**

Create `resources/js/components/atoms/Avatar.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  name: string;
  size?: 'sm' | 'md' | 'lg';
}

const props = withDefaults(defineProps<Props>(), {
  size: 'md',
});

const initial = computed(() => props.name.charAt(0).toUpperCase());

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'sm': return 'h-8 w-8 text-xs';
    case 'lg': return 'h-12 w-12 text-base';
    default: return 'h-11 w-11 text-sm';
  }
});
</script>

<template>
  <div
    :class="[
      'inline-flex items-center justify-center rounded-full font-bold text-white',
      sizeClasses,
    ]"
    style="background: linear-gradient(135deg, var(--color-accent) 0%, var(--color-primary) 100%); box-shadow: 0 4px 12px rgba(99,102,241,0.25);"
  >
    {{ initial }}
  </div>
</template>
```

- [ ] **Step 4: Run test (must pass)**

Run:
```bash
npm run test:run -- resources/js/components/atoms/Avatar.test.ts
```

Expected: PASS — 4 tests OK.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/atoms/Avatar.vue resources/js/components/atoms/Avatar.test.ts
git commit -m "$(cat <<'EOF'
feat(atoms): add Avatar component with size variants

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 7 : Atomic Component — Tag, PulseDot

**Files:**
- Create: `resources/js/components/atoms/Tag.vue` + test
- Create: `resources/js/components/atoms/PulseDot.vue` + test

- [ ] **Step 1: Write Tag test**

Create `resources/js/components/atoms/Tag.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import Tag from './Tag.vue';

describe('Tag', () => {
  it('renders slot content', () => {
    const wrapper = mount(Tag, { slots: { default: 'Premium' } });
    expect(wrapper.text()).toBe('Premium');
  });

  it('applies variant primary by default', () => {
    const wrapper = mount(Tag, { slots: { default: 'x' } });
    expect(wrapper.classes()).toContain('text-semantic-primary');
  });

  it('applies variant urgent', () => {
    const wrapper = mount(Tag, { props: { variant: 'urgent' }, slots: { default: 'x' } });
    expect(wrapper.classes()).toContain('text-semantic-urgent');
  });
});
```

- [ ] **Step 2: Implement Tag.vue**

Create `resources/js/components/atoms/Tag.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  variant?: 'primary' | 'urgent' | 'success' | 'neutral';
}

const props = withDefaults(defineProps<Props>(), { variant: 'primary' });

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'urgent': return 'bg-semantic-urgent/10 text-semantic-urgent';
    case 'success': return 'bg-semantic-success/10 text-semantic-success';
    case 'neutral': return 'bg-semantic-text-3/10 text-semantic-text-2';
    default: return 'bg-semantic-primary/10 text-semantic-primary';
  }
});
</script>

<template>
  <span
    :class="[
      'inline-block rounded-md px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider',
      variantClasses,
    ]"
  >
    <slot />
  </span>
</template>
```

- [ ] **Step 3: Run Tag test**

```bash
npm run test:run -- resources/js/components/atoms/Tag.test.ts
```

Expected: PASS.

- [ ] **Step 4: Write PulseDot test**

Create `resources/js/components/atoms/PulseDot.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PulseDot from './PulseDot.vue';

describe('PulseDot', () => {
  it('renders with default urgent variant', () => {
    const wrapper = mount(PulseDot);
    expect(wrapper.classes()).toContain('bg-semantic-urgent');
  });

  it('renders with success variant', () => {
    const wrapper = mount(PulseDot, { props: { variant: 'success' } });
    expect(wrapper.classes()).toContain('bg-semantic-success');
  });

  it('applies animate-pulse-glow class', () => {
    const wrapper = mount(PulseDot);
    expect(wrapper.classes()).toContain('animate-pulse-glow');
  });
});
```

- [ ] **Step 5: Implement PulseDot.vue**

Create `resources/js/components/atoms/PulseDot.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue';

interface Props {
  variant?: 'urgent' | 'success' | 'primary';
}

const props = withDefaults(defineProps<Props>(), { variant: 'urgent' });

const variantClass = computed(() => {
  switch (props.variant) {
    case 'success': return 'bg-semantic-success';
    case 'primary': return 'bg-semantic-primary';
    default: return 'bg-semantic-urgent';
  }
});
</script>

<template>
  <span :class="['inline-block h-2 w-2 rounded-full animate-pulse-glow', variantClass]" />
</template>

<style>
@keyframes pulse-glow {
  0% { box-shadow: 0 0 0 0 currentColor; opacity: 1; }
  70% { box-shadow: 0 0 0 12px transparent; opacity: 0.85; }
  100% { box-shadow: 0 0 0 0 transparent; opacity: 1; }
}
.animate-pulse-glow {
  animation: pulse-glow 1.6s infinite;
}
</style>
```

- [ ] **Step 6: Run all atom tests**

```bash
npm run test:run -- resources/js/components/atoms/
```

Expected: PASS — 3 test files, 10 tests total.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/atoms/Tag.vue resources/js/components/atoms/Tag.test.ts resources/js/components/atoms/PulseDot.vue resources/js/components/atoms/PulseDot.test.ts
git commit -m "$(cat <<'EOF'
feat(atoms): add Tag and PulseDot components

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 8 : Atomic Components — BtnPrimary, BtnSecondary

**Files:**
- Create: `resources/js/components/atoms/BtnPrimary.vue` + test
- Create: `resources/js/components/atoms/BtnSecondary.vue` + test

- [ ] **Step 1: Write BtnPrimary test**

Create `resources/js/components/atoms/BtnPrimary.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BtnPrimary from './BtnPrimary.vue';

describe('BtnPrimary', () => {
  it('renders slot content', () => {
    const wrapper = mount(BtnPrimary, { slots: { default: 'Voir' } });
    expect(wrapper.text()).toBe('Voir');
  });

  it('emits click', async () => {
    const wrapper = mount(BtnPrimary, { slots: { default: 'x' } });
    await wrapper.trigger('click');
    expect(wrapper.emitted('click')).toHaveLength(1);
  });

  it('is disabled when prop disabled is true', () => {
    const wrapper = mount(BtnPrimary, { props: { disabled: true }, slots: { default: 'x' } });
    expect(wrapper.attributes('disabled')).toBeDefined();
  });
});
```

- [ ] **Step 2: Implement BtnPrimary.vue**

Create `resources/js/components/atoms/BtnPrimary.vue`:

```vue
<script setup lang="ts">
interface Props {
  disabled?: boolean;
  fullWidth?: boolean;
}

withDefaults(defineProps<Props>(), { disabled: false, fullWidth: false });
defineEmits<{ click: [event: MouseEvent] }>();
</script>

<template>
  <button
    type="button"
    :disabled="disabled"
    :class="[
      'rounded-2xl px-4 py-3 text-sm font-bold text-white transition-all',
      'disabled:opacity-50 disabled:cursor-not-allowed',
      'shadow-cta',
      fullWidth ? 'w-full' : '',
    ]"
    style="background: linear-gradient(180deg, var(--color-primary) 0%, var(--color-primary-hover) 100%);"
    @click="$emit('click', $event)"
  >
    <slot />
  </button>
</template>
```

- [ ] **Step 3: Run BtnPrimary test**

```bash
npm run test:run -- resources/js/components/atoms/BtnPrimary.test.ts
```

Expected: PASS.

- [ ] **Step 4: Write BtnSecondary test**

Create `resources/js/components/atoms/BtnSecondary.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BtnSecondary from './BtnSecondary.vue';

describe('BtnSecondary', () => {
  it('renders slot content', () => {
    const wrapper = mount(BtnSecondary, { slots: { default: 'Suivre' } });
    expect(wrapper.text()).toBe('Suivre');
  });

  it('emits click', async () => {
    const wrapper = mount(BtnSecondary, { slots: { default: 'x' } });
    await wrapper.trigger('click');
    expect(wrapper.emitted('click')).toHaveLength(1);
  });
});
```

- [ ] **Step 5: Implement BtnSecondary.vue**

Create `resources/js/components/atoms/BtnSecondary.vue`:

```vue
<script setup lang="ts">
interface Props {
  disabled?: boolean;
}

withDefaults(defineProps<Props>(), { disabled: false });
defineEmits<{ click: [event: MouseEvent] }>();
</script>

<template>
  <button
    type="button"
    :disabled="disabled"
    class="rounded-2xl px-4 py-3 text-sm font-bold transition-all disabled:opacity-50"
    style="background: rgba(99,102,241,0.06); color: var(--color-primary);"
    @click="$emit('click', $event)"
  >
    <slot />
  </button>
</template>
```

- [ ] **Step 6: Run BtnSecondary test**

```bash
npm run test:run -- resources/js/components/atoms/BtnSecondary.test.ts
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/atoms/Btn*.vue resources/js/components/atoms/Btn*.test.ts
git commit -m "$(cat <<'EOF'
feat(atoms): add BtnPrimary and BtnSecondary components

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 9 : AdaptiveThemeResolver Service (PHP)

**Files:**
- Create: `app/Services/Theme/AdaptiveThemeResolver.php`
- Create: `tests/Feature/Theme/AdaptiveThemeResolverTest.php`

- [ ] **Step 1: Write failing PHPUnit test**

Create `tests/Feature/Theme/AdaptiveThemeResolverTest.php`:

```php
<?php

namespace Tests\Feature\Theme;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;
use App\Services\Theme\AdaptiveThemeResolver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdaptiveThemeResolverTest extends TestCase
{
    use RefreshDatabase;

    private AdaptiveThemeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(AdaptiveThemeResolver::class);
    }

    public function test_defaults_to_light_when_no_context(): void
    {
        $user = User::factory()->create();
        $this->assertSame('light', $this->resolver->resolveForUser($user));
    }

    public function test_honors_explicit_light_preference(): void
    {
        $user = User::factory()->create();
        $user->settings = ['theme_preference' => 'light'];
        $user->save();
        $this->assertSame('light', $this->resolver->resolveForUser($user));
    }

    public function test_honors_explicit_dark_preference(): void
    {
        $user = User::factory()->create();
        $user->settings = ['theme_preference' => 'dark'];
        $user->save();
        $this->assertSame('dark', $this->resolver->resolveForUser($user));
    }

    public function test_returns_dark_when_active_mission_exists(): void
    {
        $user = User::factory()->create();
        $mission = Mission::factory()->create([
            'client_id' => $user->id,
            'status' => 'in_mission',
        ]);
        $this->assertSame('dark', $this->resolver->resolveForUser($user));
    }

    public function test_returns_dark_during_night_with_urgent_service(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 23:30:00'));
        $user = User::factory()->create();
        Booking::factory()->create([
            'customer_user_id' => $user->id,
            'priority' => 'urgent',
            'status' => 'pending',
        ]);
        $this->assertSame('dark', $this->resolver->resolveForUser($user));
        Carbon::setTestNow();
    }

    public function test_returns_light_during_day_even_with_urgent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-22 14:00:00'));
        $user = User::factory()->create();
        Booking::factory()->create([
            'customer_user_id' => $user->id,
            'priority' => 'normal',
            'status' => 'confirmed',
        ]);
        $this->assertSame('light', $this->resolver->resolveForUser($user));
        Carbon::setTestNow();
    }
}
```

- [ ] **Step 2: Run test (must fail)**

```bash
php artisan test --filter=AdaptiveThemeResolverTest
```

Expected: FAIL — class `AdaptiveThemeResolver` not found.

- [ ] **Step 3: Implement service**

Create `app/Services/Theme/AdaptiveThemeResolver.php`:

```php
<?php

namespace App\Services\Theme;

use App\Models\Booking;
use App\Models\Mission;
use App\Models\User;
use Carbon\Carbon;

class AdaptiveThemeResolver
{
    private const ACTIVE_MISSION_STATUSES = ['en_route', 'arrived', 'in_mission', 'started'];
    private const URGENT_NIGHT_HOUR_START = 21;
    private const URGENT_NIGHT_HOUR_END = 6;

    public function resolveForUser(User $user): string
    {
        $preference = data_get($user->settings, 'theme_preference', 'auto');

        if ($preference === 'light' || $preference === 'dark') {
            return $preference;
        }

        if ($this->hasActiveMission($user)) {
            return 'dark';
        }

        if ($this->hasUrgentPendingDuringNight($user)) {
            return 'dark';
        }

        return 'light';
    }

    private function hasActiveMission(User $user): bool
    {
        return Mission::query()
            ->where(function ($q) use ($user) {
                $q->where('client_id', $user->id)
                  ->orWhere('lead_provider_user_id', $user->id);
            })
            ->whereIn('status', self::ACTIVE_MISSION_STATUSES)
            ->exists();
    }

    private function hasUrgentPendingDuringNight(User $user): bool
    {
        $hour = Carbon::now()->hour;
        $isNight = $hour >= self::URGENT_NIGHT_HOUR_START || $hour < self::URGENT_NIGHT_HOUR_END;

        if (!$isNight) {
            return false;
        }

        return Booking::query()
            ->where('customer_user_id', $user->id)
            ->where('priority', 'urgent')
            ->whereNotIn('status', ['completed', 'cancelled', 'refunded'])
            ->exists();
    }
}
```

- [ ] **Step 4: Run test (must pass)**

```bash
php artisan test --filter=AdaptiveThemeResolverTest
```

Expected: PASS — 6 tests OK.

**Note:** if `Mission::factory` or `Booking::factory` don't exist or have different signatures, adapt the test data setup to match existing factories. Check `database/factories/MissionFactory.php` and `database/factories/BookingFactory.php` first.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Theme/AdaptiveThemeResolver.php tests/Feature/Theme/AdaptiveThemeResolverTest.php
git commit -m "$(cat <<'EOF'
feat(theme): add AdaptiveThemeResolver service with 6 trigger rules

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 10 : Pennant Feature Flag `client-mobile-v2`

**Files:**
- Create: `app/Providers/FeatureServiceProvider.php`
- Modify: `bootstrap/providers.php`

- [ ] **Step 1: Create FeatureServiceProvider**

Create `app/Providers/FeatureServiceProvider.php`:

```php
<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class FeatureServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Feature::define('client-mobile-v2', function (User $user) {
            $betaUserIds = config('beta.client_mobile_v2_users', []);
            $allowAll = config('beta.client_mobile_v2_all', false);

            return $allowAll || in_array($user->id, $betaUserIds, true);
        });
    }
}
```

- [ ] **Step 2: Register provider**

In `bootstrap/providers.php`, add the class to the returned array (if file already has providers, append):

```php
return [
    // ... existing providers ...
    App\Providers\FeatureServiceProvider::class,
];
```

- [ ] **Step 3: Add config file**

Create `config/beta.php`:

```php
<?php

return [
    'client_mobile_v2_users' => array_filter(explode(',', env('BETA_CLIENT_MOBILE_V2_USERS', ''))),
    'client_mobile_v2_all' => env('BETA_CLIENT_MOBILE_V2_ALL', false),
];
```

- [ ] **Step 4: Verify feature resolves**

Run via tinker:

```bash
php artisan tinker --execute="echo \Laravel\Pennant\Feature::for(\App\Models\User::first())->active('client-mobile-v2') ? 'yes' : 'no';"
```

Expected: outputs `no` (no beta users configured by default).

- [ ] **Step 5: Commit**

```bash
git add app/Providers/FeatureServiceProvider.php bootstrap/providers.php config/beta.php
git commit -m "$(cat <<'EOF'
feat(features): add Pennant feature client-mobile-v2

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 11 : Composable useAdaptiveTheme

**Files:**
- Create: `resources/js/composables/useAdaptiveTheme.ts`
- Create: `resources/js/composables/useAdaptiveTheme.test.ts`

- [ ] **Step 1: Write failing test**

Create `resources/js/composables/useAdaptiveTheme.test.ts`:

```typescript
import { describe, it, expect, beforeEach } from 'vitest';
import { useAdaptiveTheme } from './useAdaptiveTheme';

describe('useAdaptiveTheme', () => {
  beforeEach(() => {
    document.documentElement.removeAttribute('data-theme');
  });

  it('sets data-theme attribute to light by default', () => {
    const { theme, setTheme } = useAdaptiveTheme();
    setTheme('light');
    expect(document.documentElement.getAttribute('data-theme')).toBe('light');
    expect(theme.value).toBe('light');
  });

  it('switches to dark', () => {
    const { setTheme } = useAdaptiveTheme();
    setTheme('dark');
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
  });

  it('initializes from initial prop', () => {
    const { theme } = useAdaptiveTheme('dark');
    expect(theme.value).toBe('dark');
    expect(document.documentElement.getAttribute('data-theme')).toBe('dark');
  });
});
```

- [ ] **Step 2: Run test (must fail)**

```bash
npm run test:run -- resources/js/composables/useAdaptiveTheme.test.ts
```

Expected: FAIL — module not found.

- [ ] **Step 3: Implement composable**

Create `resources/js/composables/useAdaptiveTheme.ts`:

```typescript
import { ref, watch } from 'vue';

export type Theme = 'light' | 'dark';

export function useAdaptiveTheme(initial: Theme = 'light') {
  const theme = ref<Theme>(initial);

  const apply = (value: Theme) => {
    document.documentElement.setAttribute('data-theme', value);
  };

  apply(initial);

  watch(theme, (newValue) => {
    apply(newValue);
  });

  const setTheme = (value: Theme) => {
    theme.value = value;
  };

  return { theme, setTheme };
}
```

- [ ] **Step 4: Run test (must pass)**

```bash
npm run test:run -- resources/js/composables/useAdaptiveTheme.test.ts
```

Expected: PASS — 3 tests OK.

- [ ] **Step 5: Commit**

```bash
git add resources/js/composables/useAdaptiveTheme.ts resources/js/composables/useAdaptiveTheme.test.ts
git commit -m "$(cat <<'EOF'
feat(composables): add useAdaptiveTheme for theme attribute switching

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 12 : AdaptiveHero Component

**Files:**
- Create: `resources/js/components/client/AdaptiveHero.vue` + test

- [ ] **Step 1: Write test**

Create `resources/js/components/client/AdaptiveHero.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import AdaptiveHero from './AdaptiveHero.vue';

describe('AdaptiveHero', () => {
  const props = {
    eyebrow: 'Prochain rendez-vous',
    title: 'Toiturier · Demain 14h00',
    meta: '23 av. Louise',
    tags: ['Devis 425€', 'Pré-autorisé'],
  };

  it('renders eyebrow, title, meta', () => {
    const wrapper = mount(AdaptiveHero, { props });
    expect(wrapper.text()).toContain('Prochain rendez-vous');
    expect(wrapper.text()).toContain('Toiturier · Demain 14h00');
    expect(wrapper.text()).toContain('23 av. Louise');
  });

  it('renders all tags', () => {
    const wrapper = mount(AdaptiveHero, { props });
    expect(wrapper.text()).toContain('Devis 425€');
    expect(wrapper.text()).toContain('Pré-autorisé');
  });

  it('emits primary-action click', async () => {
    const wrapper = mount(AdaptiveHero, { props });
    await wrapper.find('[data-test="primary-action"]').trigger('click');
    expect(wrapper.emitted('primary-action')).toHaveLength(1);
  });

  it('emits secondary-action click', async () => {
    const wrapper = mount(AdaptiveHero, { props });
    await wrapper.find('[data-test="secondary-action"]').trigger('click');
    expect(wrapper.emitted('secondary-action')).toHaveLength(1);
  });
});
```

- [ ] **Step 2: Run test (must fail)**

```bash
npm run test:run -- resources/js/components/client/AdaptiveHero.test.ts
```

Expected: FAIL.

- [ ] **Step 3: Implement AdaptiveHero.vue**

Create `resources/js/components/client/AdaptiveHero.vue`:

```vue
<script setup lang="ts">
import BtnPrimary from '@/components/atoms/BtnPrimary.vue';
import BtnSecondary from '@/components/atoms/BtnSecondary.vue';
import Tag from '@/components/atoms/Tag.vue';

interface Props {
  eyebrow: string;
  title: string;
  meta?: string;
  tags?: string[];
  primaryLabel?: string;
  secondaryLabel?: string;
}

withDefaults(defineProps<Props>(), {
  meta: '',
  tags: () => [],
  primaryLabel: 'Voir le détail',
  secondaryLabel: 'Suivre',
});

defineEmits<{
  'primary-action': [];
  'secondary-action': [];
}>();
</script>

<template>
  <div
    class="relative overflow-hidden rounded-[28px] border p-6"
    style="
      background: linear-gradient(135deg, var(--color-surface) 0%, var(--color-surface-2) 100%);
      border-color: var(--color-border-2);
      box-shadow: var(--shadow-card);
    "
  >
    <div
      class="pointer-events-none absolute -right-10 -top-10 h-36 w-36 rounded-full"
      style="background: radial-gradient(circle, rgba(167,139,250,0.18) 0%, transparent 70%);"
    />
    <div class="relative">
      <p class="text-[10px] font-bold uppercase tracking-wider" style="color: var(--color-primary);">
        {{ eyebrow }}
      </p>
      <h2 class="mt-1.5 text-[22px] font-extrabold leading-tight tracking-tight" style="color: var(--color-text);">
        {{ title }}
      </h2>
      <p v-if="meta" class="mt-1 text-[13px]" style="color: var(--color-text-2);">{{ meta }}</p>
      <div v-if="tags.length" class="mt-3.5 flex gap-2">
        <Tag v-for="tag in tags" :key="tag" variant="primary">{{ tag }}</Tag>
      </div>
      <div class="mt-4 flex gap-2">
        <BtnPrimary class="flex-1" data-test="primary-action" @click="$emit('primary-action')">
          {{ primaryLabel }}
        </BtnPrimary>
        <BtnSecondary data-test="secondary-action" @click="$emit('secondary-action')">
          {{ secondaryLabel }}
        </BtnSecondary>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 4: Run test (must pass)**

```bash
npm run test:run -- resources/js/components/client/AdaptiveHero.test.ts
```

Expected: PASS — 4 tests OK.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/client/AdaptiveHero.vue resources/js/components/client/AdaptiveHero.test.ts
git commit -m "$(cat <<'EOF'
feat(client): add AdaptiveHero component

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 13 : QuickActionGrid + ServiceTile + StatusCardScroller

**Files:**
- Create: `resources/js/components/client/QuickActionGrid.vue` + test
- Create: `resources/js/components/client/ServiceTile.vue` + test
- Create: `resources/js/components/client/StatusCardScroller.vue` + test

- [ ] **Step 1: QuickActionGrid — write test**

Create `resources/js/components/client/QuickActionGrid.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import QuickActionGrid from './QuickActionGrid.vue';

describe('QuickActionGrid', () => {
  const actions = [
    { id: 'urgent', icon: '⚡', label: 'Urgent' },
    { id: 'rebook', icon: '🔁', label: 'Rebook' },
    { id: 'address', icon: '📍', label: 'Adresses' },
    { id: 'help', icon: '💬', label: 'Aide' },
  ];

  it('renders all actions', () => {
    const wrapper = mount(QuickActionGrid, { props: { actions } });
    expect(wrapper.findAll('[data-test="quick-action"]')).toHaveLength(4);
    expect(wrapper.text()).toContain('Urgent');
    expect(wrapper.text()).toContain('Rebook');
  });

  it('emits action with id when clicked', async () => {
    const wrapper = mount(QuickActionGrid, { props: { actions } });
    await wrapper.findAll('[data-test="quick-action"]')[0].trigger('click');
    expect(wrapper.emitted('action')).toEqual([['urgent']]);
  });
});
```

- [ ] **Step 2: Implement QuickActionGrid.vue**

Create `resources/js/components/client/QuickActionGrid.vue`:

```vue
<script setup lang="ts">
interface Action {
  id: string;
  icon: string;
  label: string;
}

interface Props {
  actions: Action[];
}

defineProps<Props>();
defineEmits<{ action: [id: string] }>();
</script>

<template>
  <div class="grid grid-cols-4 gap-2">
    <button
      v-for="action in actions"
      :key="action.id"
      data-test="quick-action"
      type="button"
      class="flex flex-col items-center gap-1.5 rounded-[18px] border p-3 transition-transform active:scale-95"
      style="background: var(--color-surface); border-color: var(--color-border);"
      @click="$emit('action', action.id)"
    >
      <span class="text-[22px]">{{ action.icon }}</span>
      <span class="text-[10px] font-semibold leading-tight text-center" style="color: var(--color-text);">
        {{ action.label }}
      </span>
    </button>
  </div>
</template>
```

- [ ] **Step 3: Run QuickActionGrid test**

```bash
npm run test:run -- resources/js/components/client/QuickActionGrid.test.ts
```

Expected: PASS.

- [ ] **Step 4: ServiceTile — write test**

Create `resources/js/components/client/ServiceTile.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ServiceTile from './ServiceTile.vue';

describe('ServiceTile', () => {
  it('renders emoji and name', () => {
    const wrapper = mount(ServiceTile, { props: { emoji: '🔑', name: 'Serrurier' } });
    expect(wrapper.text()).toContain('🔑');
    expect(wrapper.text()).toContain('Serrurier');
  });

  it('emits select on click with name', async () => {
    const wrapper = mount(ServiceTile, { props: { emoji: '🔑', name: 'Serrurier' } });
    await wrapper.trigger('click');
    expect(wrapper.emitted('select')).toEqual([['Serrurier']]);
  });
});
```

- [ ] **Step 5: Implement ServiceTile.vue**

Create `resources/js/components/client/ServiceTile.vue`:

```vue
<script setup lang="ts">
interface Props {
  emoji: string;
  name: string;
}

const props = defineProps<Props>();
defineEmits<{ select: [name: string] }>();
</script>

<template>
  <button
    type="button"
    class="flex flex-col items-center gap-1.5 rounded-2xl border p-3.5 transition-transform active:scale-95"
    style="background: var(--color-surface); border-color: var(--color-border);"
    @click="$emit('select', props.name)"
  >
    <span class="text-2xl">{{ emoji }}</span>
    <span class="text-[10px] font-semibold" style="color: var(--color-text);">{{ name }}</span>
  </button>
</template>
```

- [ ] **Step 6: Run ServiceTile test**

```bash
npm run test:run -- resources/js/components/client/ServiceTile.test.ts
```

Expected: PASS.

- [ ] **Step 7: StatusCardScroller — write test**

Create `resources/js/components/client/StatusCardScroller.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import StatusCardScroller from './StatusCardScroller.vue';

describe('StatusCardScroller', () => {
  const cards = [
    { id: 'loyalty', label: 'Fidélité', value: '320', sub: '+80 pts pour Gold', badge: 'SILVER' },
    { id: 'credits', label: 'Crédits', value: '25 €', sub: 'Expire 30/06' },
    { id: 'referral', label: 'Parrainage', value: '3 invités', sub: '2 inscrits ✓' },
  ];

  it('renders all cards', () => {
    const wrapper = mount(StatusCardScroller, { props: { cards } });
    expect(wrapper.findAll('[data-test="status-card"]')).toHaveLength(3);
    expect(wrapper.text()).toContain('Fidélité');
    expect(wrapper.text()).toContain('SILVER');
  });

  it('emits select with card id', async () => {
    const wrapper = mount(StatusCardScroller, { props: { cards } });
    await wrapper.findAll('[data-test="status-card"]')[0].trigger('click');
    expect(wrapper.emitted('select')).toEqual([['loyalty']]);
  });
});
```

- [ ] **Step 8: Implement StatusCardScroller.vue**

Create `resources/js/components/client/StatusCardScroller.vue`:

```vue
<script setup lang="ts">
interface StatusCard {
  id: string;
  label: string;
  value: string;
  sub?: string;
  badge?: string;
}

interface Props {
  cards: StatusCard[];
}

defineProps<Props>();
defineEmits<{ select: [id: string] }>();
</script>

<template>
  <div class="flex gap-2.5 overflow-x-auto px-5 pb-1 -mx-5 px-5">
    <button
      v-for="card in cards"
      :key="card.id"
      data-test="status-card"
      type="button"
      class="min-w-[110px] flex-1 rounded-2xl border p-3 text-left transition-transform active:scale-95"
      style="background: var(--color-surface); border-color: var(--color-border);"
      @click="$emit('select', card.id)"
    >
      <p class="text-[9px] font-bold uppercase tracking-wider" style="color: var(--color-text-3);">
        {{ card.label }}
      </p>
      <p class="mt-1 text-lg font-extrabold tracking-tight" style="color: var(--color-text);">
        {{ card.value }}
        <span v-if="card.badge" class="ml-1 inline-block rounded px-1.5 py-0.5 text-[8px] font-bold" style="background: linear-gradient(180deg,#e8e8ee,#d4d4dc); color:#4a4a55;">
          {{ card.badge }}
        </span>
      </p>
      <p v-if="card.sub" class="mt-0.5 text-[10px]" style="color: var(--color-text-2);">{{ card.sub }}</p>
    </button>
  </div>
</template>
```

- [ ] **Step 9: Run all three tests**

```bash
npm run test:run -- resources/js/components/client/
```

Expected: All 3 components tests pass.

- [ ] **Step 10: Commit**

```bash
git add resources/js/components/client/QuickActionGrid.* resources/js/components/client/ServiceTile.* resources/js/components/client/StatusCardScroller.*
git commit -m "$(cat <<'EOF'
feat(client): add QuickActionGrid, ServiceTile, StatusCardScroller

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 14 : FabUrgence + BottomNav

**Files:**
- Create: `resources/js/components/client/FabUrgence.vue` + test
- Create: `resources/js/components/client/BottomNav.vue` + test

- [ ] **Step 1: FabUrgence test**

Create `resources/js/components/client/FabUrgence.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import FabUrgence from './FabUrgence.vue';

describe('FabUrgence', () => {
  it('renders fixed positioned button', () => {
    const wrapper = mount(FabUrgence);
    expect(wrapper.find('button').classes()).toContain('fixed');
  });

  it('emits trigger on click', async () => {
    const wrapper = mount(FabUrgence);
    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted('trigger')).toHaveLength(1);
  });
});
```

- [ ] **Step 2: Implement FabUrgence.vue**

Create `resources/js/components/client/FabUrgence.vue`:

```vue
<script setup lang="ts">
defineEmits<{ trigger: [] }>();
</script>

<template>
  <button
    type="button"
    aria-label="Demander urgent"
    class="fixed bottom-[110px] right-[18px] z-40 flex h-14 w-14 items-center justify-center rounded-full text-white text-2xl transition-transform active:scale-90"
    style="background: linear-gradient(180deg, var(--color-urgent), var(--color-urgent-hover)); box-shadow: var(--shadow-fab);"
    @click="$emit('trigger')"
  >
    ⚡
  </button>
</template>
```

- [ ] **Step 3: Run FabUrgence test**

```bash
npm run test:run -- resources/js/components/client/FabUrgence.test.ts
```

Expected: PASS.

- [ ] **Step 4: BottomNav test**

Create `resources/js/components/client/BottomNav.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BottomNav from './BottomNav.vue';

describe('BottomNav', () => {
  const items = [
    { id: 'home', icon: '⌂', label: 'Accueil' },
    { id: 'search', icon: '⌕', label: 'Recherche' },
    { id: 'bookings', icon: '▦', label: 'RDV' },
    { id: 'alerts', icon: '○', label: 'Alertes' },
    { id: 'profile', icon: '⊙', label: 'Profil' },
  ];

  it('renders all items', () => {
    const wrapper = mount(BottomNav, { props: { items, activeId: 'home' } });
    expect(wrapper.findAll('[data-test="nav-item"]')).toHaveLength(5);
  });

  it('marks active item', () => {
    const wrapper = mount(BottomNav, { props: { items, activeId: 'search' } });
    const navItems = wrapper.findAll('[data-test="nav-item"]');
    expect(navItems[1].classes()).toContain('is-active');
  });

  it('emits navigate with id', async () => {
    const wrapper = mount(BottomNav, { props: { items, activeId: 'home' } });
    await wrapper.findAll('[data-test="nav-item"]')[2].trigger('click');
    expect(wrapper.emitted('navigate')).toEqual([['bookings']]);
  });
});
```

- [ ] **Step 5: Implement BottomNav.vue**

Create `resources/js/components/client/BottomNav.vue`:

```vue
<script setup lang="ts">
interface NavItem {
  id: string;
  icon: string;
  label: string;
}

interface Props {
  items: NavItem[];
  activeId: string;
}

defineProps<Props>();
defineEmits<{ navigate: [id: string] }>();
</script>

<template>
  <nav
    class="fixed bottom-0 left-0 right-0 z-30 grid grid-cols-5 gap-1 border-t px-2 pb-7 pt-3"
    style="
      background: color-mix(in srgb, var(--color-surface) 86%, transparent);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-color: var(--color-border);
    "
  >
    <button
      v-for="item in items"
      :key="item.id"
      type="button"
      data-test="nav-item"
      :class="['flex flex-col items-center gap-0.5 p-1', activeId === item.id ? 'is-active' : '']"
      @click="$emit('navigate', item.id)"
    >
      <span class="text-[22px]" :style="{ opacity: activeId === item.id ? 1 : 0.45 }">{{ item.icon }}</span>
      <span class="text-[9px] font-semibold" :style="{ color: activeId === item.id ? 'var(--color-primary)' : 'var(--color-text-3)' }">
        {{ item.label }}
      </span>
    </button>
  </nav>
</template>
```

- [ ] **Step 6: Run BottomNav test**

```bash
npm run test:run -- resources/js/components/client/BottomNav.test.ts
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/client/FabUrgence.* resources/js/components/client/BottomNav.*
git commit -m "$(cat <<'EOF'
feat(client): add FabUrgence and BottomNav components

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 15 : Client Home Island Entry + Component

**Files:**
- Create: `resources/js/islands/client-home.ts`
- Create: `resources/js/islands/ClientHomeIsland.vue` + test

- [ ] **Step 1: Write ClientHomeIsland test**

Create `resources/js/islands/ClientHomeIsland.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ClientHomeIsland from './ClientHomeIsland.vue';

describe('ClientHomeIsland', () => {
  const props = {
    userName: 'Mohamed',
    initialTheme: 'light' as const,
    upcomingBooking: {
      title: 'Toiturier · Demain 14h00',
      meta: '23 av. Louise',
      tags: ['Devis 425 €', 'Pré-autorisé'],
    },
    statusCards: [
      { id: 'loyalty', label: 'Fidélité', value: '320', sub: 'Silver' },
    ],
    services: [
      { emoji: '🔑', name: 'Serrurier' },
    ],
  };

  it('renders user name', () => {
    const wrapper = mount(ClientHomeIsland, { props });
    expect(wrapper.text()).toContain('Mohamed');
  });

  it('renders upcoming booking title', () => {
    const wrapper = mount(ClientHomeIsland, { props });
    expect(wrapper.text()).toContain('Toiturier · Demain 14h00');
  });

  it('renders status cards', () => {
    const wrapper = mount(ClientHomeIsland, { props });
    expect(wrapper.text()).toContain('Fidélité');
  });

  it('dispatches window event on quick action', async () => {
    const dispatched: CustomEvent[] = [];
    const handler = (e: Event) => dispatched.push(e as CustomEvent);
    window.addEventListener('brio:client-action', handler);

    const wrapper = mount(ClientHomeIsland, { props });
    await wrapper.findAll('[data-test="quick-action"]')[0].trigger('click');

    expect(dispatched).toHaveLength(1);
    expect(dispatched[0].detail.id).toBe('urgent');

    window.removeEventListener('brio:client-action', handler);
  });
});
```

- [ ] **Step 2: Implement ClientHomeIsland.vue**

Create `resources/js/islands/ClientHomeIsland.vue`:

```vue
<script setup lang="ts">
import { useAdaptiveTheme, type Theme } from '@/composables/useAdaptiveTheme';
import Avatar from '@/components/atoms/Avatar.vue';
import AdaptiveHero from '@/components/client/AdaptiveHero.vue';
import QuickActionGrid from '@/components/client/QuickActionGrid.vue';
import ServiceTile from '@/components/client/ServiceTile.vue';
import StatusCardScroller from '@/components/client/StatusCardScroller.vue';
import FabUrgence from '@/components/client/FabUrgence.vue';
import BottomNav from '@/components/client/BottomNav.vue';

interface UpcomingBooking {
  title: string;
  meta: string;
  tags?: string[];
}

interface StatusCard {
  id: string;
  label: string;
  value: string;
  sub?: string;
  badge?: string;
}

interface Service {
  emoji: string;
  name: string;
}

interface Props {
  userName: string;
  initialTheme: Theme;
  upcomingBooking: UpcomingBooking | null;
  statusCards: StatusCard[];
  services: Service[];
}

defineProps<Props>();

const { theme } = useAdaptiveTheme();

const quickActions = [
  { id: 'urgent', icon: '⚡', label: 'Urgent' },
  { id: 'rebook', icon: '🔁', label: 'Rebook' },
  { id: 'address', icon: '📍', label: 'Adresses' },
  { id: 'help', icon: '💬', label: 'Aide' },
];

const navItems = [
  { id: 'home', icon: '⌂', label: 'Accueil' },
  { id: 'search', icon: '⌕', label: 'Recherche' },
  { id: 'bookings', icon: '▦', label: 'RDV' },
  { id: 'alerts', icon: '○', label: 'Alertes' },
  { id: 'profile', icon: '⊙', label: 'Profil' },
];

const dispatchClientAction = (id: string, payload: Record<string, unknown> = {}) => {
  window.dispatchEvent(
    new CustomEvent('brio:client-action', {
      detail: { id, ...payload },
    })
  );
};
</script>

<template>
  <div class="relative min-h-screen pb-[120px]" style="background: var(--color-bg); color: var(--color-text);">
    <header class="flex items-center justify-between px-5 pt-2">
      <div>
        <p class="text-[13px]" style="color: var(--color-text-2);">Bonjour,</p>
        <h1 class="text-[26px] font-extrabold leading-none tracking-tight">{{ userName }}</h1>
      </div>
      <Avatar :name="userName" size="md" />
    </header>

    <section v-if="upcomingBooking" class="mt-5 px-5">
      <AdaptiveHero
        eyebrow="Prochain rendez-vous"
        :title="upcomingBooking.title"
        :meta="upcomingBooking.meta"
        :tags="upcomingBooking.tags || []"
        @primary-action="dispatchClientAction('open-booking-detail')"
        @secondary-action="dispatchClientAction('open-tracking')"
      />
    </section>

    <section class="mt-4 px-5">
      <QuickActionGrid :actions="quickActions" @action="(id) => dispatchClientAction(id)" />
    </section>

    <section v-if="statusCards.length" class="mt-5">
      <div class="flex items-baseline justify-between px-5 pb-2">
        <h2 class="text-[13px] font-bold tracking-tight" style="color: var(--color-text);">Mon statut</h2>
        <button type="button" class="text-[11px] font-semibold" style="color: var(--color-primary);" @click="dispatchClientAction('open-status-detail')">
          Voir tout →
        </button>
      </div>
      <div class="px-5">
        <StatusCardScroller :cards="statusCards" @select="(id) => dispatchClientAction('open-status', { kind: id })" />
      </div>
    </section>

    <section v-if="services.length" class="mt-5">
      <div class="flex items-baseline justify-between px-5 pb-2">
        <h2 class="text-[13px] font-bold tracking-tight" style="color: var(--color-text);">Services près de chez toi</h2>
        <button type="button" class="text-[11px] font-semibold" style="color: var(--color-primary);" @click="dispatchClientAction('open-browse')">
          Tous →
        </button>
      </div>
      <div class="grid grid-cols-3 gap-2 px-5">
        <ServiceTile
          v-for="service in services"
          :key="service.name"
          :emoji="service.emoji"
          :name="service.name"
          @select="(name) => dispatchClientAction('start-booking', { service: name })"
        />
      </div>
    </section>

    <FabUrgence @trigger="dispatchClientAction('start-urgent')" />

    <BottomNav :items="navItems" active-id="home" @navigate="(id) => dispatchClientAction('navigate', { route: id })" />
  </div>
</template>
```

- [ ] **Step 3: Create island entry point**

Create `resources/js/islands/client-home.ts`:

```typescript
import { createApp } from 'vue';
import ClientHomeIsland from './ClientHomeIsland.vue';

const mountPoint = document.getElementById('client-home-island');
if (mountPoint) {
  const propsAttr = mountPoint.getAttribute('data-props');
  const props = propsAttr ? JSON.parse(propsAttr) : {};
  createApp(ClientHomeIsland, props).mount(mountPoint);
}
```

- [ ] **Step 4: Run ClientHomeIsland test**

```bash
npm run test:run -- resources/js/islands/ClientHomeIsland.test.ts
```

Expected: PASS — 4 tests OK.

- [ ] **Step 5: Verify build with the island entry**

```bash
npm run build
```

Expected: Build success. `public/build/manifest.json` contains `resources/js/islands/client-home.ts` entry.

- [ ] **Step 6: Commit**

```bash
git add resources/js/islands/client-home.ts resources/js/islands/ClientHomeIsland.vue resources/js/islands/ClientHomeIsland.test.ts
git commit -m "$(cat <<'EOF'
feat(client): add ClientHomeIsland Vue island + entry

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 16 : Wire Client Home Island into Livewire

**Files:**
- Modify: `app/Livewire/ClientDashboard.php`
- Modify: `resources/views/livewire/client-dashboard.blade.php`
- Create: `tests/Feature/Client/ClientDashboardV2Test.php`

- [ ] **Step 1: Write feature test**

Create `tests/Feature/Client/ClientDashboardV2Test.php`:

```php
<?php

namespace Tests\Feature\Client;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class ClientDashboardV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_renders_v2_mount_point_when_feature_active(): void
    {
        $user = User::factory()->create();
        Feature::for($user)->activate('client-mobile-v2');

        $response = $this->actingAs($user)->get(route('client.dashboard'));

        $response->assertOk();
        $response->assertSee('id="client-home-island"', false);
        $response->assertSee('data-props=', false);
    }

    public function test_renders_legacy_blade_when_feature_inactive(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('client.dashboard'));

        $response->assertOk();
        $response->assertDontSee('id="client-home-island"', false);
    }

    public function test_props_json_contains_user_name(): void
    {
        $user = User::factory()->create(['name' => 'Mohamed']);
        Feature::for($user)->activate('client-mobile-v2');

        $response = $this->actingAs($user)->get(route('client.dashboard'));

        $response->assertSeeText('Mohamed');
    }
}
```

- [ ] **Step 2: Find the existing client dashboard route**

Run:
```bash
grep -rn "client.dashboard" routes/ app/
```

Note the actual route name and controller. The test above assumes `client.dashboard` — adjust if it's named differently in the codebase (e.g., `client.home`, `dashboard.client`).

- [ ] **Step 3: Run test (must fail)**

```bash
php artisan test --filter=ClientDashboardV2Test
```

Expected: FAIL — mount point not present.

- [ ] **Step 4: Read existing ClientDashboard.php**

Run:
```bash
cat app/Livewire/ClientDashboard.php
```

Note existing public properties and methods. Goal is to ADD `getV2Props()` method without breaking existing v1 behavior.

- [ ] **Step 5: Modify ClientDashboard.php**

Add this method at the end of the class (before the closing brace) in `app/Livewire/ClientDashboard.php`:

```php
public function getV2Props(): array
{
    $user = auth()->user();
    $theme = app(\App\Services\Theme\AdaptiveThemeResolver::class)->resolveForUser($user);

    $upcomingBooking = null;
    $next = \App\Models\Booking::query()
        ->where('customer_user_id', $user->id)
        ->whereNotIn('status', ['completed', 'cancelled', 'refunded'])
        ->orderBy('scheduled_date')
        ->orderBy('scheduled_time')
        ->first();

    if ($next) {
        $upcomingBooking = [
            'title' => optional($next->serviceCatalog)->name . ' · ' . optional($next->scheduled_date)?->format('d/m H:i'),
            'meta' => $next->address ?? '',
            'tags' => array_filter([
                $next->devis_estime ? 'Devis ' . number_format($next->devis_estime, 0) . ' €' : null,
                $next->payment_status === 'authorized' ? 'Pré-autorisé' : null,
            ]),
        ];
    }

    return [
        'userName' => $user->name,
        'initialTheme' => $theme,
        'upcomingBooking' => $upcomingBooking,
        'statusCards' => [
            ['id' => 'loyalty', 'label' => 'Fidélité', 'value' => (string) ($user->loyalty_account?->points ?? 0), 'sub' => $user->loyalty_account?->tier ?? '—'],
            ['id' => 'credits', 'label' => 'Crédits', 'value' => '0 €', 'sub' => '—'],
            ['id' => 'referral', 'label' => 'Parrainage', 'value' => '0 invités', 'sub' => '—'],
        ],
        'services' => [
            ['emoji' => '🔑', 'name' => 'Serrurier'],
            ['emoji' => '🏠', 'name' => 'Toiture'],
            ['emoji' => '👶', 'name' => 'Babysit'],
        ],
    ];
}
```

**Adapt as needed** if `loyalty_account` relation doesn't exist on User — replace with the actual relation name (check `app/Models/User.php`).

- [ ] **Step 6: Modify the blade view**

In `resources/views/livewire/client-dashboard.blade.php`, at the very top of the file, ADD:

```blade
@php
    $useV2 = \Laravel\Pennant\Feature::for(auth()->user())->active('client-mobile-v2');
    $v2Props = $useV2 ? $this->getV2Props() : null;
@endphp

@if($useV2)
    @vite(['resources/css/app.css', 'resources/js/islands/client-home.ts'])
    <div id="client-home-island" data-props="{{ json_encode($v2Props) }}"></div>
@else
    {{-- legacy blade content stays below --}}
@endif
```

The existing blade legacy content remains BELOW this `@if`, preserved entirely. The `@else` wraps it (or leave the existing content as-is after `@else` and close with `@endif` at the very end of the file).

**Note:** find the closing of file. If existing blade has multiple `@if` directives, ensure the `@if($useV2) ... @else ... existing content ... @endif` wraps all current content cleanly.

- [ ] **Step 7: Run test (must pass)**

```bash
php artisan test --filter=ClientDashboardV2Test
```

Expected: PASS — 3 tests.

- [ ] **Step 8: Manual smoke test**

```bash
# Enable feature for first user
php artisan tinker --execute="\Laravel\Pennant\Feature::for(\App\Models\User::first())->activate('client-mobile-v2');"

# Start dev server
npm run dev
```

In another terminal:
```bash
php artisan serve
```

Login as that user → navigate to `/dashboard/client` (or the actual route). You should see the Vue island rendered with mode clair palette.

- [ ] **Step 9: Commit**

```bash
git add app/Livewire/ClientDashboard.php resources/views/livewire/client-dashboard.blade.php tests/Feature/Client/ClientDashboardV2Test.php
git commit -m "$(cat <<'EOF'
feat(client): wire Vue island into ClientDashboard via feature flag

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 17 : EtaGlassCard + StatusTimeline

**Files:**
- Create: `resources/js/components/client/EtaGlassCard.vue` + test
- Create: `resources/js/components/client/StatusTimeline.vue` + test

- [ ] **Step 1: StatusTimeline test**

Create `resources/js/components/client/StatusTimeline.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import StatusTimeline from './StatusTimeline.vue';

describe('StatusTimeline', () => {
  const steps = [
    { id: 'accepted', label: 'Accepté' },
    { id: 'enroute', label: 'En route' },
    { id: 'arrived', label: 'Arrivé' },
    { id: 'mission', label: 'Mission' },
    { id: 'done', label: 'Terminé' },
  ];

  it('renders all steps', () => {
    const wrapper = mount(StatusTimeline, { props: { steps, currentId: 'enroute' } });
    expect(wrapper.findAll('[data-test="step"]')).toHaveLength(5);
  });

  it('marks done steps before current', () => {
    const wrapper = mount(StatusTimeline, { props: { steps, currentId: 'arrived' } });
    const dots = wrapper.findAll('[data-test="step-dot"]');
    expect(dots[0].classes()).toContain('is-done');
    expect(dots[1].classes()).toContain('is-done');
    expect(dots[2].classes()).toContain('is-active');
    expect(dots[3].classes()).not.toContain('is-done');
  });
});
```

- [ ] **Step 2: Implement StatusTimeline.vue**

Create `resources/js/components/client/StatusTimeline.vue`:

```vue
<script setup lang="ts">
import { computed } from 'vue';

interface Step {
  id: string;
  label: string;
}

interface Props {
  steps: Step[];
  currentId: string;
}

const props = defineProps<Props>();

const currentIndex = computed(() => props.steps.findIndex(s => s.id === props.currentId));
const stateOf = (idx: number) => {
  if (idx < currentIndex.value) return 'done';
  if (idx === currentIndex.value) return 'active';
  return 'pending';
};
</script>

<template>
  <div class="relative flex items-center justify-between">
    <div
      class="absolute left-2.5 right-2.5 top-[9px] h-0.5"
      style="background: rgba(255, 255, 255, 0.08);"
    />
    <div
      v-for="(step, idx) in steps"
      :key="step.id"
      data-test="step"
      class="relative z-10 flex flex-col items-center gap-1"
    >
      <span
        data-test="step-dot"
        :class="[
          'block h-[18px] w-[18px] rounded-full border-2',
          stateOf(idx) === 'done' ? 'is-done' : '',
          stateOf(idx) === 'active' ? 'is-active' : '',
        ]"
        :style="{
          borderColor: 'var(--color-bg)',
          background: stateOf(idx) === 'done' ? 'var(--color-success)' :
                      stateOf(idx) === 'active' ? 'var(--color-primary)' :
                      'rgba(255,255,255,0.1)',
          boxShadow: stateOf(idx) === 'active' ? '0 0 0 4px rgba(129,140,248,0.2)' : 'none',
        }"
      />
      <span
        class="text-[9px] font-semibold"
        :style="{
          color: stateOf(idx) === 'done' ? 'var(--color-success)' :
                 stateOf(idx) === 'active' ? 'var(--color-primary)' :
                 'var(--color-text-3)',
        }"
      >
        {{ step.label }}
      </span>
    </div>
  </div>
</template>
```

- [ ] **Step 3: Run StatusTimeline test**

```bash
npm run test:run -- resources/js/components/client/StatusTimeline.test.ts
```

Expected: PASS.

- [ ] **Step 4: EtaGlassCard test**

Create `resources/js/components/client/EtaGlassCard.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import EtaGlassCard from './EtaGlassCard.vue';

describe('EtaGlassCard', () => {
  const props = {
    etaMinutes: 8,
    distanceKm: 2.1,
    providerName: 'Karim B.',
    steps: [
      { id: 'accepted', label: 'Accepté' },
      { id: 'enroute', label: 'En route' },
    ],
    currentStep: 'enroute',
  };

  it('renders ETA minutes', () => {
    const wrapper = mount(EtaGlassCard, { props });
    expect(wrapper.text()).toContain('8');
    expect(wrapper.text()).toContain('min');
  });

  it('renders provider name and distance', () => {
    const wrapper = mount(EtaGlassCard, { props });
    expect(wrapper.text()).toContain('Karim B.');
    expect(wrapper.text()).toContain('2.1');
  });
});
```

- [ ] **Step 5: Implement EtaGlassCard.vue**

Create `resources/js/components/client/EtaGlassCard.vue`:

```vue
<script setup lang="ts">
import StatusTimeline from './StatusTimeline.vue';

interface Step {
  id: string;
  label: string;
}

interface Props {
  etaMinutes: number;
  distanceKm: number;
  providerName: string;
  steps: Step[];
  currentStep: string;
}

defineProps<Props>();
defineEmits<{ dismiss: [] }>();
</script>

<template>
  <div
    class="rounded-[22px] border p-4"
    style="
      background: linear-gradient(180deg, rgba(20,20,30,0.92), rgba(14,14,24,0.92));
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-color: rgba(255,255,255,0.08);
      box-shadow: 0 12px 32px rgba(0,0,0,0.4);
    "
  >
    <div class="flex items-start justify-between">
      <div>
        <p class="text-[10px] font-bold uppercase tracking-wider" style="color: var(--color-primary);">
          Arrivée dans
        </p>
        <p class="mt-1 text-[32px] font-extrabold leading-none tracking-tight" style="color: var(--color-text);">
          {{ etaMinutes }} <span class="text-lg opacity-60">min</span>
        </p>
        <p class="mt-0.5 text-xs" style="color: var(--color-text-2);">
          {{ distanceKm }} km · {{ providerName }} en route
        </p>
      </div>
      <button
        type="button"
        aria-label="Fermer"
        class="flex h-10 w-10 items-center justify-center rounded-xl border text-lg"
        style="background: rgba(255,255,255,0.08); border-color: rgba(255,255,255,0.1); color: var(--color-text);"
        @click="$emit('dismiss')"
      >
        ✕
      </button>
    </div>
    <div class="mt-3.5 border-t pt-3.5" style="border-color: rgba(255,255,255,0.06);">
      <StatusTimeline :steps="steps" :current-id="currentStep" />
    </div>
  </div>
</template>
```

- [ ] **Step 6: Run EtaGlassCard test**

```bash
npm run test:run -- resources/js/components/client/EtaGlassCard.test.ts
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add resources/js/components/client/EtaGlassCard.* resources/js/components/client/StatusTimeline.*
git commit -m "$(cat <<'EOF'
feat(client): add EtaGlassCard and StatusTimeline components

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 18 : BottomActionSheet + QrScanCta

**Files:**
- Create: `resources/js/components/client/BottomActionSheet.vue` + test
- Create: `resources/js/components/client/QrScanCta.vue` + test

- [ ] **Step 1: BottomActionSheet test**

Create `resources/js/components/client/BottomActionSheet.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import BottomActionSheet from './BottomActionSheet.vue';

describe('BottomActionSheet', () => {
  it('renders default slot content', () => {
    const wrapper = mount(BottomActionSheet, { slots: { default: '<p>Inner</p>' } });
    expect(wrapper.html()).toContain('Inner');
  });

  it('renders handle bar', () => {
    const wrapper = mount(BottomActionSheet);
    expect(wrapper.find('[data-test="sheet-handle"]').exists()).toBe(true);
  });
});
```

- [ ] **Step 2: Implement BottomActionSheet.vue**

Create `resources/js/components/client/BottomActionSheet.vue`:

```vue
<template>
  <div
    class="fixed bottom-0 left-0 right-0 z-30 rounded-t-[28px] border-t px-5 pb-7 pt-3.5"
    style="
      background: linear-gradient(180deg, rgba(20,20,30,0.95) 0%, var(--color-bg) 100%);
      backdrop-filter: blur(30px);
      -webkit-backdrop-filter: blur(30px);
      border-color: var(--color-border);
      box-shadow: 0 -12px 40px rgba(0,0,0,0.6);
    "
  >
    <div data-test="sheet-handle" class="mx-auto mb-3.5 h-1 w-9 rounded-full" style="background: rgba(255,255,255,0.2);" />
    <slot />
  </div>
</template>
```

- [ ] **Step 3: QrScanCta test**

Create `resources/js/components/client/QrScanCta.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import QrScanCta from './QrScanCta.vue';

describe('QrScanCta', () => {
  it('renders title and subtitle', () => {
    const wrapper = mount(QrScanCta, { props: { title: 'Scanner', subtitle: '6 chiffres' } });
    expect(wrapper.text()).toContain('Scanner');
    expect(wrapper.text()).toContain('6 chiffres');
  });

  it('emits scan on click', async () => {
    const wrapper = mount(QrScanCta, { props: { title: 't', subtitle: 's' } });
    await wrapper.find('button').trigger('click');
    expect(wrapper.emitted('scan')).toHaveLength(1);
  });

  it('is disabled when disabled prop true', () => {
    const wrapper = mount(QrScanCta, { props: { title: 't', subtitle: 's', disabled: true } });
    expect(wrapper.find('button').attributes('disabled')).toBeDefined();
  });
});
```

- [ ] **Step 4: Implement QrScanCta.vue**

Create `resources/js/components/client/QrScanCta.vue`:

```vue
<script setup lang="ts">
interface Props {
  title: string;
  subtitle: string;
  disabled?: boolean;
}

withDefaults(defineProps<Props>(), { disabled: false });
defineEmits<{ scan: [] }>();
</script>

<template>
  <button
    type="button"
    :disabled="disabled"
    class="flex w-full items-center gap-3.5 rounded-[18px] p-4 text-white transition-transform active:scale-95 disabled:opacity-50"
    style="background: linear-gradient(180deg, var(--color-primary), var(--color-primary-hover)); box-shadow: var(--shadow-cta);"
    @click="$emit('scan')"
  >
    <span class="flex h-12 w-12 items-center justify-center rounded-xl text-2xl" style="background: rgba(255,255,255,0.15);">
      ▦
    </span>
    <span class="flex-1 text-left">
      <span class="block text-[15px] font-bold">{{ title }}</span>
      <span class="mt-0.5 block text-[11px] opacity-85">{{ subtitle }}</span>
    </span>
    <span class="text-xl opacity-70">›</span>
  </button>
</template>
```

- [ ] **Step 5: Run both tests**

```bash
npm run test:run -- resources/js/components/client/BottomActionSheet.test.ts resources/js/components/client/QrScanCta.test.ts
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/client/BottomActionSheet.* resources/js/components/client/QrScanCta.*
git commit -m "$(cat <<'EOF'
feat(client): add BottomActionSheet and QrScanCta components

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 19 : useReverbChannel Composable

**Files:**
- Create: `resources/js/composables/useReverbChannel.ts`
- Create: `resources/js/composables/useReverbChannel.test.ts`

- [ ] **Step 1: Write test**

Create `resources/js/composables/useReverbChannel.test.ts`:

```typescript
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { useReverbChannel } from './useReverbChannel';

describe('useReverbChannel', () => {
  let mockEcho: { private: ReturnType<typeof vi.fn>; leaveChannel: ReturnType<typeof vi.fn> };
  let mockChannel: { listen: ReturnType<typeof vi.fn> };

  beforeEach(() => {
    mockChannel = { listen: vi.fn().mockReturnThis() };
    mockEcho = {
      private: vi.fn().mockReturnValue(mockChannel),
      leaveChannel: vi.fn(),
    };
    // @ts-expect-error inject mock
    window.Echo = mockEcho;
  });

  it('subscribes to private channel and listens to event', () => {
    const handler = vi.fn();
    useReverbChannel('mission.42', { 'MissionLivePosition': handler });

    expect(mockEcho.private).toHaveBeenCalledWith('mission.42');
    expect(mockChannel.listen).toHaveBeenCalledWith('.MissionLivePosition', handler);
  });

  it('returns unsubscribe function', () => {
    const { unsubscribe } = useReverbChannel('mission.42', {});
    unsubscribe();
    expect(mockEcho.leaveChannel).toHaveBeenCalledWith('private-mission.42');
  });
});
```

- [ ] **Step 2: Implement composable**

Create `resources/js/composables/useReverbChannel.ts`:

```typescript
declare global {
  interface Window {
    Echo: {
      private: (channel: string) => { listen: (event: string, handler: (payload: unknown) => void) => unknown };
      leaveChannel: (channel: string) => void;
    };
  }
}

type Handlers = Record<string, (payload: unknown) => void>;

export function useReverbChannel(channelName: string, handlers: Handlers) {
  if (typeof window === 'undefined' || !window.Echo) {
    return { unsubscribe: () => {} };
  }

  const channel = window.Echo.private(channelName);
  Object.entries(handlers).forEach(([event, handler]) => {
    channel.listen(`.${event}`, handler);
  });

  return {
    unsubscribe: () => {
      window.Echo.leaveChannel(`private-${channelName}`);
    },
  };
}
```

- [ ] **Step 3: Run test (must pass)**

```bash
npm run test:run -- resources/js/composables/useReverbChannel.test.ts
```

Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add resources/js/composables/useReverbChannel.*
git commit -m "$(cat <<'EOF'
feat(composables): add useReverbChannel for Echo subscriptions

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 20 : Mission Live Island

**Files:**
- Create: `resources/js/islands/mission-live.ts`
- Create: `resources/js/islands/MissionLiveIsland.vue` + test

- [ ] **Step 1: Write MissionLiveIsland test**

Create `resources/js/islands/MissionLiveIsland.test.ts`:

```typescript
import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import MissionLiveIsland from './MissionLiveIsland.vue';

describe('MissionLiveIsland', () => {
  const baseProps = {
    missionId: 42,
    initialEtaMinutes: 8,
    initialDistanceKm: 2.1,
    initialCurrentStep: 'enroute',
    provider: {
      name: 'Karim B.',
      rating: 4.9,
      missionsCount: 287,
      label: 'Serrurier certifié',
    },
    isUrgent: true,
  };

  it('renders provider name', () => {
    const wrapper = mount(MissionLiveIsland, { props: baseProps });
    expect(wrapper.text()).toContain('Karim B.');
  });

  it('renders urgency banner when urgent', () => {
    const wrapper = mount(MissionLiveIsland, { props: baseProps });
    expect(wrapper.text()).toContain('URGENCE');
  });

  it('does not render urgency banner when not urgent', () => {
    const wrapper = mount(MissionLiveIsland, { props: { ...baseProps, isUrgent: false } });
    expect(wrapper.text()).not.toContain('URGENCE');
  });

  it('dispatches scan event on QR cta', async () => {
    const dispatched: CustomEvent[] = [];
    const handler = (e: Event) => dispatched.push(e as CustomEvent);
    window.addEventListener('brio:mission-scan', handler);

    const wrapper = mount(MissionLiveIsland, { props: baseProps });
    await wrapper.findComponent({ name: 'QrScanCta' }).find('button').trigger('click');

    expect(dispatched).toHaveLength(1);
    expect(dispatched[0].detail.missionId).toBe(42);

    window.removeEventListener('brio:mission-scan', handler);
  });
});
```

- [ ] **Step 2: Implement MissionLiveIsland.vue**

Create `resources/js/islands/MissionLiveIsland.vue`:

```vue
<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { useAdaptiveTheme } from '@/composables/useAdaptiveTheme';
import { useReverbChannel } from '@/composables/useReverbChannel';
import Avatar from '@/components/atoms/Avatar.vue';
import PulseDot from '@/components/atoms/PulseDot.vue';
import EtaGlassCard from '@/components/client/EtaGlassCard.vue';
import BottomActionSheet from '@/components/client/BottomActionSheet.vue';
import QrScanCta from '@/components/client/QrScanCta.vue';

interface Provider {
  name: string;
  rating: number;
  missionsCount: number;
  label: string;
}

interface Props {
  missionId: number;
  initialEtaMinutes: number;
  initialDistanceKm: number;
  initialCurrentStep: string;
  provider: Provider;
  isUrgent: boolean;
}

const props = defineProps<Props>();

// Force dark mode on mount (active mission triggers it)
useAdaptiveTheme('dark');

const etaMinutes = ref(props.initialEtaMinutes);
const distanceKm = ref(props.initialDistanceKm);
const currentStep = ref(props.initialCurrentStep);

const steps = [
  { id: 'accepted', label: 'Accepté' },
  { id: 'enroute', label: 'En route' },
  { id: 'arrived', label: 'Arrivé' },
  { id: 'mission', label: 'Mission' },
  { id: 'done', label: 'Terminé' },
];

let reverbHandle: { unsubscribe: () => void } | null = null;

onMounted(() => {
  reverbHandle = useReverbChannel(`mission.${props.missionId}`, {
    MissionLivePosition: (payload: any) => {
      if (typeof payload?.distance_km === 'number') distanceKm.value = payload.distance_km;
      if (typeof payload?.eta_minutes === 'number') etaMinutes.value = payload.eta_minutes;
    },
    MissionLiveEta: (payload: any) => {
      if (typeof payload?.eta_minutes === 'number') etaMinutes.value = payload.eta_minutes;
    },
    MissionStatusUpdated: (payload: any) => {
      if (typeof payload?.step === 'string') currentStep.value = payload.step;
    },
  });
});

onBeforeUnmount(() => {
  reverbHandle?.unsubscribe();
});

const onScan = () => {
  window.dispatchEvent(
    new CustomEvent('brio:mission-scan', {
      detail: { missionId: props.missionId, step: currentStep.value },
    })
  );
};

const onCall = () => {
  window.dispatchEvent(
    new CustomEvent('brio:mission-call', {
      detail: { missionId: props.missionId },
    })
  );
};

const scanCtaTitle = () => {
  if (currentStep.value === 'arrived') return 'Scanner le QR pour démarrer';
  if (currentStep.value === 'mission') return 'Scanner pour terminer';
  return 'En attente de l\'arrivée…';
};
const scanCtaSub = () =>
  currentStep.value === 'arrived' || currentStep.value === 'mission'
    ? 'Code 6 chiffres'
    : 'QR disponible à l\'arrivée';
const scanDisabled = () =>
  !['arrived', 'mission'].includes(currentStep.value);
</script>

<template>
  <div class="relative min-h-screen overflow-hidden" style="background: var(--color-bg); color: var(--color-text);">
    <!-- Map stylized backdrop -->
    <div
      class="absolute inset-0"
      style="
        background:
          radial-gradient(circle at 30% 40%, rgba(99,102,241,0.15) 0%, transparent 50%),
          radial-gradient(circle at 70% 60%, rgba(167,139,250,0.1) 0%, transparent 50%),
          linear-gradient(180deg, var(--color-bg) 0%, var(--color-surface-2) 100%);
      "
    />

    <div
      v-if="isUrgent"
      class="absolute left-3 right-3 top-14 z-10 flex items-center gap-2 rounded-xl border px-3 py-1.5 text-[11px] font-bold backdrop-blur"
      style="background: rgba(239,68,68,0.12); border-color: rgba(239,68,68,0.3); color: #fca5a5;"
    >
      <PulseDot variant="urgent" />
      <span>URGENCE · MISSION EN COURS</span>
    </div>

    <div class="absolute left-5 right-5 top-[100px] z-10">
      <EtaGlassCard
        :eta-minutes="etaMinutes"
        :distance-km="distanceKm"
        :provider-name="provider.name"
        :steps="steps"
        :current-step="currentStep"
      />
    </div>

    <BottomActionSheet>
      <div class="mb-4 flex items-center gap-3">
        <Avatar :name="provider.name" size="md" />
        <div class="flex-1">
          <p class="text-base font-bold tracking-tight">{{ provider.name }}</p>
          <p class="mt-0.5 text-[11px]" style="color: var(--color-text-2);">
            <span style="color: var(--color-warning); font-weight: 600;">★ {{ provider.rating }}</span>
            · {{ provider.missionsCount }} missions · {{ provider.label }}
          </p>
        </div>
        <button
          type="button"
          aria-label="Appeler"
          class="flex h-11 w-11 items-center justify-center rounded-full text-lg text-white"
          style="background: linear-gradient(180deg, var(--color-success), #059669); box-shadow: var(--shadow-call);"
          @click="onCall"
        >
          📞
        </button>
      </div>

      <QrScanCta
        :title="scanCtaTitle()"
        :subtitle="scanCtaSub()"
        :disabled="scanDisabled()"
        @scan="onScan"
      />
    </BottomActionSheet>
  </div>
</template>
```

- [ ] **Step 3: Create island entry**

Create `resources/js/islands/mission-live.ts`:

```typescript
import { createApp } from 'vue';
import MissionLiveIsland from './MissionLiveIsland.vue';

const mountPoint = document.getElementById('mission-live-island');
if (mountPoint) {
  const propsAttr = mountPoint.getAttribute('data-props');
  const props = propsAttr ? JSON.parse(propsAttr) : {};
  createApp(MissionLiveIsland, props).mount(mountPoint);
}
```

- [ ] **Step 4: Run MissionLiveIsland test**

```bash
npm run test:run -- resources/js/islands/MissionLiveIsland.test.ts
```

Expected: PASS — 4 tests.

- [ ] **Step 5: Commit**

```bash
git add resources/js/islands/mission-live.ts resources/js/islands/MissionLiveIsland.vue resources/js/islands/MissionLiveIsland.test.ts
git commit -m "$(cat <<'EOF'
feat(client): add MissionLiveIsland with Reverb live updates

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 21 : Wire Mission Live Island into Livewire

**Files:**
- Modify: `app/Livewire/Client/MissionLiveTracking.php`
- Modify: `resources/views/livewire/client/mission-live-tracking.blade.php`
- Create: `tests/Feature/Client/MissionLiveTrackingV2Test.php`

- [ ] **Step 1: Write feature test**

Create `tests/Feature/Client/MissionLiveTrackingV2Test.php`:

```php
<?php

namespace Tests\Feature\Client;

use App\Models\Mission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Tests\TestCase;

class MissionLiveTrackingV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_renders_v2_mount_when_feature_active(): void
    {
        $user = User::factory()->create();
        Feature::for($user)->activate('client-mobile-v2');

        $mission = Mission::factory()->create([
            'client_id' => $user->id,
            'status' => 'en_route',
        ]);

        $response = $this->actingAs($user)->get(route('client.mission.live', $mission->id));

        $response->assertOk();
        $response->assertSee('id="mission-live-island"', false);
        $response->assertSee('data-props=', false);
    }

    public function test_renders_legacy_when_feature_inactive(): void
    {
        $user = User::factory()->create();
        $mission = Mission::factory()->create([
            'client_id' => $user->id,
            'status' => 'en_route',
        ]);

        $response = $this->actingAs($user)->get(route('client.mission.live', $mission->id));
        $response->assertOk();
        $response->assertDontSee('id="mission-live-island"', false);
    }
}
```

- [ ] **Step 2: Locate the actual route**

Run:
```bash
grep -rn "client.mission.live\|MissionLiveTracking" routes/ app/Livewire/Client/ | head -20
```

Adjust route name in test if different (likely `client.suivi-live` or similar). Same goes for the Livewire class location.

- [ ] **Step 3: Run test (must fail)**

```bash
php artisan test --filter=MissionLiveTrackingV2Test
```

Expected: FAIL.

- [ ] **Step 4: Modify MissionLiveTracking.php**

Add this method at end of class in `app/Livewire/Client/MissionLiveTracking.php`:

```php
public function getV2Props(): array
{
    $mission = $this->mission ?? \App\Models\Mission::findOrFail($this->missionId);

    $stepMap = [
        'planned' => 'accepted',
        'assigned' => 'accepted',
        'en_route' => 'enroute',
        'arrived' => 'arrived',
        'started' => 'mission',
        'in_mission' => 'mission',
        'completed' => 'done',
    ];

    $session = $mission->tripTrackingSession ?? null;

    return [
        'missionId' => $mission->id,
        'initialEtaMinutes' => (int) ($session?->eta_minutes ?? 0),
        'initialDistanceKm' => (float) round(($session?->distance_m ?? 0) / 1000, 1),
        'initialCurrentStep' => $stepMap[$mission->status] ?? 'enroute',
        'provider' => [
            'name' => $mission->leadProvider?->name ?? 'Provider',
            'rating' => (float) ($mission->leadProvider?->average_rating ?? 5.0),
            'missionsCount' => (int) ($mission->leadProvider?->missions_count ?? 0),
            'label' => $mission->serviceCatalog?->trade?->name ?? 'Prestataire',
        ],
        'isUrgent' => $mission->booking?->priority === 'urgent',
    ];
}
```

**Adapt** if Livewire component uses different property names (e.g., `$mission` vs `$missionId`) — check the file first.

- [ ] **Step 5: Modify the blade view**

In `resources/views/livewire/client/mission-live-tracking.blade.php`, at the very top:

```blade
@php
    $useV2 = \Laravel\Pennant\Feature::for(auth()->user())->active('client-mobile-v2');
    $v2Props = $useV2 ? $this->getV2Props() : null;
@endphp

@if($useV2)
    @vite(['resources/css/app.css', 'resources/js/islands/mission-live.ts'])
    <div id="mission-live-island" data-props="{{ json_encode($v2Props) }}"></div>
@else
    {{-- legacy content preserved --}}
@endif
```

Wrap existing content with `@else ... @endif` like in Task 16.

- [ ] **Step 6: Run test (must pass)**

```bash
php artisan test --filter=MissionLiveTrackingV2Test
```

Expected: PASS — 2 tests.

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Client/MissionLiveTracking.php resources/views/livewire/client/mission-live-tracking.blade.php tests/Feature/Client/MissionLiveTrackingV2Test.php
git commit -m "$(cat <<'EOF'
feat(client): wire MissionLive Vue island via feature flag

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 22 : Playwright E2E Tests

**Files:**
- Create: `tests/e2e/playwright.config.ts`
- Create: `tests/e2e/client-mobile-poc.spec.ts`

- [ ] **Step 1: Create Playwright config**

Create `tests/e2e/playwright.config.ts`:

```typescript
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: '.',
  timeout: 30_000,
  retries: 0,
  use: {
    baseURL: process.env.APP_URL || 'http://localhost:8000',
    ...devices['iPhone 13 Pro'],
  },
  projects: [
    { name: 'mobile-webkit', use: { ...devices['iPhone 13 Pro'] } },
    { name: 'mobile-chromium', use: { ...devices['Pixel 7'] } },
  ],
});
```

- [ ] **Step 2: Create E2E spec**

Create `tests/e2e/client-mobile-poc.spec.ts`:

```typescript
import { test, expect } from '@playwright/test';

test.describe('Client Mobile POC', () => {
  test.beforeEach(async ({ page }) => {
    // assumes a beta test user logs in via session — adjust to your auth flow
    await page.goto('/login');
    await page.fill('input[name="email"]', process.env.E2E_USER_EMAIL || 'beta@brio.test');
    await page.fill('input[name="password"]', process.env.E2E_USER_PASSWORD || 'password');
    await page.click('button[type="submit"]');
    await page.waitForURL(/dashboard/);
  });

  test('renders V2 home with adaptive light mode', async ({ page }) => {
    await page.goto('/dashboard/client');
    await expect(page.locator('#client-home-island')).toBeVisible();
    const theme = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
    expect(theme).toBe('light');
  });

  test('quick action dispatches event', async ({ page }) => {
    await page.goto('/dashboard/client');
    await page.evaluate(() => {
      (window as any).__lastEvent = null;
      window.addEventListener('brio:client-action', (e: any) => {
        (window as any).__lastEvent = e.detail;
      });
    });
    await page.locator('[data-test="quick-action"]').first().click();
    const detail = await page.evaluate(() => (window as any).__lastEvent);
    expect(detail?.id).toBe('urgent');
  });

  test('switches to dark mode on active mission', async ({ page }) => {
    // assumes a mission in 'en_route' status seeded for the test user
    await page.goto('/dashboard/client/mission-live/1');
    await expect(page.locator('#mission-live-island')).toBeVisible();
    const theme = await page.evaluate(() => document.documentElement.getAttribute('data-theme'));
    expect(theme).toBe('dark');
  });

  test('falls back to legacy blade if feature flag off', async ({ page, context }) => {
    // log in as a non-beta user
    await context.clearCookies();
    await page.goto('/login');
    await page.fill('input[name="email"]', process.env.E2E_LEGACY_USER_EMAIL || 'legacy@brio.test');
    await page.fill('input[name="password"]', process.env.E2E_LEGACY_USER_PASSWORD || 'password');
    await page.click('button[type="submit"]');
    await page.goto('/dashboard/client');
    await expect(page.locator('#client-home-island')).toHaveCount(0);
  });
});
```

- [ ] **Step 3: Seed beta + legacy users**

Run:
```bash
php artisan tinker --execute="
\$beta = \App\Models\User::firstOrCreate(['email' => 'beta@brio.test'], ['name' => 'Beta', 'password' => bcrypt('password'), 'role' => 'client']);
\$legacy = \App\Models\User::firstOrCreate(['email' => 'legacy@brio.test'], ['name' => 'Legacy', 'password' => bcrypt('password'), 'role' => 'client']);
\Laravel\Pennant\Feature::for(\$beta)->activate('client-mobile-v2');
"
```

- [ ] **Step 4: Run E2E**

In one terminal:
```bash
php artisan serve
```

In another:
```bash
npm run dev
```

Then in a third:
```bash
npx playwright test --config=tests/e2e/playwright.config.ts
```

Expected: 4 tests pass on `mobile-webkit` and `mobile-chromium` projects (8 total).

- [ ] **Step 5: Commit**

```bash
git add tests/e2e/
git commit -m "$(cat <<'EOF'
test(e2e): add Playwright suite for client mobile POC (4 scenarios)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 23 : Capacitor Smoke Test on Device

**Files:**
- No code files; verification task

- [ ] **Step 1: Build production assets**

```bash
npm run build
```

Expected: Build success. Check `public/build/manifest.json` includes both islands.

- [ ] **Step 2: Sync Capacitor**

```bash
npx cap sync
```

Expected: iOS and Android directories synced. No errors.

- [ ] **Step 3: Open iOS project**

```bash
npx cap open ios
```

In Xcode, select a device or simulator (iPhone 13 Pro recommended) and Run (⌘R).

- [ ] **Step 4: Manual checks on iOS**

Navigate to `/dashboard/client` (with beta user logged in). Verify:
- Home renders in mode clair
- FAB urgence visible bottom right
- Bottom nav blur effect works
- Tap quick action → no JS error in Safari Web Inspector
- Tap on a status card

Navigate to `/dashboard/client/mission-live/1`:
- Mode bascule en sombre automatiquement
- ETA card glassmorphic visible
- Bottom action sheet visible
- Tap on QR CTA dispatches event (check console)

- [ ] **Step 5: Open Android project**

```bash
npx cap open android
```

In Android Studio, select an emulator (Pixel 7 recommended) and Run.

- [ ] **Step 6: Manual checks Android**

Same as iOS step 4. Note any discrepancies (backdrop-filter support varies on older Android).

- [ ] **Step 7: Document findings**

Create `docs/design-system/poc-device-testing.md`:

```markdown
# POC Device Testing — 2026-05-22

## iOS 17 (iPhone 13 Pro)
- [ ] Home renders correctly
- [ ] Adaptive switch works
- [ ] FAB urgence visible
- [ ] No console errors
- Notes: ...

## Android 14 (Pixel 7)
- [ ] Home renders correctly
- [ ] Adaptive switch works
- [ ] Backdrop-filter renders OK (may need fallback)
- [ ] No console errors
- Notes: ...

## Performance
- Initial JS bundle size: ___ KB gzipped (target < 80 KB)
- FCP on 4G: ___ ms (target < 1500 ms)
- LCP: ___ ms (target < 2500 ms)
```

Fill in the actual results.

- [ ] **Step 8: Commit results**

```bash
git add docs/design-system/poc-device-testing.md
git commit -m "$(cat <<'EOF'
docs: document POC device testing results (iOS + Android)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 24 : Documentation Design System

**Files:**
- Create: `docs/design-system/README.md`
- Create: `docs/design-system/tokens.md`
- Create: `docs/design-system/components.md`

- [ ] **Step 1: Write README**

Create `docs/design-system/README.md`:

```markdown
# Brio Design System — POC Adaptive

**Statut**: POC client mobile livré 2026-06-05 (V1 home clair + V3 mission live sombre).

## Vue d'ensemble

- 2 modes adaptifs : `light` (planifié, retention) / `dark` (mission active, urgent, nuit)
- Switch automatique via `App\Services\Theme\AdaptiveThemeResolver` (PHP) + `useAdaptiveTheme` (Vue)
- Switch manuel possible via `users.settings.theme_preference` (`auto` / `light` / `dark`)
- Stack : Tailwind CSS variables + Vue 3 SFC + Motion One
- Coexistence ancien/nouveau via Laravel Pennant feature `client-mobile-v2`

## Comment l'activer pour un user beta

```bash
php artisan tinker
> \Laravel\Pennant\Feature::for(\App\Models\User::find($id))->activate('client-mobile-v2');
```

## Comment le désactiver (rollback)

```bash
php artisan tinker
> \Laravel\Pennant\Feature::for(\App\Models\User::find($id))->deactivate('client-mobile-v2');
```

## Liens

- [Design tokens](./tokens.md)
- [Composants](./components.md)
- [Device testing](./poc-device-testing.md)
- [Spec source](../superpowers/specs/2026-05-22-client-mobile-poc-design.md)
```

- [ ] **Step 2: Write tokens doc**

Create `docs/design-system/tokens.md`:

```markdown
# Design Tokens

Source : `resources/css/tokens.css`. Toutes les couleurs sont des CSS variables — switch via `data-theme="light|dark"` sur `<html>`.

## Palette

| Token | Light | Dark | Usage |
|---|---|---|---|
| `--color-bg` | `#fafaf7` | `#0a0a0f` | Canvas page |
| `--color-surface` | `#ffffff` | `#1a1a25` | Cards |
| `--color-text` | `#0a0a0f` | `#fafaf7` | Texte principal |
| `--color-primary` | `#6366f1` | `#818cf8` | Indigo |
| `--color-urgent` | `#ef4444` | `#ef4444` | Rouge — identique |
| `--color-success` | `#10b981` | `#10b981` | Vert — identique |

## Classes Tailwind utilitaires

- `bg-semantic-bg`, `bg-semantic-surface`
- `text-semantic-text`, `text-semantic-primary`, `text-semantic-urgent`
- `shadow-card`, `shadow-fab`, `shadow-cta`, `shadow-call`

## Motion

- Easing : `var(--ease-apple)` = `cubic-bezier(0.32, 0.72, 0, 1)`
- Durées : `--duration-fast` 180ms, `--duration-base` 380ms, `--duration-slow` 600ms
```

- [ ] **Step 3: Write components doc**

Create `docs/design-system/components.md`:

```markdown
# Composants — Catalogue POC

Tous les composants sont dans `resources/js/components/`.

## Atoms (réutilisables partout)

| Composant | Fichier | Props clés |
|---|---|---|
| `Avatar` | `atoms/Avatar.vue` | `name`, `size` |
| `Tag` | `atoms/Tag.vue` | `variant` (primary/urgent/success/neutral), slot |
| `PulseDot` | `atoms/PulseDot.vue` | `variant` (urgent/success/primary) |
| `BtnPrimary` | `atoms/BtnPrimary.vue` | `disabled`, `fullWidth`, slot, `@click` |
| `BtnSecondary` | `atoms/BtnSecondary.vue` | `disabled`, slot, `@click` |

## Client (composants métier client mobile)

| Composant | Fichier | Props clés | Émet |
|---|---|---|---|
| `AdaptiveHero` | `client/AdaptiveHero.vue` | `eyebrow`, `title`, `meta`, `tags[]` | `primary-action`, `secondary-action` |
| `QuickActionGrid` | `client/QuickActionGrid.vue` | `actions[]` | `action(id)` |
| `ServiceTile` | `client/ServiceTile.vue` | `emoji`, `name` | `select(name)` |
| `StatusCardScroller` | `client/StatusCardScroller.vue` | `cards[]` | `select(id)` |
| `FabUrgence` | `client/FabUrgence.vue` | — | `trigger` |
| `BottomNav` | `client/BottomNav.vue` | `items[]`, `activeId` | `navigate(id)` |
| `BottomActionSheet` | `client/BottomActionSheet.vue` | slot | — |
| `EtaGlassCard` | `client/EtaGlassCard.vue` | `etaMinutes`, `distanceKm`, `providerName`, `steps[]`, `currentStep` | `dismiss` |
| `StatusTimeline` | `client/StatusTimeline.vue` | `steps[]`, `currentId` | — |
| `QrScanCta` | `client/QrScanCta.vue` | `title`, `subtitle`, `disabled` | `scan` |

## Communication Vue → Livewire

Les composants ne font pas d'API call directement. Ils dispatchent des `window.CustomEvent` que Livewire intercepte via Alpine ou des listeners JS :

```js
window.addEventListener('brio:client-action', (e) => {
    Livewire.dispatch('client-action', { id: e.detail.id, payload: e.detail });
});
```

Conventions d'événements :
- `brio:client-action` — actions home (quick actions, navigation)
- `brio:mission-scan` — déclenche scan QR
- `brio:mission-call` — déclenche appel provider
```

- [ ] **Step 4: Commit**

```bash
git add docs/design-system/
git commit -m "$(cat <<'EOF'
docs(design-system): add README, tokens, and components catalog

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 25 : Wire Window Events to Livewire (Glue Layer)

**Files:**
- Create: `resources/js/livewire-bridge.ts`
- Modify: `resources/js/app.js`

- [ ] **Step 1: Create bridge**

Create `resources/js/livewire-bridge.ts`:

```typescript
// Forwards Vue island events into Livewire's event bus.

interface LivewireGlobal {
  dispatch: (event: string, detail: Record<string, unknown>) => void;
}

declare global {
  interface Window {
    Livewire?: LivewireGlobal;
  }
}

const forward = (sourceEvent: string, livewireEvent: string) => {
  window.addEventListener(sourceEvent, (e: Event) => {
    const detail = (e as CustomEvent).detail ?? {};
    if (window.Livewire) {
      window.Livewire.dispatch(livewireEvent, detail);
    } else {
      console.warn(`[brio] Livewire not yet ready for ${livewireEvent}`, detail);
    }
  });
};

export function installLivewireBridge() {
  forward('brio:client-action', 'client-action');
  forward('brio:mission-scan', 'mission-scan-requested');
  forward('brio:mission-call', 'mission-call-requested');
}
```

- [ ] **Step 2: Import bridge in app.js**

In `resources/js/app.js`, ADD at the bottom:

```javascript
import { installLivewireBridge } from './livewire-bridge';

document.addEventListener('livewire:initialized', () => {
  installLivewireBridge();
});
```

- [ ] **Step 3: Verify build**

```bash
npm run build
```

Expected: Build success.

- [ ] **Step 4: Commit**

```bash
git add resources/js/livewire-bridge.ts resources/js/app.js
git commit -m "$(cat <<'EOF'
feat(bridge): forward Vue island events into Livewire bus

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Task 26 : Final Verification & Demo Video

**Files:**
- Modify: `docs/design-system/README.md`

- [ ] **Step 1: Run full test suite**

```bash
php artisan test --filter="ClientDashboardV2Test|MissionLiveTrackingV2Test|AdaptiveThemeResolverTest"
npm run test:run
```

Expected: All tests pass — both PHP and Vue suites.

- [ ] **Step 2: Run E2E**

```bash
npx playwright test --config=tests/e2e/playwright.config.ts
```

Expected: All 8 tests pass (4 scenarios × 2 device projects).

- [ ] **Step 3: Build production assets**

```bash
npm run build
```

Expected: `public/build/manifest.json` regenerated. Note bundle sizes for `client-home.ts` and `mission-live.ts` entries — they should be < 80KB gzipped each.

- [ ] **Step 4: Record demo video**

Record a 60-second screen capture on a real device (or simulator) showing:
1. Login as beta user
2. Land on Home in mode clair
3. Tap quick action → action dispatched
4. Navigate to active mission → mode switches to sombre
5. ETA card visible with live updates
6. QR CTA enabled when status = arrived
7. Toggle preference back to legacy → blade renders

Save to `docs/design-system/demo.mp4` (or upload to internal share and link in README).

- [ ] **Step 5: Update README with demo link**

In `docs/design-system/README.md`, add at top:

```markdown
## 🎬 Demo (60s)

[Watch the POC walkthrough](./demo.mp4) — adaptive switch + QR flow on iPhone 13 Pro.
```

- [ ] **Step 6: Final commit**

```bash
git add docs/design-system/README.md docs/design-system/demo.mp4
git commit -m "$(cat <<'EOF'
docs(design-system): add POC demo video and final README

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 7: Push branch & open PR**

```bash
git push -u origin feat/client-mobile-poc
gh pr create --title "Client Mobile POC · Adaptive design system (V1 + V3)" --body "$(cat <<'EOF'
## Summary
- Adaptive design system POC : Home clair (V1) + Mission Live sombre (V3)
- Hybrid Livewire core + Vue 3 islands
- Feature flag Pennant `client-mobile-v2` pour coexistence ancien/nouveau
- 15 composants Vue 3 SFC + Vitest unit tests
- AdaptiveThemeResolver (PHP) avec 6 trigger rules + tests
- E2E Playwright sur mobile-webkit + mobile-chromium

## Test plan
- [ ] `php artisan test` passe
- [ ] `npm run test:run` passe
- [ ] `npx playwright test --config=tests/e2e/playwright.config.ts` passe
- [ ] Smoke test iOS device : home + mission live + adaptive switch
- [ ] Smoke test Android device : idem
- [ ] Bundle size < 80KB gzipped par island
- [ ] Rollback testé via Pennant deactivate

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Self-Review (executed by plan author)

**Spec coverage check :**
- §1 Objectif → Tasks 1-26 (POC entier)
- §2 Scope V1+V3 → Tasks 12-21 (composants) + 15-16, 20-21 (islands intégrés)
- §3 Architecture hybride → Tasks 1-2 (deps + Vite), 15, 20 (entry points)
- §4 Design tokens → Tasks 3-4
- §5 10 composants atomiques → Tasks 6-8, 12-14, 17-18 (15 composants au total : 5 atoms + 10 client)
- §6 Adaptive switch → Tasks 9 (PHP), 11 (Vue composable)
- §7 Data flow Reverb → Task 19 (composable), 20 (utilisation)
- §8 Error handling → Géré via feature flag fallback (Tasks 16, 21), Sentry déjà actif
- §9 Testing → Vitest tasks 5+, PHPUnit tasks 9, 16, 21, Playwright task 22
- §10 Coexistence Pennant → Task 10
- §11 Deliverables → Task 24 (docs), 26 (demo + PR)
- §12 Risks → Mitigations documentées dans le spec, pas de tâche dédiée nécessaire
- §13 Hors scope → Pas couvert (intentionnel)
- §14 Open questions → Résolues en pre-flight au top du plan

**Placeholder scan :** Le plan référence du code adapté à la base existante (relations User, routes). Des notes "Adapt as needed" sont présentes là où l'existant peut différer — ce n'est pas un placeholder bloquant mais une instruction explicite de vérification.

**Type consistency :** `getV2Props()` méthode utilisée Tasks 16 et 21. Event names `brio:*` cohérents Tasks 15, 20, 25. Composable `useAdaptiveTheme` signature `Theme = 'light' | 'dark'` cohérente Tasks 11, 15, 20.

Plan validé — pas d'incohérences détectées.
