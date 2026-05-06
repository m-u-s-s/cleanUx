<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 4 — Index de recherche full-text sur messages.content.
 *
 * Stratégie compatible MySQL 5.7+/8 ET PostgreSQL 11+.
 * Sur SQLite (tests), on skip silencieusement.
 *
 * Pour MySQL on utilise un FULLTEXT INDEX (InnoDB).
 * Pour PostgreSQL on crée un index GIN sur to_tsvector('simple', content).
 *
 * Le scope `whereSearch($term)` du model Message dispatchera selon le driver.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // Détecter si l'index existe déjà avant de le créer
            $exists = collect(DB::select("SHOW INDEX FROM messages WHERE Key_name = 'messages_content_fulltext'"))->isNotEmpty();
            if (! $exists) {
                DB::statement('ALTER TABLE messages ADD FULLTEXT messages_content_fulltext (content)');
            }
        } elseif ($driver === 'pgsql') {
            DB::statement(<<<'SQL'
                CREATE INDEX IF NOT EXISTS messages_content_fulltext
                ON messages
                USING gin (to_tsvector('simple', coalesce(content, '')))
            SQL);
        }
        // SQLite (tests) : aucun index full-text natif. On skip ; le scope
        // de recherche tombera sur LIKE %term% dans ce cas.
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            try {
                DB::statement('ALTER TABLE messages DROP INDEX messages_content_fulltext');
            } catch (\Throwable $e) {
                // Index probablement déjà absent
            }
        } elseif ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS messages_content_fulltext');
        }
    }
};
