# API Versioning — CleanUx

## Current state

CleanUx exposes two API generations:

| Generation | URL prefix | Status |
|-----------|-----------|--------|
| v1 (implicit) | `/api/*` (no version prefix) | Active — stable |
| v2 | `/api/v2/*` | Active — new modules only |

All routes live under `routes/api/` and are loaded by `routes/api.php`.

**v1 routes** (`/api/auth/*`, `/api/client/*`, `/api/provider/*`, `/api/admin/*`, `/api/public/*`) were written first and carry no version prefix. They are stable and receive only backward-compatible changes.

**v2 routes** (`/api/v2/*`) are used for new modules introduced since May 2026 (onboarding, contracts, chat, subscriptions, KYB, etc.) and are defined in `routes/api/v2-shared.php` and per-module controllers.

---

## Versioning policy

### When to create a new version

A new API version (`v3`, etc.) is required when **any** of the following apply:

- A response field is renamed or removed
- A query parameter is renamed or its semantics change
- An endpoint URL changes
- An HTTP method changes for an existing action
- Authentication scheme changes for an existing endpoint
- An enum value is removed

Adding new optional fields, new endpoints, or new enum values is **backward-compatible** and does NOT require a new version.

### Deprecation process

1. The endpoint or field is marked deprecated in the API reference documentation.
2. A `Deprecation` response header is added:
   ```
   Deprecation: true
   Sunset: Sat, 01 Nov 2026 00:00:00 GMT
   Link: <https://docs.cleanux.com/api/migration>; rel="deprecation"
   ```
3. A **minimum 6-month notice** is given before removal.
4. After sunset, the endpoint returns `410 Gone` with a machine-readable body:
   ```json
   {"error": "endpoint_sunset", "message": "This endpoint was removed on 2026-11-01. See https://docs.cleanux.com/api/migration"}
   ```

### Breaking change workflow

```
1. Open GitHub issue tagged [breaking-change] with migration guide
2. Implement new v(N+1) endpoint in parallel with old v(N)
3. Add Deprecation + Sunset headers to old endpoint
4. Communicate via email / changelog to all API key holders
5. Remove old endpoint after sunset date
```

---

## Adding a new v2 endpoint

1. Create controller in `app/Http/Controllers/Api/`.
2. Register route in `routes/api/v2-shared.php` (shared) or a new `routes/api/v2-<domain>.php` loaded from `routes/api.php`.
3. Secure with `auth:sanctum` middleware and the appropriate API scope middleware:
   ```php
   Route::middleware(['auth:sanctum', 'api_scope:bookings.read'])->get(...);
   ```
4. Document in `docs/API_REFERENCE.md`.

---

## Response envelope

All v2 endpoints return a consistent JSON envelope:

```json
{
  "data": { ... },
  "meta": { "request_id": "uuid", "version": "2" }
}
```

Error responses:
```json
{
  "error": "validation_failed",
  "message": "Human-readable description",
  "errors": { "field": ["rule message"] }
}
```

HTTP status codes follow RFC 9110 strictly: `200 OK`, `201 Created`, `204 No Content`, `400 Bad Request`, `401 Unauthorized`, `403 Forbidden`, `404 Not Found`, `409 Conflict`, `422 Unprocessable Entity`, `429 Too Many Requests`, `500 Internal Server Error`.

---

## Mobile clients (React Native / Expo)

Mobile apps (`/mobile`) must use versioned endpoints only (`/api/v2/*`) for any feature built after Sprint 1. This ensures the mobile app can be updated independently of the web backend, and that a user on an older app version still receives valid responses during a phased rollout.

---

## Changelog

| Date | Change |
|------|--------|
| 2026-05 | v2 prefix introduced for new modules (onboarding, contracts, chat, etc.) |
| 2026-05 | API token scopes and throttle middleware added to all v2 routes |
