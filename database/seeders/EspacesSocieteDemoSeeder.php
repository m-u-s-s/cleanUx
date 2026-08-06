<?php

namespace Database\Seeders;

use Database\Seeders\Concerns\SeedsOnlyExistingColumns;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * LES CINQ ÉCRANS DE L'ESPACE SOCIÉTÉ N'AVAIENT AUCUNE DONNÉE À MONTRER.
 *
 * Après un `db:seed` complet sur une base vierge, le profil démo produit 2 organisations, 4 membres
 * et 2 sites — mais 0 équipe terrain, 0 tâche, 0 canal et 0 mission. Les écrans construits cette
 * semaine (Répartition, Équipe, Équipes terrain, Tâches, Canaux) s'ouvrent donc tous sur un état
 * vide, côté web comme en natif.
 *
 * C'est un angle mort connu de ce dépôt : un écran vide se laisse regarder sans rien dire. Il ne
 * distingue pas « cette société n'a pas encore d'équipe » de « la requête est fausse » ni de « la
 * table n'est jamais remplie ». Ce seeder supprime cette ambiguïté à la racine — et l'a d'ailleurs
 * prouvé aussitôt en révélant que le tableau de répartition lisait `scheduled_at`, colonne qui
 * n'existe pas (corrigé dans le même commit).
 *
 * IDEMPOTENCE. Chaque ligne est cherchée sur une clé métier stable avant d'être écrite :
 * le nom pour une équipe, un canal ; le titre pour une tâche ; le couple (canal, auteur, contenu)
 * pour un message. Relancer le seeder mille fois donne la même base.
 *
 * LES MISSIONS FONT EXCEPTION : la table ne porte aucune référence lisible qui puisse servir de clé.
 * On les marque donc dans `metadata` et on les retrouve en PHP après lecture. Ce détour évite
 * d'inventer une colonne — et un `where('metadata->demo')` aurait de toute façon été filtré par
 * `onlyExistingColumns()`, qui ne connaît que de vrais noms de colonnes.
 *
 * Les missions sont replanifiées SUR LE JOUR COURANT à chaque exécution, parce que le tableau de
 * répartition ouvre sur `filterDate = aujourd'hui`. Des missions figées à la date du seed
 * laisseraient l'écran vide dès le lendemain — soit exactement le défaut qu'on corrige ici.
 */
class EspacesSocieteDemoSeeder extends Seeder
{
    use SeedsOnlyExistingColumns;

    /** Marqueur écrit dans `missions.metadata`, seul moyen de les retrouver sans clé métier. */
    private const MARQUEUR_MISSION = 'espaces-societe-demo';

    public function run(): void
    {
        $societe = $this->societePrestataire();

        if (! $societe) {
            $this->command?->warn('⚠️ Aucune société prestataire avec membre actif : espaces société ignorés.');

            return;
        }

        $membres = $this->membresActifs($societe->id);

        if ($membres === []) {
            $this->command?->warn('⚠️ Société prestataire sans membre actif : espaces société ignorés.');

            return;
        }

        $responsable = $membres[0];

        $this->seedEquipesTerrain($societe, $membres);
        $this->seedTaches($societe, $responsable);
        $this->seedCanaux($societe, $membres);
        $this->seedMissions($societe, $membres);

        $this->command?->info('✅ Espaces société démo : équipes terrain, tâches, canaux et missions à répartir.');
    }

    /**
     * On cherche par TYPE, pas par nom.
     *
     * `DemoPlatformSeeder` nomme la sienne « CleanUx Partner Brussels », mais figer ce nom ici
     * créerait une dépendance invisible : renommer la société là-bas viderait les espaces ici, sans
     * la moindre erreur. Le type, lui, est ce que les cinq écrans vérifient réellement.
     */
    private function societePrestataire(): ?object
    {
        if (! $this->hasTable('organization_accounts')) {
            return null;
        }

        return DB::table('organization_accounts')
            ->where('type', 'provider_company')
            ->orderBy('id')
            ->first();
    }

