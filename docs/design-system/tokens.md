# Design Tokens

Source : `resources/css/tokens.css`. Switch via `data-theme="light|dark"` sur `<html>`.

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
