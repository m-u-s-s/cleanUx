<?php

namespace Database\Seeders;

use App\Models\QualityChecklist;
use App\Models\QualityChecklistItem;
use Illuminate\Database\Seeder;

class QualityChecklistsSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'checklist' => [
                    'code' => 'generic_post',
                    'name' => 'Inspection post-mission (générique)',
                    'description' => 'Checklist par défaut tous trades, à appliquer en fin de mission',
                    'trade_codes' => null,
                    'phase' => QualityChecklist::PHASE_POST,
                    'is_active' => true,
                    'version' => 1,
                ],
                'items' => [
                    ['code' => 'site_clean', 'label' => 'Chantier laissé propre', 'item_type' => 'boolean', 'weight' => 3, 'required' => true],
                    ['code' => 'photo_after', 'label' => 'Photo après prestation', 'item_type' => 'photo', 'weight' => 2, 'required' => true],
                    ['code' => 'client_satisfaction', 'label' => 'Niveau satisfaction client (1-5)', 'item_type' => 'rating', 'weight' => 5, 'required' => true,
                        'valid_options' => ['max' => 5]],
                    ['code' => 'notes', 'label' => 'Notes complémentaires', 'item_type' => 'text', 'weight' => 1, 'required' => false],
                ],
            ],
            [
                'checklist' => [
                    'code' => 'cleaning_post',
                    'name' => 'Inspection nettoyage (post)',
                    'description' => 'Checklist spécifique nettoyage en fin de prestation',
                    'trade_codes' => ['nettoyage', 'cleaning'],
                    'phase' => QualityChecklist::PHASE_POST,
                    'is_active' => true,
                    'version' => 1,
                ],
                'items' => [
                    ['code' => 'kitchen_done', 'label' => 'Cuisine nettoyée', 'item_type' => 'boolean', 'weight' => 4],
                    ['code' => 'bathroom_done', 'label' => 'Salle de bain nettoyée', 'item_type' => 'boolean', 'weight' => 4],
                    ['code' => 'floors_done', 'label' => 'Sols nettoyés/aspirés', 'item_type' => 'boolean', 'weight' => 3],
                    ['code' => 'photo_after', 'label' => 'Photo après', 'item_type' => 'photo', 'weight' => 2, 'required' => true],
                    ['code' => 'rating', 'label' => 'Note globale (1-5)', 'item_type' => 'rating', 'weight' => 5, 'valid_options' => ['max' => 5]],
                ],
            ],
            [
                'checklist' => [
                    'code' => 'painting_pre',
                    'name' => 'Inspection peinture (pre)',
                    'description' => 'Photos avant + protection des sols/meubles',
                    'trade_codes' => ['peinture', 'painting'],
                    'phase' => QualityChecklist::PHASE_PRE,
                    'is_active' => true,
                    'version' => 1,
                ],
                'items' => [
                    ['code' => 'photo_before', 'label' => 'Photos avant intervention', 'item_type' => 'photo', 'weight' => 3, 'required' => true],
                    ['code' => 'floor_protected', 'label' => 'Sols protégés (bâches)', 'item_type' => 'boolean', 'weight' => 2],
                    ['code' => 'furniture_moved', 'label' => 'Meubles déplacés/protégés', 'item_type' => 'boolean', 'weight' => 2],
                    ['code' => 'surface_prepared', 'label' => 'Surfaces préparées (poncage, nettoyage)', 'item_type' => 'boolean', 'weight' => 3],
                ],
            ],
        ];

        foreach ($templates as $template) {
            $checklist = QualityChecklist::query()->updateOrCreate(
                ['code' => $template['checklist']['code']],
                $template['checklist'],
            );

            // Wipe existing items and re-seed (development convenience)
            $checklist->items()->delete();

            foreach (array_values($template['items']) as $i => $item) {
                QualityChecklistItem::create(array_merge([
                    'checklist_id' => $checklist->id,
                    'position' => $i + 1,
                    'required' => $item['required'] ?? true,
                ], $item));
            }
        }
    }
}
