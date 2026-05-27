# Contributing to CleanUx

## Code conventions

### PHP
- PSR-12 standard enforced by [Laravel Pint](https://laravel.com/docs/pint).
- Run before every commit: `./vendor/bin/pint`
- Functions must stay under 20 lines. Extract helpers ruthlessly.
- All public method signatures must be typed (parameters + return type).
- Use `readonly` properties and named constructors where appropriate.

### JavaScript / TypeScript
- ESLint + Prettier configured in `.eslintrc` and `.prettierrc`.
- Run: `npm run lint` and `npm run format` before committing.
- Mobile (React Native): TypeScript strict mode. No `any`.

### Blade / Livewire
- One Livewire component = one responsibility (SRP).
- Components must not exceed ~150 lines of PHP. Extract `Concerns/` traits.
- Views use existing design tokens (`cu-btn-primary`, `cu-chip`, etc.) — do not inline custom Tailwind unless adding to the design system.

---

## Branch naming

| Type | Pattern | Example |
|------|---------|---------|
| New feature | `feature/<short-description>` | `feature/provider-certifications` |
| Bug fix | `fix/<short-description>` | `fix/booking-qr-race-condition` |
| Refactor | `refactor/<short-description>` | `refactor/split-analytics-kpi-service` |
| Documentation | `docs/<short-description>` | `docs/api-versioning` |
| DevOps / infra | `ops/<short-description>` | `ops/staging-workflow` |
| Hotfix on main | `hotfix/<short-description>` | `hotfix/stripe-webhook-500` |

Branches are cut from `develop` (features) or `main` (hotfixes only).

---

## PR process

1. **CI must pass.** All GitHub Actions checks (lint, tests, security scan) must be green before review.
2. **One reviewer required.** Tag the relevant domain owner (see `CODEOWNERS`).
3. **Self-review checklist** (mark in PR description):
   - [ ] PSR-12 / Pint clean
   - [ ] Tests added or updated
   - [ ] Migrations are reversible (`down()` implemented)
   - [ ] No raw `DB::statement` without a comment explaining why
   - [ ] `@deprecated` added if removing a feature flag fallback
   - [ ] Secrets/keys not committed
4. Squash-merge into `develop`; release manager cherry-picks to `main`.

---

## Test patterns

### Setup
```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class MyTest extends TestCase
{
    use RefreshDatabase;
}
```

### Factories
```php
// Prefer factories over raw DB inserts
$user = User::factory()->withProviderProfile()->create();
$booking = Booking::factory()->for($user, 'client')->completed()->create();
```

### Naming convention
```
it_creates_a_booking_when_provider_is_available()
it_throws_when_service_catalog_has_no_trade()
it_returns_422_if_required_field_is_missing()
```

Use `it_` prefix for feature/behaviour tests. Use `test_` prefix only for unit tests on pure functions.

### Assertions
- Assert on domain state, not implementation details.
- For Livewire: use `Livewire::test(MyComponent::class)->call('action', $args)->assertSee(...)`.
- For Jobs/Events: use `Queue::fake()` / `Event::fake()` — never run real external calls in tests.
- Use `Http::fake()` to mock Stripe, Twilio, Google Maps responses.

---

## Commit message style (Conventional Commits)

```
<type>(<scope>): <short summary in imperative mood>

[optional body]

[optional footer: Co-Authored-By, Closes #issue]
```

Types: `feat`, `fix`, `refactor`, `test`, `docs`, `ops`, `chore`, `perf`

Examples:
```
feat(certifications): add provider_trade_certifications table and model
fix(pricing): resolve zone pricing override not applied when base_price is 0
refactor(matching): extract scoring criteria into dedicated value objects
test(cancellation): add edge cases for SLA window calculation
docs(api): document v2 versioning and deprecation policy
```

**Breaking changes** must include `BREAKING CHANGE:` in the footer:
```
feat(api)!: rename /api/bookings to /api/v1/bookings

BREAKING CHANGE: all API clients must update their base URL.
```
