# KYC Domain

Identity verification for providers.

- **KycProviderInterface** — contract for verification providers
- **OnfidoKycProvider** — Onfido API integration (mock fallback)
- **KycVerificationService** — orchestration: create applicant → check → webhook → status
