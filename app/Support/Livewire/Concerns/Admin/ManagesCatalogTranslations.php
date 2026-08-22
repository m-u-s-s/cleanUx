<?php

namespace App\Support\Livewire\Concerns\Admin;

/**
 * Les langues à traduire, dites à UN SEUL endroit.
 *
 * `QuestionnaireBuilder` portait cette liste ; `CatalogCenter` en avait besoin à son tour. La
 * recopier aurait créé la situation que ce dépôt décrit lui-même ailleurs — « trois copies d'une
 * même règle finissent par diverger, et c'est alors la plus permissive qui décide sans que
 * personne ne le remarque ». Une langue activée dans `config/i18n.php` mais oubliée dans l'une des
 * copies produirait un écran d'administration où cette langue est simplement introuvable, sans le
 * moindre message.
 *
 * Ce trait ne porte QUE la partie réellement commune. La recherche du modèle et la liste des champs
 * traduisibles restent chez chaque écran : elles diffèrent par nature, et une liste de champs
 * doit rester FERMÉE et lisible à l'endroit où elle s'applique.
 */
trait ManagesCatalogTranslations
{
    /**
     * Les langues à proposer : celles activées, moins celle des libellés de base.
     *
     * La langue par défaut est exclue parce qu'elle EST la colonne : proposer de « traduire en
     * français » un libellé déjà français inviterait à écrire deux fois la même chose à deux
     * endroits, dont un seul ferait foi.
     *
     * @return array<string, string>
     */
    public function translationLocales(): array
    {
        $default = (string) config('i18n.default', 'fr');

        return collect((array) config('i18n.locales', []))
            ->filter(fn ($meta) => (bool) ($meta['enabled'] ?? false))
            ->reject(fn ($meta, $code) => $code === $default)
            ->map(fn ($meta) => (string) ($meta['native_name'] ?? $meta['name'] ?? ''))
            ->all();
    }
}
