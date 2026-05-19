<?php

namespace App\Livewire\Admin\I18n;

use App\Models\TranslationOverride;
use App\Services\I18n\LocaleResolver;
use App\Services\I18n\TranslationScanner;
use App\Support\ActivityLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithPagination;

class TranslationsCenter extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    public string $locale = 'fr';
    public string $group = 'app';
    public string $search = '';
    public bool $showOnlyOverridden = false;

    public ?string $editingKey = null;
    public string $editingValue = '';

    public function mount(): void
    {
        $this->locale = app(LocaleResolver::class)->default();
    }

    public function startEdit(string $key, string $currentValue): void
    {
        $this->editingKey = $key;
        $this->editingValue = $currentValue;
    }

    public function cancelEdit(): void
    {
        $this->reset(['editingKey', 'editingValue']);
    }

    public function saveOverride(): void
    {
        $this->validate([
            'editingValue' => ['required', 'string', 'max:5000'],
        ]);

        TranslationOverride::updateOrCreate(
            [
                'locale' => $this->locale,
                'group' => $this->group,
                'key' => $this->editingKey,
                'namespace' => '*',
            ],
            [
                'value' => $this->editingValue,
                'is_published' => true,
                'updated_by_user_id' => Auth::id(),
            ]
        );

        ActivityLogger::log('translation.override_saved', null, [
            'locale' => $this->locale,
            'group' => $this->group,
            'key' => $this->editingKey,
        ]);

        $this->dispatch('toast', 'Traduction enregistrée.', 'success');
        $this->reset(['editingKey', 'editingValue']);
    }

    public function removeOverride(string $key): void
    {
        TranslationOverride::query()
            ->where('locale', $this->locale)
            ->where('group', $this->group)
            ->where('key', $key)
            ->where('namespace', '*')
            ->delete();

        ActivityLogger::log('translation.override_removed', null, [
            'locale' => $this->locale,
            'group' => $this->group,
            'key' => $key,
        ]);

        $this->dispatch('toast', 'Override supprimé — fichier disque restauré.', 'success');
    }

    public function render(): View
    {
        $resolver = app(LocaleResolver::class);
        $scanner = app(TranslationScanner::class);

        $localeEntries = $scanner->flattenLocale($this->locale);
        $fallbackEntries = $scanner->flattenLocale($resolver->fallback());

        $groupPrefix = $this->group . '.';
        $rows = [];
        foreach ($localeEntries as $key => $value) {
            if (! str_starts_with($key, $groupPrefix)) {
                continue;
            }
            $rows[$key] = [
                'key' => $key,
                'short_key' => substr($key, strlen($groupPrefix)),
                'value' => $value,
                'fallback' => $fallbackEntries[$key] ?? null,
            ];
        }

        $overrides = TranslationOverride::query()
            ->where('locale', $this->locale)
            ->where('group', $this->group)
            ->pluck('value', 'key')
            ->all();

        foreach ($overrides as $oKey => $oVal) {
            $compoundKey = $groupPrefix . $oKey;
            if (! isset($rows[$compoundKey])) {
                $rows[$compoundKey] = [
                    'key' => $compoundKey,
                    'short_key' => $oKey,
                    'value' => $oVal,
                    'fallback' => $fallbackEntries[$compoundKey] ?? null,
                ];
            }
            $rows[$compoundKey]['overridden'] = true;
            $rows[$compoundKey]['override_value'] = $oVal;
        }

        if ($this->search !== '') {
            $term = mb_strtolower($this->search);
            $rows = array_filter($rows, function ($r) use ($term) {
                return str_contains(mb_strtolower($r['short_key']), $term)
                    || str_contains(mb_strtolower((string) $r['value']), $term)
                    || str_contains(mb_strtolower((string) ($r['fallback'] ?? '')), $term);
            });
        }

        if ($this->showOnlyOverridden) {
            $rows = array_filter($rows, fn ($r) => ! empty($r['overridden']));
        }

        ksort($rows);

        $kpis = [
            'total_keys' => count($localeEntries),
            'overrides_count' => count($overrides),
            'available_locales' => $resolver->availableForSwitcher(),
        ];

        $groups = $this->detectGroups($this->locale);

        return view('livewire.admin.i18n.translations-center', [
            'rows' => $rows,
            'kpis' => $kpis,
            'groups' => $groups,
        ]);
    }

    protected function detectGroups(string $locale): array
    {
        $dir = base_path("lang/{$locale}");
        if (! is_dir($dir)) {
            return [];
        }
        return array_map(
            fn ($f) => pathinfo($f, PATHINFO_FILENAME),
            glob($dir . '/*.php') ?: []
        );
    }
}
