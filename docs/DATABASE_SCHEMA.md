# Database Schema Overview

127 total migrations as of 2026-05-25.

---

## Core Tables (~15)

| Table | Purpose |
|---|---|
| `users` | All roles: client, provider, admin, employe |
| `bookings` / `rendez_vous` | Service reservations |
| `missions` | Assigned work units (1 booking → 1+ missions) |
| `mission_assignments` | Provider ↔ mission matching (dispatch) |
| `provider_profiles` | Provider-specific data: commission_rate, rating_avg, etc. |
| `organizations` | B2B companies |
| `organization_sites` | Multi-site locations |
| `parametres` | Platform-wide config key-value store |
| `trades` | Service types (cleaning, painting, babysitting, etc.) |
| `zones` | Geographic coverage zones |
| `service_categories` | Trade taxonomy |
| `disponibilites` | Legacy V1 availability slots |
| `notifications` | Laravel notification ledger |
| `activity_log` | Polymorphic activity stream (spatie/laravel-activitylog) |
| `media` | Polymorphic file attachments (spatie/laravel-medialibrary) |

---

## Payment (~8)

| Column / Table | Purpose |
|---|---|
| `bookings.stripe_payment_intent_id` | PI reference (pre-auth capture flow) |
| `bookings.payment_status` | `authorized → captured → refunded` |
| `bookings.payout_status` | `null → processed → transferred` |
| `provider_payouts` | Ledger of provider earnings |
| `provider_wallet_transactions` | Wallet credits / debits |
| `booking_tips` | Client-to-provider tips |
| `customer_credits` | **See Known Issues** — do NOT write via `CustomerCredit::create` |
| `subscription_invoices` | Subscription cycle invoices (V2) |

---

## Modules V2 (~40 tables)

### Promotions & Loyalty
| Table | Module |
|---|---|
| `promo_codes`, `promo_code_usages` | Promotions & Parrainage |
| `referral_invitations` | Parrainage |
| `loyalty_accounts`, `loyalty_transactions` | Loyalty (4 tiers) |
| `loyalty_rewards`, `loyalty_redemptions` | Loyalty Redemption Marketplace |

### Reviews & Ratings
| Table | Module |
|---|---|
| `ratings`, `rating_dimensions` | Ratings V2 (blind reveal) |

### Dispatch & Search
| Table | Module |
|---|---|
| `matching_scores`, `matching_audit_logs` | Matching V2 |
| `browse_provider_results` | Search V2 |

### Communication
| Table | Module |
|---|---|
| `chat_threads`, `chat_participants`, `chat_messages`, `chat_message_reads` | Chat V2 |
| `sms_messages` | SMS / WhatsApp V2 |
| `device_tokens`, `push_notifications` | Push V2 |
| `broadcast_events` | Realtime V2 |
| `notification_preferences` | Notification Preferences Center |

### Finance & Payments
| Table | Module |
|---|---|
| `exchange_rates`, `currency_conversions` | FX V2 |
| `accounting_entries`, `accounting_periods`, `accounting_batches` | Accounting V2 |
| `subscriptions_v2`, `subscription_plans_v2`, `subscription_cycles`, `subscription_invoices` | Subscriptions V2 |
| `insurance_plans`, `insurance_policies`, `insurance_claims` | Insurance V2 |

### Compliance & Legal
| Table | Module |
|---|---|
| `kyc_verifications`, `kyc_documents` | KYC |
| `kyb_entities`, `kyb_documents`, `kyb_verifications`, `kyb_sanctions_checks`, `kyb_beneficial_owners` | KYB B2B V2 |
| `gdpr_export_requests`, `gdpr_deletion_requests` | GDPR |
| `contracts`, `contract_templates`, `contract_signatures` | Contracts & E-signatures V2 |
| `api_token_scopes`, `api_token_usages` | API Tokens V2 (extends Sanctum personal_access_tokens) |

### Operations
| Table | Module |
|---|---|
| `quality_inspections`, `quality_checklist_items`, `inspection_results` | Quality V2 |
| `disputes`, `dispute_messages`, `dispute_timeline_events` | Disputes & SAV |
| `availability_slots`, `availability_exceptions`, `availability_holds` | Availability V2 |
| `trip_tracking_sessions`, `trip_tracking_points` | Trip Tracking V2 |
| `provider_presence` | Presence V2 |
| `fleet_vehicles`, `fleet_equipment`, `fleet_assignments`, `fleet_maintenance_logs`, `fleet_certifications` | Fleet V2 |
| `provider_badges`, `provider_badge_awards` | Provider Badges |
| `booking_favorites` | Booking Favorites |