    /** @return list<object> membres actifs, propriétaire en tête */
    private function membresActifs(int $societeId): array
    {
        if (! $this->hasTable('organization_members')) {
            return [];
        }

        return DB::table('organization_members as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->where('m.organization_account_id', $societeId)
            ->where('m.status', 'active')
            ->orderByRaw("CASE WHEN m.role = 'owner' THEN 0 ELSE 1 END")
            ->orderBy('m.id')
            ->select(['m.user_id', 'm.role', 'u.name'])
            ->get()
            ->all();
    }

    /** @param  list<object>  $membres */
    private function seedEquipesTerrain(object $societe, array $membres): void
    {
        $definitions = [
            ['name' => 'Équipe Bruxelles Centre', 'color' => '#f59e0b', 'max' => 3],
            ['name' => 'Équipe Périphérie', 'color' => '#38bdf8', 'max' => 2],
        ];

        foreach ($definitions as $rang => $definition) {
            // Le chef est le membre de même rang s'il existe, sinon le premier : une société d'un
            // seul membre reste cohérente plutôt que de produire une équipe sans chef.
            $chef = $membres[$rang] ?? $membres[0];

            $equipe = $this->updateOrInsertTable('field_teams', [
                'organization_account_id' => $societe->id,
                'name' => $definition['name'],
            ], [
                'team_lead_user_id' => $chef->user_id,
                'status' => 'active',
                'color' => $definition['color'],
                'is_internal' => true,
                'max_concurrent_missions' => $definition['max'],
                // Le slug est préfixé par l'identifiant de la société : deux sociétés démo qui
                // nommeraient leurs équipes pareil ne se marcheraient pas dessus.
                'slug' => Str::slug($definition['name']).'-'.$societe->id,
                'notes' => 'Équipe de démonstration.',
                'metadata' => ['seeded' => true],
            ]);

            if (! $equipe) {
                continue;
            }

            $this->seedMembresEquipe($equipe, $membres, $rang, $chef);
        }
    }

    /** @param  list<object>  $membres */
    private function seedMembresEquipe(object $equipe, array $membres, int $rang, object $chef): void
    {
        // Le chef, plus un équipier pris en alternance : chaque équipe a au moins deux lignes, et
        // aucun membre ne se retrouve seul dans les deux.
        $affectes = [$chef];

        foreach ($membres as $index => $membre) {
            if ($membre->user_id !== $chef->user_id && $index % 2 === $rang % 2) {
                $affectes[] = $membre;
            }
        }

        foreach ($affectes as $membre) {
            $estChef = $membre->user_id === $chef->user_id;

            $this->updateOrInsertTable('field_team_members', [
                'field_team_id' => $equipe->id,
                'user_id' => $membre->user_id,
            ], [
                'role' => $estChef ? 'lead' : 'worker',
                'role_on_team' => $estChef ? 'lead' : 'worker',
                'is_team_lead' => $estChef,
                'status' => 'active',
                'is_active' => true,
                'joined_at' => now()->subMonths(2),
                'metadata' => ['seeded' => true],
            ]);
        }
    }

    private function seedTaches(object $societe, object $responsable): void
    {
        /*
         * PRIORITÉS PRISES DANS L'ÉNUMÉRATION DE L'API, PAS DANS LE DÉFAUT DE LA COLONNE.
         *
         * `tasks.priority` a pour défaut `'normal'`, valeur qu'aucune constante du modèle ne
         * déclare et que la validation de `CompanyController::createTask` refuse
         * (`in:low,medium,high,urgent`). Une tâche née du défaut porterait donc une priorité que
         * l'API elle-même rejetterait. On ne s'appuie jamais sur ce défaut ici.
         */
        $taches = [
            ['Commander les recharges de produits', 'in_progress', 'high', 2, 'Stock bas sur le dégraissant vitres.'],
            ['Planifier la visite de contrôle Atlas', 'todo', 'medium', 5, 'Trimestrielle, à caler avec le client.'],
            ['Renouveler l\'assurance du véhicule 2', 'todo', 'urgent', 1, 'Échéance proche.'],
            ['Former le nouvel équipier au protocole vitres', 'done', 'low', -3, null],
        ];

        foreach ($taches as [$titre, $statut, $priorite, $jours, $description]) {
            $this->updateOrInsertTable('tasks', [
                'organization_account_id' => $societe->id,
                'title' => $titre,
            ], [
                'created_by' => $responsable->user_id,
                'description' => $description,
                'status' => $statut,
                'priority' => $priorite,
                'due_date' => now()->addDays($jours)->toDateString(),
                'completed_at' => $statut === 'done' ? now()->subDays(2) : null,
                'metadata' => ['seeded' => true],
            ]);
        }
    }

    /** @param  list<object>  $membres */
    private function seedCanaux(object $societe, array $membres): void
    {
        $canaux = [
            [
                'name' => 'général',
                'type' => 'team',
                'messages' => [
                    'Bonjour à toutes et tous, planning de la semaine publié.',
                    'Pensez à confirmer votre présence avant vendredi.',
                    'Merci, c\'est noté de mon côté.',
                ],
            ],
            [
                'name' => 'urgences',
                'type' => 'announcement',
                'messages' => [
                    'Canal réservé aux imprévus terrain : panne, accès bloqué, absence.',
                    'Accès livraison condamné rue des Palais, passer par l\'arrière.',
                ],
            ],
        ];

        foreach ($canaux as $definition) {
            $canal = $this->updateOrInsertTable('channels', [
                'organization_account_id' => $societe->id,
                'name' => $definition['name'],
            ], [
                'type' => $definition['type'],
                'is_private' => false,
                'is_archived' => false,
                'is_locked' => false,
                'created_by' => $membres[0]->user_id,
                'settings' => ['seeded' => true],
            ]);

            if (! $canal) {
                continue;
            }

            /*
             * L'APPARTENANCE N'EST PAS DÉCORATIVE.
             *
             * `CompanyController::channels()` filtre par `whereHas('members', user_id = moi)`. Un
             * canal seedé sans sa table pivot serait donc invisible pour tout le monde — présent en
             * base, absent de l'écran : exactement le genre de « donnée qui existe et ne se voit
             * pas » qu'on cherche à éliminer.
             */
            foreach ($membres as $index => $membre) {
                $this->updateOrInsertTable('channel_members', [
                    'channel_id' => $canal->id,
                    'user_id' => $membre->user_id,
                ], [
                    'role' => $index === 0 ? 'owner' : 'member',
                    'last_read_at' => now()->subHours(3),
                ]);
            }

            foreach ($definition['messages'] as $rang => $contenu) {
                $auteur = $membres[$rang % count($membres)];

                $this->updateOrInsertTable('messages', [
                    'channel_id' => $canal->id,
                    'user_id' => $auteur->user_id,
                    'content' => $contenu,
                ], [
                    'type' => 'text',
                    'created_at' => now()->subHours(count($definition['messages']) - $rang),
                ]);
            }
        }
    }

    /** @param  list<object>  $membres */
    private function seedMissions(object $societe, array $membres): void
    {
        if (! $this->hasTable('missions')) {
            return;
        }

        $site = $this->siteClient();

        /*
         * DEUX NOTIONS D'ÉQUIPE COEXISTENT, ET ELLES NE SONT PAS INTERCHANGEABLES.
         *
         * `missions.provider_team_id` est contraint sur `provider_teams` — PAS sur `field_teams`,
         * la table qu'alimente l'écran « Équipes terrain ». Y écrire l'identifiant d'une équipe
         * terrain a fait échouer le premier seed sur une violation de clé étrangère, et seulement à
         * la deuxième mission : la première portait l'identifiant 1, qui existait par hasard dans
         * les deux tables. Une erreur d'un rang plus bas serait passée inaperçue.
         */
        $equipePrestataire = $this->hasTable('provider_teams')
            ? DB::table('provider_teams')->where('organization_account_id', $societe->id)->orderBy('id')->first()
            : null;

        /*
         * TROIS MISSIONS NON ATTRIBUÉES, UNE ATTRIBUÉE.
         *
         * Un tableau de répartition dont tout est déjà réparti ne montre pas ce qu'il sait faire :
         * le bouton d'affectation n'apparaît que pour les statuts `pending` et `assigned`. On seede
         * donc les deux états, pour que l'écran montre à la fois le travail à faire et son résultat.
         */
        $definitions = [
            ['rang' => 1, 'heure' => 8, 'status' => 'pending', 'notes' => 'Nettoyage bureaux — plateau 2.'],
            ['rang' => 2, 'heure' => 11, 'status' => 'pending', 'notes' => 'Vitrerie hall d\'accueil.'],
            ['rang' => 3, 'heure' => 14, 'status' => 'pending', 'notes' => 'Remise en état après travaux.'],
            ['rang' => 4, 'heure' => 16, 'status' => 'assigned', 'notes' => 'Passage hebdomadaire sanitaires.'],
        ];

        $existantes = $this->missionsDemoExistantes($societe->id);

        foreach ($definitions as $definition) {
            $valeurs = $this->onlyExistingColumns('missions', [
                'provider_organization_id' => $societe->id,
                'organization_account_id' => $societe->id,
                'organization_site_id' => $site?->id,
                'provider_team_id' => $equipePrestataire?->id,
                'status' => $definition['status'],
                'mission_type' => 'standard',
                'planned_start_at' => now()->startOfDay()->addHours($definition['heure']),
                'planned_end_at' => now()->startOfDay()->addHours($definition['heure'] + 2),
                'estimated_duration_minutes' => 120,
                'notes' => $definition['notes'],
                'lead_provider_user_id' => $definition['status'] === 'assigned' ? $membres[0]->user_id : null,
                'metadata' => ['seeded' => true, 'demo' => self::MARQUEUR_MISSION, 'rang' => $definition['rang']],
                'updated_at' => now(),
            ]);

            $existante = $existantes[$definition['rang']] ?? null;

            if ($existante) {
                DB::table('missions')->where('id', $existante->id)->update($valeurs);
                $missionId = $existante->id;
            } else {
                $missionId = DB::table('missions')->insertGetId($valeurs + ['created_at' => now()]);
            }

            if ($definition['status'] === 'assigned') {
                $this->seedAffectation($missionId, $membres[0]);
            }
        }
    }

    /**
     * Les missions démo se retrouvent par leur marqueur, lu en PHP.
     *
     * @return array<int, object> indexées par rang
     */
    private function missionsDemoExistantes(int $societeId): array
    {
        if (! $this->hasColumn('missions', 'metadata')) {
            return [];
        }

        $trouvees = [];

        foreach (DB::table('missions')->where('provider_organization_id', $societeId)->get() as $mission) {
            $metadata = json_decode((string) ($mission->metadata ?? ''), true);

            if (is_array($metadata) && ($metadata['demo'] ?? null) === self::MARQUEUR_MISSION) {
                $trouvees[(int) ($metadata['rang'] ?? 0)] = $mission;
            }
        }

        return $trouvees;
    }

    /**
     * La mission se déroule chez le client, pas chez le prestataire.
     *
     * `DemoPlatformSeeder` ne crée de sites que pour la société cliente ; c'est cohérent avec le
     * métier — une société de nettoyage intervient sur les sites de ses clients.
     */
    private function siteClient(): ?object
    {
        if (! $this->hasTable('organization_sites')) {
            return null;
        }

        return DB::table('organization_sites as s')
            ->join('organization_accounts as o', 'o.id', '=', 's.organization_account_id')
            ->where('o.type', 'client_company')
            ->orderBy('s.id')
            ->select('s.*')
            ->first();
    }

    private function seedAffectation(int $missionId, object $membre): void
    {
        $this->updateOrInsertTable('mission_assignments', [
            'mission_id' => $missionId,
            'user_id' => $membre->user_id,
        ], [
            'role' => 'lead',
            'role_on_mission' => 'lead',
            'status' => 'accepted',
            'assignment_status' => 'accepted',
            'assigned_at' => now()->subDay(),
            'accepted_at' => now()->subDay()->addHours(2),
        ]);
    }
}
