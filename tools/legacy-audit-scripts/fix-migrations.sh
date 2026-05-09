#!/bin/bash
# ============================================================
#  CleanUx — fix-migrations.sh
#  Archiver toutes les migrations conflictuelles avec les
#  nouvelles migrations 2026_05_04_*
#
#  Usage : bash fix-migrations.sh
#  Doit être lancé depuis la RACINE du projet Laravel
# ============================================================

set -e

MIG_DIR="database/migrations"
ARCHIVE_DIR="database/migrations/archive"

echo ""
echo "╔══════════════════════════════════════════════════════╗"
echo "║  CleanUx — Nettoyage des migrations conflictuelles   ║"
echo "╚══════════════════════════════════════════════════════╝"
echo ""

# ─── Vérifications ────────────────────────────────────────
if [ ! -d "$MIG_DIR" ]; then
  echo "❌  Lancez ce script depuis la racine du projet Laravel."
  exit 1
fi

if [ ! -f "$MIG_DIR/2026_05_04_000001_create_identity_tables.php" ]; then
  echo "❌  Les nouvelles migrations 2026_05_04_* sont introuvables dans $MIG_DIR"
  exit 1
fi

# ─── Créer le dossier d'archive ───────────────────────────
mkdir -p "$ARCHIVE_DIR"
echo "📦  Dossier d'archive : $ARCHIVE_DIR"
echo ""

