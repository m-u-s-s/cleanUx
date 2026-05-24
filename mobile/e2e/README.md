# CleanUx E2E Tests (Maestro)

## Prerequisites

Install Maestro CLI:
```bash
curl -Ls "https://get.maestro.mobile.dev" | bash
```

## Running tests

### Client app
```bash
# Start the client app first
cd mobile/client && npx expo start

# In another terminal, run E2E tests
maestro test mobile/e2e/flows/client/
```

### Provider app
```bash
cd mobile/provider && npx expo start
maestro test mobile/e2e/flows/provider/
```

### Single flow
```bash
maestro test mobile/e2e/flows/client/02-booking-flow.yaml
```

## Test data requirements

Tests expect:
- A test user `test@cleanux.dev` / `password123` (client role)
- A test user `provider@cleanux.dev` / `password123` (provider role)
- At least one service in the catalog (e.g. "Nettoyage")
- Laravel backend running at the URL configured in `.env`

Create test users via:
```bash
php artisan tinker
> User::factory()->create(['email' => 'test@cleanux.dev', 'password' => bcrypt('password123'), 'role' => 'client']);
> User::factory()->create(['email' => 'provider@cleanux.dev', 'password' => bcrypt('password123'), 'role' => 'provider']);
```

## Flow naming convention

`NN-description.yaml` — numbered for execution order. Login flows run first.

## Notes

- Flows use text matching (not testIDs) for simplicity. If the UI text changes, update the flows.
- The booking flow (02) depends on login (01) having run first to establish a session.
- Maestro supports running on iOS Simulator and Android Emulator.
- For CI, use `maestro cloud` (paid) or self-hosted runner.
