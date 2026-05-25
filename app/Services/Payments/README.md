# Payments Domain

Handles all money flow: client → platform → provider.

- **CommissionService** — calculates platform fee + provider payout per booking
- **StripeConnectService** — Express account management for providers
- **StripeCountryMapper** — per-country Stripe capabilities (iDEAL NL, SEPA DE)
- **MissionPaymentService** — pre-auth → capture flow for bookings
