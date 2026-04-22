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

        foreach (['nl' => $nl, 'en' => $en] as $locale => $catalog) {
            $keys = array_keys($catalog);
            sort($keys);

            $this->assertSame($expectedKeys, $keys, sprintf('Locale %s does not match FR JSON keys.', $locale));
        }
    }
}