# ─── Liste des migrations à archiver ─────────────────────
# Tout ce qui est REMPLACÉ par les nouvelles 2026_05_04_*
TO_ARCHIVE=(
  # ─ Identité / Auth (→ 2026_05_04_000001)
  "2013_04_29_222238_create_companies_table.php"
  "2014_10_12_000000_create_users_table.php"
  "2014_10_12_100000_create_password_reset_tokens_table.php"
  "2014_10_12_200000_add_two_factor_columns_to_users_table.php"
  "2019_08_19_000000_create_failed_jobs_table.php"
  "2019_12_14_000001_create_personal_access_tokens_table.php"
  "2025_06_27_215628_create_sessions_table.php"
  "2026_03_27_020318_create_notifications_table.php"
  "2026_03_31_130000_create_jobs_table.php"

  # ─ Cashier / Stripe (columns remplacées par customer_profiles / provider_profiles)
  "2019_05_03_000001_create_customer_columns.php"
  "2019_05_03_000002_create_subscriptions_table.php"
  "2019_05_03_000003_create_subscription_items_table.php"
  "2025_06_06_000004_add_meter_id_to_subscription_items_table.php"
  "2025_06_06_000005_add_meter_event_name_to_subscription_items_table.php"

  # ─ Teams Jetstream (→ 2026_05_04_000002)
  "2020_05_21_100000_create_teams_table.php"
  "2020_05_21_200000_create_team_user_table.php"
  "2020_05_21_300000_create_team_invitations_table.php"

  # ─ RDV / Bookings (→ 2026_05_04_000006)
  "2025_06_25_235226_create_rendez_vous_table.php"
  "2026_04_06_020000_add_terrain_workflow_fields_to_rendez_vous_table.php"
  "2026_04_07_130000_add_recurring_series_fields_to_rendez_vous_table.php"
  "2026_04_12_000001_backfill_service_identifier_on_rendez_vous_snapshots.php"
  "2026_04_12_000002_drop_service_type_from_rendez_vous_table.php"
  "2026_04_26_220316_add_subscription_to_rendez_vous.php"
  "2026_04_27_000247_create_enterprise_booking_approvals_table.php"
  "2026_04_29_002911_add_asap_fields_to_rendez_vous_table.php"
  "2026_04_29_013919_add_payment_intent_fields_to_rendez_vous_table.php"
  "2026_04_29_205845_add_google_location_fields_to_rendez_vous_table.php"
  "2026_04_29_214943_add_stripe_connect_payment_fields_to_rendez_vous_table.php"

  # ─ Disponibilités (→ 2026_05_04_000005 provider_availabilities)
  "2025_06_27_234710_create_disponibilites_table.php"

  # ─ Feedback (→ 2026_05_04_000008)
  "2025_07_08_095637_create_feedback_table.php"

  # ─ Activity logs (→ 2026_05_04_000010)
  "2026_03_31_010548_create_activity_logs_table.php"
  "2026_04_07_001000_enrich_activity_logs_for_security_audit.php"

  # ─ Users columns (→ nouveau users table)
  "2026_04_04_000050_add_platform_columns_to_users_and_rendez_vous_tables.php"
  "2026_04_05_231000_add_security_scope_columns_to_users_table.php"
  "2026_04_29_205937_add_current_location_fields_to_users_table.php"
  "2026_04_29_214856_add_stripe_connect_fields_to_users_table.php"

  # ─ Géographie / Zones (→ 2026_05_04_000004)
  "2026_04_04_000010_create_geography_tables.php"
  "2026_04_04_000020_create_service_zone_tables.php"
  "2026_04_23_150000_create_country_market_foundations_tables.php"

  # ─ Organisation (→ 2026_05_04_000003)
  "2026_04_04_000030_create_organization_tables.php"

  # ─ Préférences client/employé (concept remplacé)
  "2026_04_02_000001_create_client_employee_preferences_table.php"

  # ─ Finance (→ 2026_05_04_000009)
  "2026_04_06_040000_create_finance_documents_tables.php"
  "2026_04_26_104750_create_customer_credits_table.php"
  "2026_04_27_003924_add_b2b_batch_fields_to_finance_invoices_table.php"

  # ─ Missions (→ 2026_05_04_000007)
  "2026_04_13_000001_create_missions_table.php"
  "2026_04_13_000002_create_mission_assignments_table.php"
  "2026_04_13_000003_create_mission_verification_codes_table.php"
  "2026_04_13_000004_create_mission_tracking_sessions_table.php"
  "2026_04_13_000005_create_mission_tracking_points_table.php"
  "2026_04_13_000006_add_destination_coordinates_to_missions_table.php"
  "2026_04_13_000008_create_mission_client_actions_table.php"
  "2026_04_13_000009_create_mission_checklists_table.php"
  "2026_04_13_000010_create_mission_checklist_items_table.php"
  "2026_04_13_000011_create_mission_media_table.php"
  "2026_04_13_000012_add_quality_fields_to_missions_table.php"
  "2026_04_13_000013_create_mission_incidents_table.php"
  "2026_04_13_000014_create_mission_quality_reviews_table.php"
  "2026_04_13_000015_create_mission_reports_table.php"
  "2026_04_13_000016_create_mission_events_table.php"
  "2026_04_29_123944_add_profit_fields_to_missions_table.php"

  # ─ Équipes / Partenaires (→ 2026_05_04_000005)
  "2026_04_23_090000_create_field_team_and_partner_foundations_tables.php"

  # ─ B2B lourd (→ 2026_05_04_000003 + 000006)
  "2026_04_23_120000_create_b2b_heavy_foundations_tables.php"

  # ─ Conversations (→ 2026_05_04_000008 channels/messages)
  "2026_04_29_015734_create_conversations_table.php"
  "2026_04_29_015735_create_conversation_messages_table.php"
)

# ─── Archiver ─────────────────────────────────────────────
ARCHIVED=0
SKIPPED=0

for FILE in "${TO_ARCHIVE[@]}"; do
  SRC="$MIG_DIR/$FILE"
  if [ -f "$SRC" ]; then
    mv "$SRC" "$ARCHIVE_DIR/$FILE"
    echo "  ✅  Archivé  : $FILE"
    ((ARCHIVED++))
  else
    echo "  ⏭️   Ignoré   : $FILE (introuvable, déjà supprimé ?)"
    ((SKIPPED++))
  fi
done

echo ""
echo "────────────────────────────────────────────────────────"
echo "  Archivés  : $ARCHIVED"
echo "  Ignorés   : $SKIPPED"
echo ""
echo "📋  Migrations restantes dans $MIG_DIR :"
ls "$MIG_DIR"/*.php 2>/dev/null | xargs -I{} basename {} | sort
echo ""
echo "✅  Nettoyage terminé. Lancez maintenant :"
echo "    php artisan migrate:fresh"
echo ""
echo "⚠️   Si migrate:fresh échoue encore, inspectez les"
echo "    migrations restantes et ajoutez-les à TO_ARCHIVE."
echo "    Les fichiers archivés sont dans : $ARCHIVE_DIR/"
