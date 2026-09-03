<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * LES SIX GABARITS QUITTENT LE `match` PHP POUR LA BASE.
 *
 * `ProductEmailTemplates::payload()` les tenait dans un `match` de cent lignes : ni modifiables,
 * ni traduisibles, ni duplicables. La table qui les attend existe depuis toujours et n'a jamais
 * porté une ligne.
 *
 * Les valeurs d'exemple deviennent des VARIABLES : `{{service}}` plutôt que « Nettoyage standard ».
 * Un gabarit décrit une forme, pas un cas particulier.
 *
 * Idempotente : elle n'écrit que ce qui manque, et se rejoue sans rien écraser.
 */
return new class extends Migration
{
    public function up(): void
    {
        $themeId = $this->themeParDefaut();

        foreach ($this->gabarits() as $gabarit) {
            if (DB::table('email_templates')->where('code', $gabarit['code'])->exists()) {
                continue;
            }

            DB::table('email_templates')->insert([
                'code' => $gabarit['code'],
                'name' => $gabarit['name'],
                'description' => $gabarit['description'],
                'category' => $gabarit['category'],
                'subject_pattern' => $gabarit['subject'],
                'preheader' => $gabarit['preheader'],
                'body_html_pattern' => '',
                'body_text_pattern' => null,
                'blocks' => json_encode($gabarit['blocks'], JSON_UNESCAPED_UNICODE),
                'email_theme_id' => $gabarit['impose_le_theme'] ? $themeId : null,
                'locale_overrides' => null,
                'required_variables' => json_encode($gabarit['variables']),
                'is_active' => true,
                'metadata' => json_encode(['origine' => 'migration_socle_2026_10_05'], JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * PAS DE RETOUR EN ARRIÈRE SUR LE CONTENU.
     *
     * Ces lignes deviennent éditables dès leur création : les effacer emporterait le travail de
     * l'administrateur avec le gabarit d'origine. Le thème par défaut, lui, se retire.
     */
    public function down(): void
    {
        DB::table('email_themes')->where('code', 'brio')->delete();
    }

    private function themeParDefaut(): int
    {
        $existant = DB::table('email_themes')->where('code', 'brio')->value('id');

        if ($existant !== null) {
            return (int) $existant;
        }

        // LES VALEURS DE LA COQUILLE HISTORIQUE, reprises a l identique : le socle ne change pas
        // l apparence des e-mails, il la rend modifiable.
        return (int) DB::table('email_themes')->insertGetId([
            'code' => 'brio',
            'name' => 'Brio',
            'description' => 'Habillage permanent de la plateforme.',
            'is_default' => true,
            'is_active' => true,
            'priority' => 0,
            'recurs_yearly' => false,
            'color_accent' => '#ffb648',
            'color_accent_contrast' => '#0f172a',
            'color_page_background' => '#f8fafc',
            'color_card_background' => '#ffffff',
            'color_text' => '#0f172a',
            'color_text_muted' => '#475569',
            'color_border' => '#e2e8f0',
            'color_banner_from' => '#0f172a',
            'color_banner_to' => '#1e293b',
            'font_stack' => 'Arial, Helvetica, sans-serif',
            'corner_radius' => 20,
            'footer_text' => 'Brio — plateforme de gestion des interventions.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function gabarits(): array
    {
        return [
            [
                'code' => 'booking_confirmed',
                'name' => 'Rendez-vous confirmé',
                'description' => 'Part au client dès qu’une intervention est validée.',
                'category' => 'transactionnel',
                'subject' => 'Rendez-vous confirmé',
                'preheader' => 'Votre intervention du {{date}} est validée.',
                'impose_le_theme' => false,
                'variables' => ['client_name', 'service', 'date', 'heure', 'adresse', 'action_url'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Votre intervention est confirmée'],
                    ['type' => 'paragraph', 'text' => 'Bonjour {{client_name}}, votre prestation a bien été validée dans Brio.'],
                    ['type' => 'details', 'rows' => [
                        ['label' => 'Service', 'value' => '{{service}}'],
                        ['label' => 'Date', 'value' => '{{date}} à {{heure}}'],
                        ['label' => 'Adresse', 'value' => '{{adresse}}'],
                    ]],
                    ['type' => 'highlight', 'text' => 'Vous recevrez un rappel avant l’intervention.'],
                    ['type' => 'button', 'text' => 'Voir mon espace client', 'url' => '{{action_url}}'],
                    ['type' => 'paragraph', 'text' => 'Merci pour votre confiance.'],
                ],
            ],
            [
                'code' => 'booking_reminder',
                'name' => 'Rappel de rendez-vous',
                'description' => 'Part avant l’intervention, à l’échéance choisie.',
                'category' => 'rappel',
                'subject' => 'Rappel de votre intervention',
                'preheader' => 'Votre intervention approche.',
                'impose_le_theme' => false,
                'variables' => ['client_name', 'service', 'date', 'heure', 'adresse', 'action_url'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Votre intervention approche'],
                    ['type' => 'paragraph', 'text' => 'Bonjour {{client_name}}, votre intervention est prévue prochainement.'],
                    ['type' => 'details', 'rows' => [
                        ['label' => 'Service', 'value' => '{{service}}'],
                        ['label' => 'Créneau', 'value' => '{{date}} à {{heure}}'],
                        ['label' => 'Lieu', 'value' => '{{adresse}}'],
                    ]],
                    ['type' => 'button', 'text' => 'Vérifier ma réservation', 'url' => '{{action_url}}'],
                    ['type' => 'paragraph', 'text' => 'À très bientôt.'],
                ],
            ],
            [
                'code' => 'feedback_request',
                'name' => 'Demande d’avis',
                'description' => 'Part après l’intervention pour recueillir un retour.',
                'category' => 'transactionnel',
                'subject' => 'Comment s’est passée votre intervention ?',
                'preheader' => 'Trente secondes suffisent.',
                'impose_le_theme' => false,
                'variables' => ['client_name', 'service', 'date', 'action_url'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Votre avis compte vraiment'],
                    ['type' => 'paragraph', 'text' => 'Bonjour {{client_name}}, prenez trente secondes pour nous dire comment s’est passée votre prestation.'],
                    ['type' => 'details', 'rows' => [
                        ['label' => 'Service', 'value' => '{{service}}'],
                        ['label' => 'Intervention', 'value' => '{{date}}'],
                    ]],
                    ['type' => 'button', 'text' => 'Laisser un avis', 'url' => '{{action_url}}'],
                    ['type' => 'paragraph', 'text' => 'Votre retour nous aide à améliorer la qualité.'],
                ],
            ],
            [
                'code' => 'finance_reminder',
                'name' => 'Rappel de facture',
                'description' => 'Relance une facture dont le solde reste ouvert.',
                'category' => 'transactionnel',
                'subject' => 'Rappel de facture Brio',
                'preheader' => 'Un solde reste à régler.',
                // UNE FACTURE NE SE DEGUISE PAS. Elle garde l habit de la maison en toute saison.
                'impose_le_theme' => true,
                'variables' => ['client_name', 'invoice_number', 'total', 'balance', 'due_date', 'action_url'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Un solde reste à régler'],
                    ['type' => 'paragraph', 'text' => 'Bonjour {{client_name}}, nous vous envoyons un rappel concernant une facture encore ouverte.'],
                    ['type' => 'details', 'rows' => [
                        ['label' => 'Facture', 'value' => '{{invoice_number}}'],
                        ['label' => 'Montant total', 'value' => '{{total}}'],
                        ['label' => 'Reste à payer', 'value' => '{{balance}}'],
                        ['label' => 'Échéance', 'value' => '{{due_date}}'],
                    ]],
                    ['type' => 'button', 'text' => 'Voir mes documents', 'url' => '{{action_url}}'],
                    ['type' => 'paragraph', 'text' => 'Si le paiement a déjà été effectué, ignorez simplement ce message.'],
                ],
            ],
            [
                'code' => 'new_booking_admin',
                'name' => 'Nouvelle demande — équipe',
                'description' => 'Prévient l’équipe qu’une demande attend une décision.',
                'category' => 'interne',
                'subject' => 'Nouvelle demande d’intervention',
                'preheader' => 'Une demande attend une décision.',
                'impose_le_theme' => true,
                'variables' => ['client_name', 'service', 'priorite', 'action_url'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Une nouvelle demande nécessite votre attention'],
                    ['type' => 'paragraph', 'text' => 'Une demande vient d’être enregistrée sur la plateforme.'],
                    ['type' => 'details', 'rows' => [
                        ['label' => 'Client', 'value' => '{{client_name}}'],
                        ['label' => 'Service', 'value' => '{{service}}'],
                        ['label' => 'Priorité', 'value' => '{{priorite}}'],
                    ]],
                    ['type' => 'button', 'text' => 'Ouvrir le planning', 'url' => '{{action_url}}'],
                    ['type' => 'paragraph', 'text' => 'Merci de confirmer ou de réaffecter rapidement cette mission.'],
                ],
            ],
            [
                'code' => 'status_update',
                'name' => 'Changement de statut',
                'description' => 'Informe le client qu’une intervention a changé d’état.',
                'category' => 'transactionnel',
                'subject' => 'Mise à jour de votre demande',
                'preheader' => 'Le statut de votre intervention a changé.',
                'impose_le_theme' => false,
                'variables' => ['client_name', 'statut', 'date', 'heure', 'action_url'],
                'blocks' => [
                    ['type' => 'heading', 'text' => 'Le statut de votre intervention a changé'],
                    ['type' => 'paragraph', 'text' => 'Bonjour {{client_name}}, votre intervention a été mise à jour dans votre espace.'],
                    ['type' => 'details', 'rows' => [
                        ['label' => 'Nouveau statut', 'value' => '{{statut}}'],
                        ['label' => 'Date', 'value' => '{{date}} à {{heure}}'],
                    ]],
                    ['type' => 'button', 'text' => 'Voir le détail', 'url' => '{{action_url}}'],
                    ['type' => 'paragraph', 'text' => 'Nous restons disponibles si vous avez une question.'],
                ],
            ],
        ];
    }
};
