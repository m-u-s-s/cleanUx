# Brio API Reference

Base URL: `https://app.brio.com/api`

## Authentication
All endpoints require `Authorization: Bearer <token>` unless marked PUBLIC.

### POST /auth/login (PUBLIC)
Body: `{email, password, device_name?}`
Response: `{ok, token, user}`

### POST /auth/register (PUBLIC)
Body: `{name, email, password, password_confirmation, phone?, locale?, accept_terms, device_name?}`

### POST /auth/refresh
Response: `{token, expires_at}`

### GET /auth/me
Response: `{user}`

### POST /auth/forgot-password (PUBLIC)
Body: `{email}`

### POST /auth/logout

## Client Endpoints

### GET /client/bookings
### POST /client/bookings
### GET /client/bookings/{id}
### GET /client/bookings/{id}/commission
### POST /client/bookings/{id}/payment-intent
### POST /client/bookings/{id}/tip
### GET /client/bookings/{id}/tracking
### POST /client/bookings/{id}/rating

### GET /client/payment-methods
### POST /client/payment-methods/setup-intent
### DELETE /client/payment-methods/{id}

### PUT /client/profile
### POST /client/profile/avatar
### POST /client/nps
### GET /client/loyalty/me
### GET /client/loyalty/rewards

### GET /client/disputes
### POST /client/gdpr/requests
### POST /client/devices/register

## Provider Endpoints

### GET /provider/assignments/inbox
### POST /provider/assignments/{id}/accept
### POST /provider/assignments/{id}/decline
### POST /provider/missions/{id}/start|arrive|complete
### POST /provider/missions/{id}/live/position
### POST /provider/missions/{id}/live/eta
### GET /provider/wallet/balance
### GET /provider/wallet/transactions
### POST /provider/wallet/withdraw
### GET /provider/stripe-connect/status
### POST /provider/stripe-connect/onboard
### POST /provider/presence-v2/heartbeat
### GET /provider/availability
### GET /provider/badges
### GET /provider/kyc/status
### GET /provider/disputes
### GET /provider/ratings/me

## Public Endpoints

### GET /search/services
### GET /search/providers
### GET /search/postal-autocomplete
### GET /health
### GET /support/faq

## WebSocket

### GET /realtime/socket-config
### POST /broadcasting/auth

## Error Format
All errors: `{ok: false, error_code, message, errors?}`