### Platform
| Table | Module |
|---|---|
| `tenants`, `tenant_domains`, `tenant_users` | Multi-tenancy V2 |
| `webhook_endpoints`, `webhook_subscriptions`, `webhook_events`, `webhook_deliveries` | Webhooks B2B V2 |
| `audit_events` | Audit Log V2 |
| `analytics_events`, `analytics_sessions` | Analytics V2 |
| `marketing_segments`, `marketing_campaigns`, `marketing_campaign_sends` | Marketing Automation V2 |
| `risk_events`, `risk_holds` | Fraud / Risk V2 |
| `nps_responses` | NPS |
| `utm_sessions` | UTM capture |

### Catalog & Pricing
| Table | Module |
|---|---|
| `service_catalog_v2`, `service_catalog_versions` | Service Catalog V2 |
| `pricing_rules`, `price_quotes` | Pricing V2 |

---

## Migration History

- **127 total migrations**
- **~21 fix / compat migrations** (all from the May 2026 sprint, listed below)
- Schema is normalised 3NF with soft deletes (`deleted_at`) on most models
- Polymorphic relations used for: `activity_log`, `notifications`, `media`
- V1 and V2 columns coexist on some tables (e.g. `devis_estime` + `estimated_price` on `bookings`)

### Fix / Compat Migrations (May 2026)

| Migration file | Reason |
|---|---|
| `2026_05_11_223000_fix_remaining_test_compatibility_schema` | Test suite column gaps |
| `2026_05_11_224500_fix_portal_and_booking_legacy_columns` | Portal legacy columns |
| `2026_05_11_225500_fix_disponibilites_and_recurring_compat` | Recurring booking compat |
| `2026_05_11_230500_fix_booking_contact_columns` | Missing contact columns |
| `2026_05_11_231500_fix_portal_legacy_columns_round2` | Round 2 portal fix |
| `2026_05_11_232500_fix_portal_dashboard_remaining_compat` | Dashboard compat |
| `2026_05_13_194311_fix_cleanux_test_schema_compatibility_final` | Final test compat |
| `2026_05_13_211602_fix_enterprise_feedback_recurring_schema_compat` | Enterprise feedback |
| `2026_05_13_214504_fix_structural_blockers_round2` | Structural blockers |
| `2026_05_13_221046_create_country_billing_profiles_compat_table` | Billing profiles compat |
| `2026_05_13_221122_create_mission_team_assignments_compat_table` | Team assignments compat |
| `2026_05_13_222610_fix_b2b_approval_and_country_schema_round3` | B2B approval round 3 |
| `2026_05_13_225926_fix_approval_booking_runtime_schema_round4` | Runtime schema round 4 |
| `2026_05_13_230245_create_conversations_compat_table_round5` | Conversations compat |
| `2026_05_13_232801_fix_runtime_schema_round6` | Runtime schema round 6 |
| `2026_05_13_235712_fix_remaining_runtime_schema_compat_round5` | Remaining runtime compat |
| `2026_05_14_002124_create_google_calendar_event_links_compat_table_round7` | Calendar links compat |
| `2026_05_14_235147_fix_mission_verification_codes_runtime_columns` | Verification codes |
| `2026_05_15_223530_fix_admin_advanced_centers_schema_compat_round` | Admin centers |
| `2026_05_16_220000_fix_remaining_test_schema_compat_round_final` | Final compat round |
| `2026_05_17_120006_fix_bookings_surface_to_string_type` | surface column type |

---

## Known Issues

### `customer_credits` — model/table mismatch
The `CustomerCredit` model and `customer_credits` table have column mismatches introduced during the May 2026 sprint. **Do NOT call `CustomerCredit::create()`** until a reconciliation migration is written.

### `BelongsToTenant` trait — unused
The trait exists at `App\Traits\BelongsToTenant` and provides a global scope + `creating` hook. Zero models currently use it. Multi-tenancy (`TenantContext`) is wired but not activated at the model layer.

### V1 / V2 column coexistence
Several core tables carry both old V1 columns and new V2 equivalents:
- `bookings`: `devis_estime` (V1) and `estimated_price` (V2)
- `bookings`: `statut` (V1) and `status` (V2)
Always prefer the V2 column in new code.
