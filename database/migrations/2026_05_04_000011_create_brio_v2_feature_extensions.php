<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->corpsInitial();
        $this->fusion20260528100004AddActorUserIdToMissionEventsTable();
        $this->fusion20260528100015AddMissingColumnsToCustomerClaimsTable();
        $this->fusion20260528100020AddMissingColumnsToGoogleCalendarConnectionsTa();
        $this->fusion20260528100030AddMissingColumnsToMissionIncidentsTable();
        $this->fusion20260528100032AddMissingColumnsToMissionQualityReviewsTable();
        $this->fusion20260528100037AddMissingColumnsToOrganizationContractsTable();
        $this->fusionFixPortalLegacyColumnsRound2ProviderFavorites();
        $this->fusionFixBrioTestSchemaCompatibilityFinalLocationGeocodes();
        $this->fusionFixRemainingRuntimeSchemaCompatRound5MissionQualityRevie();
        $this->fusionFixRemainingRuntimeSchemaCompatRound5GoogleCalendarConne();
        $this->fusionFixRemainingRuntimeSchemaCompatRound5LocationGeocodes();
        $this->fusionAddProviderOrgAndContractLinksForSp4OrganizationContract();
        $this->fusionSocleDuKitSurPlaceMissionIncidents();
    }

    public function down(): void
    {
        Schema::dropIfExists('location_geocodes');
        Schema::dropIfExists('organization_contracts');
        Schema::dropIfExists('mission_events');
        Schema::dropIfExists('mission_reports');
        Schema::dropIfExists('mission_quality_reviews');
        Schema::dropIfExists('mission_incidents');
        Schema::dropIfExists('customer_claims');
        Schema::dropIfExists('pricing_logs');
        Schema::dropIfExists('email_logs');
        Schema::dropIfExists('google_calendar_events');
        Schema::dropIfExists('google_calendar_connections');
        Schema::dropIfExists('provider_daily_limits');
        Schema::dropIfExists('provider_favorites');
        Schema::dropIfExists('platform_modules');
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('account_subscriptions');
        Schema::dropIfExists('subscription_plans');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
    }

    /** Le corps d origine, extrait pour que son `return` ne quitte que lui. */
    private function corpsInitial(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'stripe_id')) {
                $table->string('stripe_id')->nullable()->index()->after('status');
            }
            if (! Schema::hasColumn('users', 'pm_type')) {
                $table->string('pm_type')->nullable()->after('stripe_id');
            }
            if (! Schema::hasColumn('users', 'pm_last_four')) {
                $table->string('pm_last_four', 4)->nullable()->after('pm_type');
            }
            if (! Schema::hasColumn('users', 'trial_ends_at')) {
                $table->timestamp('trial_ends_at')->nullable()->after('pm_last_four');
            }
        });

        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->morphs('billable');
                $table->string('type');
                $table->string('stripe_id')->unique();
                $table->string('stripe_status');
                $table->string('stripe_price')->nullable();
                $table->integer('quantity')->nullable();
                $table->timestamp('trial_ends_at')->nullable();
                $table->timestamp('ends_at')->nullable();
                $table->timestamps();
                $table->index(['billable_type', 'billable_id', 'stripe_status']);
            });
        }

        if (! Schema::hasTable('subscription_items')) {
            Schema::create('subscription_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
                $table->string('stripe_id')->unique();
                $table->string('stripe_product')->nullable();
                $table->string('stripe_price');
                $table->integer('quantity')->nullable();
                $table->string('meter_id')->nullable();
                $table->string('meter_event_name')->nullable();
                $table->timestamps();
                $table->index(['subscription_id', 'stripe_price']);
            });
        }

        if (! Schema::hasTable('subscription_plans')) {
            Schema::create('subscription_plans', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('audience')->default('client_personal');
                $table->string('stripe_price_id')->nullable()->index();
                $table->decimal('monthly_price', 10, 2)->default(0);
                $table->string('currency', 3)->default('EUR');
                $table->json('features')->nullable();
                $table->json('limits')->nullable();
                $table->boolean('is_active')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->timestamps();
                $table->index(['audience', 'is_active']);
            });
        }

        if (! Schema::hasTable('account_subscriptions')) {
            Schema::create('account_subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->nullOnDelete();
                $table->foreignId('subscription_plan_id')->nullable()->constrained('subscription_plans')->nullOnDelete();
                $table->string('status')->default('inactive');
                $table->string('stripe_subscription_id')->nullable()->index();
                $table->timestamp('started_at')->nullable();
                $table->timestamp('renews_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
                $table->index(['organization_account_id', 'status']);
            });
        }

        if (! Schema::hasTable('platform_settings')) {
            Schema::create('platform_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->longText('value')->nullable();
                $table->string('type')->default('string');
                $table->string('group')->default('general');
                $table->text('description')->nullable();
                $table->boolean('is_public')->default(false);
                $table->boolean('is_editable')->default(true);
                $table->timestamps();
                $table->index('group');
            });
        }

        if (! Schema::hasTable('platform_modules')) {
            Schema::create('platform_modules', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('name');
                $table->text('description')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->json('settings')->nullable();
                $table->json('required_permissions')->nullable();
                $table->timestamps();
                $table->index('is_enabled');
            });
        }

        if (! Schema::hasTable('provider_favorites')) {
            Schema::create('provider_favorites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('customer_user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->foreignId('customer_organization_id')->nullable()->constrained('organization_accounts')->cascadeOnDelete();
                $table->foreignId('provider_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status')->default('active');
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->unique(['customer_user_id', 'provider_user_id'], 'provider_favorites_customer_user_unique');
                $table->index(['customer_organization_id', 'status']);
            });
        }

        if (! Schema::hasTable('provider_daily_limits')) {
            Schema::create('provider_daily_limits', function (Blueprint $table) {
                $table->id();
                $table->foreignId('provider_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->nullOnDelete();
                $table->date('date');
                $table->unsignedInteger('max_bookings')->default(5);
                $table->unsignedInteger('max_minutes')->nullable();
                $table->boolean('locked_by_admin')->default(false);
                $table->timestamps();
                $table->unique(['provider_user_id', 'date']);
                $table->index(['organization_account_id', 'date']);
            });
        }

        if (! Schema::hasTable('google_calendar_connections')) {
            Schema::create('google_calendar_connections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->cascadeOnDelete();
                $table->string('google_account_email')->nullable();
                $table->text('access_token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->string('calendar_id')->nullable();
                $table->string('status')->default('active');
                $table->json('settings')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'status']);
                $table->index(['organization_account_id', 'status']);
            });
        }

        if (! Schema::hasTable('google_calendar_events')) {
            Schema::create('google_calendar_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('google_calendar_connection_id')->constrained('google_calendar_connections')->cascadeOnDelete();
                $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
                $table->string('google_event_id')->nullable()->index();
                $table->string('sync_status')->default('pending');
                $table->timestamp('last_synced_at')->nullable();
                $table->json('payload')->nullable();
                $table->text('last_error')->nullable();
                $table->timestamps();
                $table->index(['booking_id', 'sync_status']);
                $table->index(['mission_id', 'sync_status']);
            });
        }

        if (! Schema::hasTable('email_logs')) {
            Schema::create('email_logs', function (Blueprint $table) {
                $table->id();

                // Compatibilité ancienne logique
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('organization_account_id')->nullable()->constrained('organization_accounts')->nullOnDelete();
                $table->string('mailable')->nullable();
                $table->string('notification_type')->nullable();
                $table->string('to_email')->nullable();

                // Nouvelle logique utilisée par les notifications / logs mail
                $table->string('template_key')->nullable();
                $table->string('subject')->nullable();
                $table->string('status')->default('pending');
                $table->string('channel')->nullable();

                $table->string('recipient_email')->nullable();

                $table->string('notifiable_type')->nullable();
                $table->unsignedBigInteger('notifiable_id')->nullable();

                $table->text('error_message')->nullable();

                $table->json('meta')->nullable();
                $table->json('metadata')->nullable();

                $table->timestamp('sent_at')->nullable();
                $table->timestamp('failed_at')->nullable();

                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['organization_account_id', 'status']);
                $table->index(['notifiable_type', 'notifiable_id']);
                $table->index(['template_key', 'status']);
                $table->index('recipient_email');
            });
        }

        if (! Schema::hasTable('pricing_logs')) {
            Schema::create('pricing_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->nullable()->constrained('bookings')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('source')->default('system');
                $table->decimal('base_price', 10, 2)->nullable();
                $table->decimal('final_price', 10, 2)->nullable();
                $table->string('currency', 3)->default('EUR');
                $table->json('calculation_snapshot')->nullable();
                $table->timestamps();
                $table->index(['booking_id', 'created_at']);
                $table->index('source');
            });
        }

        if (! Schema::hasTable('customer_claims')) {
            Schema::create('customer_claims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('booking_id')->nullable()->constrained('bookings')->nullOnDelete();
                $table->foreignId('mission_id')->nullable()->constrained('missions')->nullOnDelete();
                $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('customer_organization_id')->nullable()->constrained('organization_accounts')->nullOnDelete();
                $table->string('claim_reference')->unique();
                $table->string('category')->nullable();
                $table->string('status')->default('open');
                $table->string('priority')->default('normal');
                $table->text('description')->nullable();
                $table->text('resolution')->nullable();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('resolved_at')->nullable();
                $table->json('attachments')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['customer_user_id', 'status']);
                $table->index(['customer_organization_id', 'status']);
                $table->index(['status', 'priority']);
            });
        }

        if (! Schema::hasTable('mission_incidents')) {
            Schema::create('mission_incidents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
                $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('category')->nullable();
                $table->string('severity')->default('normal');
                $table->string('status')->default('open');
                $table->text('description')->nullable();
                $table->text('resolution')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['mission_id', 'status']);
            });
        }

        if (! Schema::hasTable('mission_quality_reviews')) {
            Schema::create('mission_quality_reviews', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedTinyInteger('score')->nullable();
                $table->string('status')->default('pending');
                $table->text('notes')->nullable();
                $table->json('criteria_scores')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();
                $table->index(['mission_id', 'status']);
            });
        }

        if (! Schema::hasTable('mission_reports')) {
            Schema::create('mission_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
                $table->string('type')->default('final');
                $table->string('disk')->default('public');
                $table->string('path');
                $table->string('status')->default('generated');
                $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('generated_at')->nullable();
                $table->timestamp('sent_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['mission_id', 'type']);
            });
        }

        if (! Schema::hasTable('mission_events')) {
            Schema::create('mission_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('mission_id')->constrained('missions')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('event_type');
                $table->string('title')->nullable();
                $table->text('description')->nullable();
                $table->json('payload')->nullable();
                $table->timestamp('happened_at')->nullable();
                $table->timestamps();
                $table->index(['mission_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('organization_contracts')) {
            Schema::create('organization_contracts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_account_id')->constrained('organization_accounts')->cascadeOnDelete();
                $table->string('contract_reference')->unique();
                $table->string('status')->default('draft');
                $table->date('starts_at')->nullable();
                $table->date('ends_at')->nullable();
                $table->date('effective_from')->nullable();
                $table->date('effective_to')->nullable();
                $table->decimal('discount_rate', 5, 2)->nullable();
                $table->decimal('monthly_budget', 10, 2)->nullable();
                $table->string('billing_frequency')->default('monthly');
                $table->json('service_rules')->nullable();
                $table->json('pricing_rules')->nullable();
                $table->json('sla_rules')->nullable();
                $table->timestamps();
                $table->index(['organization_account_id', 'status']);
            });
        }

        if (! Schema::hasTable('location_geocodes')) {
            Schema::create('location_geocodes', function (Blueprint $table) {
                $table->id();
                $table->string('address_hash')->unique();
                $table->string('address');
                $table->string('city')->nullable();
                $table->string('postal_code')->nullable();
                $table->string('country', 2)->default('BE');
                $table->decimal('lat', 10, 7)->nullable();
                $table->decimal('lng', 10, 7)->nullable();
                $table->string('provider')->default('google');
                $table->string('place_id')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();
                $table->index(['postal_code', 'city']);
            });
        }
    }

    /** Fusionne depuis 2026_05_28_100004_add_actor_user_id_to_mission_events_table */
    private function fusion20260528100004AddActorUserIdToMissionEventsTable(): void
    {
        if (! Schema::hasTable('mission_events')) {
            return;
        }

        Schema::table('mission_events', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_events', 'actor_user_id')) {
                $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100015_add_missing_columns_to_customer_claims_table */
    private function fusion20260528100015AddMissingColumnsToCustomerClaimsTable(): void
    {
        if (! Schema::hasTable('customer_claims')) {
            return;
        }

        Schema::table('customer_claims', function (Blueprint $table) {
            if (! Schema::hasColumn('customer_claims', 'client_id')) {
                $table->unsignedBigInteger('client_id')->nullable()->index();
            }
            if (! Schema::hasColumn('customer_claims', 'rendez_vous_id')) {
                $table->unsignedBigInteger('rendez_vous_id')->nullable()->index();
            }
            if (! Schema::hasColumn('customer_claims', 'title')) {
                $table->string('title')->nullable();
            }
            if (! Schema::hasColumn('customer_claims', 'sla_due_at')) {
                $table->timestamp('sla_due_at')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100020_add_missing_columns_to_google_calendar_connections_table */
    private function fusion20260528100020AddMissingColumnsToGoogleCalendarConnectionsTa(): void
    {
        if (! Schema::hasTable('google_calendar_connections')) {
            return;
        }

        Schema::table('google_calendar_connections', function (Blueprint $table) {
            if (! Schema::hasColumn('google_calendar_connections', 'scope')) {
                $table->string('scope')->nullable();
            }
            if (! Schema::hasColumn('google_calendar_connections', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100030_add_missing_columns_to_mission_incidents_table */
    private function fusion20260528100030AddMissingColumnsToMissionIncidentsTable(): void
    {
        if (! Schema::hasTable('mission_incidents')) {
            return;
        }

        Schema::table('mission_incidents', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_incidents', 'reported_by_user_id')) {
                $table->unsignedBigInteger('reported_by_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_incidents', 'resolved_by_user_id')) {
                $table->unsignedBigInteger('resolved_by_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_incidents', 'incident_type')) {
                $table->string('incident_type')->nullable();
            }
            if (! Schema::hasColumn('mission_incidents', 'title')) {
                $table->string('title')->nullable();
            }
            if (! Schema::hasColumn('mission_incidents', 'resolution_notes')) {
                $table->text('resolution_notes')->nullable();
            }
            if (! Schema::hasColumn('mission_incidents', 'client_visible')) {
                $table->boolean('client_visible')->default(false);
            }
            if (! Schema::hasColumn('mission_incidents', 'reported_at')) {
                $table->timestamp('reported_at')->nullable();
            }
            if (! Schema::hasColumn('mission_incidents', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100032_add_missing_columns_to_mission_quality_reviews_table */
    private function fusion20260528100032AddMissingColumnsToMissionQualityReviewsTable(): void
    {
        if (! Schema::hasTable('mission_quality_reviews')) {
            return;
        }

        Schema::table('mission_quality_reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('mission_quality_reviews', 'reviewer_user_id')) {
                $table->unsignedBigInteger('reviewer_user_id')->nullable()->index();
            }
            if (! Schema::hasColumn('mission_quality_reviews', 'reviewer_role')) {
                $table->string('reviewer_role')->nullable();
            }
            if (! Schema::hasColumn('mission_quality_reviews', 'final_status')) {
                $table->string('final_status')->nullable();
            }
            if (! Schema::hasColumn('mission_quality_reviews', 'cleanliness_score')) {
                $table->unsignedInteger('cleanliness_score')->nullable();
            }
            if (! Schema::hasColumn('mission_quality_reviews', 'punctuality_score')) {
                $table->unsignedInteger('punctuality_score')->nullable();
            }
            if (! Schema::hasColumn('mission_quality_reviews', 'behavior_score')) {
                $table->unsignedInteger('behavior_score')->nullable();
            }
            if (! Schema::hasColumn('mission_quality_reviews', 'comment')) {
                $table->text('comment')->nullable();
            }
            if (! Schema::hasColumn('mission_quality_reviews', 'meta')) {
                $table->json('meta')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_28_100037_add_missing_columns_to_organization_contracts_table */
    private function fusion20260528100037AddMissingColumnsToOrganizationContractsTable(): void
    {
        if (! Schema::hasTable('organization_contracts')) {
            return;
        }

        Schema::table('organization_contracts', function (Blueprint $table) {
            if (! Schema::hasColumn('organization_contracts', 'allowed_service_catalog_ids')) {
                $table->json('allowed_service_catalog_ids')->nullable();
            }
            if (! Schema::hasColumn('organization_contracts', 'metadata')) {
                $table->json('metadata')->nullable();
            }
        });
    }

    /** Fusionne depuis 2026_05_11_231500_fix_portal_legacy_columns_round2 */
    private function fusionFixPortalLegacyColumnsRound2ProviderFavorites(): void
    {
        if (Schema::hasTable('provider_favorites')) {
            Schema::table('provider_favorites', function (Blueprint $table) {
                if (! Schema::hasColumn('provider_favorites', 'is_favorite')) {
                    $table->boolean('is_favorite')->default(true);
                }
            });
        }
    }

    /** Fusionne depuis 2026_05_13_194311_fix_brio_test_schema_compatibility_final */
    private function fusionFixBrioTestSchemaCompatibilityFinalLocationGeocodes(): void
    {
        if (Schema::hasTable('location_geocodes')) {
            Schema::table('location_geocodes', function (Blueprint $table) {
                if (! Schema::hasColumn('location_geocodes', 'lookup_hash')) {
                    $table->string('lookup_hash')->nullable()->index();
                }

                if (! Schema::hasColumn('location_geocodes', 'address_line')) {
                    $table->string('address_line')->nullable();
                }

                if (! Schema::hasColumn('location_geocodes', 'country_code')) {
                    $table->string('country_code', 2)->nullable();
                }

                if (! Schema::hasColumn('location_geocodes', 'raw')) {
                    $table->json('raw')->nullable();
                }
            });
        }
    }

    /** Fusionne depuis 2026_05_13_235712_fix_remaining_runtime_schema_compat_round5 */
    private function fusionFixRemainingRuntimeSchemaCompatRound5MissionQualityRevie(): void
    {
        // Analytics
        if (Schema::hasTable('mission_quality_reviews')) {
            Schema::table('mission_quality_reviews', function (Blueprint $table) {
                if (! Schema::hasColumn('mission_quality_reviews', 'overall_rating')) {
                    $table->unsignedTinyInteger('overall_rating')->nullable();
                }
            });
        }
    }

    /** Fusionne depuis 2026_05_13_235712_fix_remaining_runtime_schema_compat_round5 */
    private function fusionFixRemainingRuntimeSchemaCompatRound5GoogleCalendarConne(): void
    {
        // Google Calendar connections
        if (Schema::hasTable('google_calendar_connections')) {
            Schema::table('google_calendar_connections', function (Blueprint $table) {
                if (! Schema::hasColumn('google_calendar_connections', 'google_email')) {
                    $table->string('google_email')->nullable();
                }

                if (! Schema::hasColumn('google_calendar_connections', 'google_user_id')) {
                    $table->string('google_user_id')->nullable();
                }

                if (! Schema::hasColumn('google_calendar_connections', 'calendar_id')) {
                    $table->string('calendar_id')->nullable();
                }

                if (! Schema::hasColumn('google_calendar_connections', 'sync_enabled')) {
                    $table->boolean('sync_enabled')->default(true);
                }

                if (! Schema::hasColumn('google_calendar_connections', 'last_synced_at')) {
                    $table->timestamp('last_synced_at')->nullable();
                }

                if (! Schema::hasColumn('google_calendar_connections', 'last_sync_status')) {
                    $table->string('last_sync_status')->nullable();
                }

                if (! Schema::hasColumn('google_calendar_connections', 'last_sync_error')) {
                    $table->text('last_sync_error')->nullable();
                }
            });
        }
    }

    /** Fusionne depuis 2026_05_13_235712_fix_remaining_runtime_schema_compat_round5 */
    private function fusionFixRemainingRuntimeSchemaCompatRound5LocationGeocodes(): void
    {
        // Location cache
        if (Schema::hasTable('location_geocodes')) {
            Schema::table('location_geocodes', function (Blueprint $table) {
                if (! Schema::hasColumn('location_geocodes', 'address_hash')) {
                    $table->string('address_hash')->nullable();
                }

                if (! Schema::hasColumn('location_geocodes', 'lookup_hash')) {
                    $table->string('lookup_hash')->nullable();
                }

                if (! Schema::hasColumn('location_geocodes', 'raw')) {
                    $table->json('raw')->nullable();
                }
            });
        }
    }

    /** Fusionne depuis 2026_06_05_000001_add_provider_org_and_contract_links_for_sp4 */
    private function fusionAddProviderOrgAndContractLinksForSp4OrganizationContract(): void
    {
        if (Schema::hasTable('organization_contracts') && ! Schema::hasColumn('organization_contracts', 'provider_organization_id')) {
            Schema::table('organization_contracts', function (Blueprint $table) {
                $table->unsignedBigInteger('provider_organization_id')->nullable()->index();
            });
        }
    }

    /** Fusionne depuis 2026_08_11_000100_socle_du_kit_sur_place */
    private function fusionSocleDuKitSurPlaceMissionIncidents(): void
    {
        if (Schema::hasTable('mission_incidents')) {
            Schema::table('mission_incidents', function (Blueprint $table) {
                if (! Schema::hasColumn('mission_incidents', 'mission_media_id')) {
                    $table->unsignedBigInteger('mission_media_id')->nullable();
                }
                if (! Schema::hasColumn('mission_incidents', 'notified_at')) {
                    $table->timestamp('notified_at')->nullable();
                }
                if (! Schema::hasColumn('mission_incidents', 'complaint_case_id')) {
                    $table->unsignedBigInteger('complaint_case_id')->nullable();
                }
            });
        }
    }
};
