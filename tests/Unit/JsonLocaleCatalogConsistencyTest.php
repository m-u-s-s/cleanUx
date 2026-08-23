<?php

namespace Tests\Unit;

use Tests\TestCase;

class JsonLocaleCatalogConsistencyTest extends TestCase
{
    public function test_custom_json_locales_share_the_same_translation_keys(): void
    {
        $fr = json_decode(file_get_contents(lang_path('fr.json')), true, 512, JSON_THROW_ON_ERROR);
        $nl = json_decode(file_get_contents(lang_path('nl.json')), true, 512, JSON_THROW_ON_ERROR);
        $en = json_decode(file_get_contents(lang_path('en.json')), true, 512, JSON_THROW_ON_ERROR);

        $expectedKeys = array_keys($fr);
        sort($expectedKeys);

        // Les deux catalogues compares ensemble, et l'ecart NOMME : `assertSame` sur deux longues
        // listes triees produit un diff illisible, alors que la difference symetrique dit
        // exactement quelles cles manquent et lesquelles sont en trop.
        $ecarts = [];

        foreach (['nl' => $nl, 'en' => $en] as $locale => $catalog) {
            $keys = array_keys($catalog);
            sort($keys);

            foreach (array_diff($expectedKeys, $keys) as $manquante) {
                $ecarts[] = "{$locale} : « {$manquante} » manque";
            }

            foreach (array_diff($keys, $expectedKeys) as $enTrop) {
                $ecarts[] = "{$locale} : « {$enTrop} » en trop";
            }
        }

        $this->assertSame([], $ecarts, 'Ces catalogues ne correspondent pas aux cles du francais.');
    }
}
