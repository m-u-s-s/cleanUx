# CleanUx Mobile RN — Sprint 3 : Design System Library

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Construire `mobile/client/src/ui/` — la bibliothèque de composants RN réutilisables qui consomment les tokens de Sprint 1 et servent de building blocks pour les écrans des Sprints 4-11. 13 composants + animation helpers.

**Architecture:** Chaque composant = 1 fichier sous `src/ui/`, props typées, StyleSheet avec tokens du theme, testable en snapshot. Pas de logique métier dans les composants UI — ils sont purs visuels.

**Tech Stack:** React Native, Reanimated 3, @gorhom/bottom-sheet, react-native-svg (pour icons custom), expo-blur (EtaGlassCard), theme tokens (Sprint 1).

---

## Components to build

| # | Component | File | Props | Used by |
|---|---|---|---|---|
| 1 | Button | `ui/Button.tsx` | variant, size, onPress, disabled, loading | Everywhere |
| 2 | Badge | `ui/Badge.tsx` | variant, label | Lists, cards |
| 3 | Icon | `ui/Icon.tsx` | name, size, color | Everywhere |
| 4 | Avatar | `ui/Avatar.tsx` | name, imageUri?, size | Chat, profile |
| 5 | Tag | `ui/Tag.tsx` | variant, label | Status indicators |
| 6 | StatCard | `ui/StatCard.tsx` | title, value, trend?, tone | Dashboards |
| 7 | KPICard | `ui/KPICard.tsx` | title, value, hint?, icon? | Dashboards |
| 8 | PulseDot | `ui/PulseDot.tsx` | variant, size | Live indicators |
| 9 | Skeleton | `ui/Skeleton.tsx` | width, height, radius | Loading states |
| 10 | Screen | `ui/Screen.tsx` | scroll?, edges? | All screens |
| 11 | BottomSheet | `ui/BottomSheet.tsx` | snapPoints, children | Modals |
| 12 | Divider | `ui/Divider.tsx` | label? | Lists, forms |
| 13 | TextInput | `ui/TextInput.tsx` | label, error?, icon? | Forms |

Plus `ui/animations.ts` — Reanimated helpers (fadeUp, shimmer timing).

---

## Execution: 3 batches

### Batch 1 — Install deps + Core atoms (Button, Badge, Icon, Avatar, Tag, Divider, TextInput)
### Batch 2 — Data display + feedback (StatCard, KPICard, PulseDot, Skeleton, Screen)
### Batch 3 — Layout patterns + animations (BottomSheet, animations.ts, index.ts barrel)
