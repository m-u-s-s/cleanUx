# CleanUx Design System — POC Adaptive

**Statut**: POC client mobile (V1 home clair + V3 mission live sombre).

## Vue d'ensemble

- 2 modes adaptifs : `light` (planifié, retention) / `dark` (mission active, urgent, nuit)
- Switch automatique via `App\Services\Theme\AdaptiveThemeResolver` (PHP) + `useAdaptiveTheme` (Vue)
- Switch manuel via `users.settings.theme_preference` (`auto` / `light` / `dark`)
- Stack : Tailwind CSS variables + Vue 3 SFC + Motion One
- Coexistence ancien/nouveau via Laravel Pennant feature `client-mobile-v2`

## Activer pour un user beta

```bash
php artisan tinker
> \Laravel\Pennant\Feature::for(\App\Models\User::find($id))->activate('client-mobile-v2');
```

## Désactiver (rollback)

```bash
php artisan tinker
> \Laravel\Pennant\Feature::for(\App\Models\User::find($id))->deactivate('client-mobile-v2');
```

## Liens

- [Design tokens](./tokens.md)
- [Composants](./components.md)
- [Device testing](./poc-device-testing.md)
- [Spec source](../superpowers/specs/2026-05-22-client-mobile-poc-design.md)
- [E2E tests](../../tests/e2e/README.md)
