<div class="cu-page space-y-10 pb-20">

    {{-- ═══════════════════════════════════════════════════════
         HEADER
         ═══════════════════════════════════════════════════════ --}}
    <div class="cu-page-header">
        <div>
            <p class="cu-eyebrow">Design System</p>
            <h1 class="cu-section-title mt-2 text-3xl">CleanUx Component Library</h1>
            <p class="cu-section-subtitle">Composants réutilisables, tokens de design, et patterns UI.</p>
        </div>
    </div>

    {{-- ═══════════════════════════════════════════════════════
         1 — COLORS
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Couleurs" subtitle="Palette de marque et couleurs sémantiques">
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3 mt-4">
            @foreach([
                ['Brand 500', 'bg-brand-500', '#6366f1'],
                ['Brand 600', 'bg-brand-600', '#4f46e5'],
                ['Brand 700', 'bg-brand-700', '#4338ca'],
                ['Amber', 'bg-amber-400', '#ffb648'],
                ['Emerald', 'bg-emerald-500', '#10b981'],
                ['Rose', 'bg-rose-500', '#ef4444'],
                ['Sky', 'bg-sky-500', '#0ea5e9'],
                ['Slate 900', 'bg-slate-900', '#0f172a'],
            ] as [$name, $class, $hex])
                <div class="text-center">
                    <div class="{{ $class }} h-14 w-full rounded-xl shadow-sm"></div>
                    <p class="mt-2 text-xs font-bold text-slate-700">{{ $name }}</p>
                    <p class="text-[10px] text-slate-400 font-mono">{{ $hex }}</p>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════
         2 — TYPOGRAPHY
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Typographie" subtitle="Figtree (body) · Space Grotesk (display) · JetBrains Mono (code)">
        <div class="mt-4 space-y-4">
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-1">Display — Space Grotesk</p>
                <h2 style="font-family: var(--cx-display);" class="text-4xl font-bold tracking-tight text-slate-900">The quick brown fox</h2>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-1">Body — Figtree</p>
                <p class="text-base text-slate-700 leading-7">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam.</p>
            </div>
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-1">Mono — JetBrains Mono</p>
                <code class="cu-code-block text-sm">const booking = await api.post('/bookings', { service_id: 42 });</code>
            </div>
        </div>
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════
         3 — BUTTONS
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Buttons" subtitle="x-ui.button — 8 variants × 5 sizes">
        <div class="mt-4 space-y-6">
            {{-- Variants --}}
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-3">Variants</p>
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button variant="primary">Primary</x-ui.button>
                    <x-ui.button variant="secondary">Secondary</x-ui.button>
                    <x-ui.button variant="ghost">Ghost</x-ui.button>
                    <x-ui.button variant="danger">Danger</x-ui.button>
                    <x-ui.button variant="success">Success</x-ui.button>
                    <x-ui.button variant="outline">Outline</x-ui.button>
                    <x-ui.button variant="link">Link</x-ui.button>
                    <x-ui.button variant="amber">Amber</x-ui.button>
                </div>
            </div>
            {{-- Sizes --}}
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-3">Sizes</p>
                <div class="flex flex-wrap items-end gap-3">
                    <x-ui.button variant="primary" size="xs">XS</x-ui.button>
                    <x-ui.button variant="primary" size="sm">Small</x-ui.button>
                    <x-ui.button variant="primary" size="md">Medium</x-ui.button>
                    <x-ui.button variant="primary" size="lg">Large</x-ui.button>
                    <x-ui.button variant="primary" size="xl">XL</x-ui.button>
                </div>
            </div>
            {{-- With icons --}}
            <div>
                <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-3">With Icons</p>
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.button variant="primary" icon="plus">Ajouter</x-ui.button>
                    <x-ui.button variant="danger" icon="x-mark">Supprimer</x-ui.button>
                    <x-ui.button variant="secondary" icon="arrow-right" icon-position="right">Suivant</x-ui.button>
                    <x-ui.button variant="primary" :loading="true">Loading</x-ui.button>
                </div>
            </div>
            {{-- Code --}}
            <div class="cu-note">
                <code class="text-xs">&lt;x-ui.button variant="primary" size="md" icon="plus"&gt;Label&lt;/x-ui.button&gt;</code>
            </div>
        </div>
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════
         4 — BADGES
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Badges" subtitle="x-ui.badge — 5 tones">
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <x-ui.badge tone="neutral" label="Neutral" />
            <x-ui.badge tone="blue" label="Blue" />
            <x-ui.badge tone="green" label="Green" />
            <x-ui.badge tone="amber" label="Amber" />
            <x-ui.badge tone="red" label="Red" />
        </div>
        <div class="cu-note mt-4">
            <code class="text-xs">&lt;x-ui.badge tone="green" label="Active" /&gt;</code>
        </div>
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════
         5 — CARDS
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Cards" subtitle="x-ui.card — default, amber, danger, dark tones">
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-ui.card title="Default Card" subtitle="Standard white card">
                <p class="text-sm text-slate-600">Card content goes here.</p>
            </x-ui.card>
            <x-ui.card title="Amber Tone" tone="amber" subtitle="For warnings">
                <p class="text-sm text-amber-800">Attention needed content.</p>
            </x-ui.card>
            <x-ui.card title="Danger Tone" tone="danger" subtitle="For errors">
                <p class="text-sm text-rose-800">Critical alert content.</p>
            </x-ui.card>
            <x-ui.card title="With Actions" subtitle="Header actions slot">
                <x-slot:actions>
                    <x-ui.button variant="ghost" size="sm" icon="cog">Settings</x-ui.button>
                </x-slot:actions>
                <p class="text-sm text-slate-600">Card with action buttons.</p>
            </x-ui.card>
        </div>
        <div class="cu-note mt-4">
            <code class="text-xs">&lt;x-ui.card title="Title" subtitle="Sub" tone="amber"&gt;Content&lt;/x-ui.card&gt;</code>
        </div>
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════
         6 — STATS / KPI
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Stats / KPI" subtitle="x-ui.stat — Multi-tone stat cards">
        <div class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-4">
            <x-ui.stat title="Réservations" value="142" hint="+12% vs hier" heroicon="calendar" tone="blue" />
            <x-ui.stat title="Revenus" value="€8,420" trend="+23%" heroicon="currency-euro" tone="emerald" />
            <x-ui.stat title="Litiges" value="3" heroicon="exclamation-triangle" tone="amber" />
            <x-ui.stat title="Annulations" value="7" heroicon="x-mark" tone="red" />
        </div>
        <div class="cu-note mt-4">
            <code class="text-xs">&lt;x-ui.stat title="Label" value="42" heroicon="star" tone="emerald" trend="+5%" /&gt;</code>
        </div>
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════
         7 — ICONS
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Icons" subtitle="x-ui.icon — Heroicons v2 outline (90+ icons)">
        <div class="mt-4 grid grid-cols-6 sm:grid-cols-8 lg:grid-cols-12 gap-4">
            @foreach(['home','plus','check','calendar','clock','user','users','star','heart','bell','chat-bubble','envelope','phone','chart-bar','cog','cube','rocket','shield-check','sparkles','gift','briefcase','wrench','camera','map-pin','globe','key','bolt','fire','magnifying-glass','arrow-right','credit-card','currency-euro','wallet','banknotes'] as $icon)
                <div class="flex flex-col items-center gap-1.5">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-50 text-slate-600">
                        <x-ui.icon :name="$icon" class="w-5 h-5" />
                    </div>
                    <span class="text-[9px] font-mono text-slate-400 truncate w-full text-center">{{ $icon }}</span>
                </div>
            @endforeach
        </div>
        <div class="cu-note mt-4">
            <code class="text-xs">&lt;x-ui.icon name="star" class="w-5 h-5" /&gt;</code>
        </div>
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════
         8 — FORM INPUTS
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Form Inputs" subtitle="Styled via CSS tool-mode layer">
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="ui-label">Email</label>
                <input type="email" placeholder="votre@email.com" class="ui-input">
                <p class="ui-help">Votre adresse email professionnelle</p>
            </div>
            <div>
                <label class="ui-label">Mot de passe</label>
                <input type="password" placeholder="••••••••" class="ui-input">
            </div>
            <div>
                <label class="ui-label">Sélection</label>
                <select class="ui-input">
                    <option>Nettoyage</option>
                    <option>Peinture</option>
                    <option>Jardinage</option>
                </select>
            </div>
            <div>
                <label class="ui-label">Avec erreur</label>
                <input type="text" value="invalide" class="ui-input ui-input-error">
                <p class="ui-error-msg">Ce champ est invalide</p>
            </div>
        </div>
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════
         9 — EMPTY STATES
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Empty States" subtitle="Pattern pour les listes vides">
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="ui-empty">
                <div class="ui-empty-icon">
                    <x-ui.icon name="calendar" class="w-6 h-6" />
                </div>
                <p class="ui-empty-title">Aucune réservation</p>
                <p class="ui-empty-desc">Vos réservations apparaîtront ici.</p>
            </div>
            <div class="cu-empty">
                <div class="cu-empty-icon">
                    <x-ui.icon name="chat-bubble" class="w-6 h-6" />
                </div>
                <p class="mt-3 text-sm font-semibold text-slate-700">Pas de messages</p>
                <p class="mt-1 text-xs text-slate-500">Commencez une conversation.</p>
            </div>
        </div>
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════
         10 — SKELETON LOADERS
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Skeleton Loaders" subtitle="ui-skeleton / cu-skeleton shimmer patterns">
        <div class="mt-4 space-y-3">
            <div class="ui-skeleton h-6 w-48 rounded"></div>
            <div class="ui-skeleton h-4 w-full rounded"></div>
            <div class="ui-skeleton h-4 w-3/4 rounded"></div>
            <div class="flex gap-3 mt-4">
                <div class="cu-skeleton h-20 w-20 rounded-2xl"></div>
                <div class="flex-1 space-y-2">
                    <div class="cu-skeleton h-4 w-32 rounded"></div>
                    <div class="cu-skeleton h-3 w-full rounded"></div>
                    <div class="cu-skeleton h-3 w-2/3 rounded"></div>
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════
         11 — TOASTS
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Toast Notifications" subtitle="ui-toast — 4 variants">
        <div class="mt-4 space-y-3 max-w-sm">
            <div class="ui-toast-success"><x-ui.icon name="check-circle" class="w-5 h-5 text-emerald-500 shrink-0" /><div><p class="text-sm font-semibold text-slate-900">Succès</p><p class="text-xs text-slate-500">Réservation créée.</p></div></div>
            <div class="ui-toast-error"><x-ui.icon name="x-mark" class="w-5 h-5 text-rose-500 shrink-0" /><div><p class="text-sm font-semibold text-slate-900">Erreur</p><p class="text-xs text-slate-500">Paiement refusé.</p></div></div>
            <div class="ui-toast-warning"><x-ui.icon name="exclamation-triangle" class="w-5 h-5 text-amber-500 shrink-0" /><div><p class="text-sm font-semibold text-slate-900">Attention</p><p class="text-xs text-slate-500">Session expire bientôt.</p></div></div>
            <div class="ui-toast-info"><x-ui.icon name="information-circle" class="w-5 h-5 text-sky-500 shrink-0" /><div><p class="text-sm font-semibold text-slate-900">Info</p><p class="text-xs text-slate-500">Mise à jour disponible.</p></div></div>
        </div>
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════
         12 — SHADOWS
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Shadows" subtitle="Stripe-like shadow scale">
        <div class="mt-4 flex flex-wrap items-center gap-6">
            @foreach(['shadow-soft-xs','shadow-soft-sm','shadow-soft','shadow-soft-md','shadow-soft-lg'] as $shadow)
                <div class="flex h-20 w-20 items-center justify-center rounded-2xl bg-white {{ $shadow }}">
                    <span class="text-[10px] font-mono text-slate-400">{{ str_replace('shadow-', '', $shadow) }}</span>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════
         13 — SPACING & RADIUS
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Border Radius" subtitle="Custom radius scale from tokens.css">
        <div class="mt-4 flex flex-wrap items-end gap-4">
            @foreach([
                ['sm (10px)', 'rounded-[10px]'],
                ['md (14px)', 'rounded-[14px]'],
                ['lg (22px)', 'rounded-[22px]'],
                ['xl (28px)', 'rounded-[28px]'],
                ['pill (999px)', 'rounded-full'],
            ] as [$label, $class])
                <div class="flex flex-col items-center gap-2">
                    <div class="h-16 w-16 border-2 border-brand-300 bg-brand-50 {{ $class }}"></div>
                    <span class="text-[10px] font-mono text-slate-500">{{ $label }}</span>
                </div>
            @endforeach
        </div>
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════
         14 — GLASSMORPHISM
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Glassmorphism" subtitle="cu-glass (light) and cx-glass (dark)">
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="relative h-32 rounded-2xl overflow-hidden" style="background: linear-gradient(135deg, #6366f1, #ffb648);">
                <div class="absolute inset-4 cu-glass rounded-xl flex items-center justify-center">
                    <span class="text-sm font-bold text-slate-900">cu-glass</span>
                </div>
            </div>
            <div class="relative h-32 rounded-2xl overflow-hidden" style="background: linear-gradient(135deg, #070b14, #1e293b);">
                <div class="absolute inset-4 cx-glass rounded-xl flex items-center justify-center">
                    <span class="text-sm font-bold text-white">cx-glass</span>
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- ═══════════════════════════════════════════════════════
         15 — DESIGN TOKENS REFERENCE
         ═══════════════════════════════════════════════════════ --}}
    <x-ui.card title="Design Tokens" subtitle="CSS custom properties from tokens.css">
        <div class="mt-4 overflow-x-auto">
            <table class="ui-table">
                <thead>
                    <tr>
                        <th>Token</th>
                        <th>Value</th>
                        <th>Usage</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach([
                        ['--cx-amber', '#ffb648', 'Brand accent, CTAs, highlights'],
                        ['--cx-cyan', '#4fe3d6', 'Secondary accent, vitrine mode'],
                        ['--cx-violet', '#8b7bff', 'Tertiary accent, gradient stops'],
                        ['--cx-dur-fast', '180ms', 'Micro-interactions, hovers'],
                        ['--cx-dur', '280ms', 'Standard transitions'],
                        ['--cx-dur-slow', '420ms', 'Reveal animations, card lifts'],
                        ['--cx-ease', 'cubic-bezier(.16,1,.3,1)', 'Snappy premium easing'],
                        ['--cx-radius-md', '14px', 'Default component radius'],
                        ['--cu-ink', '#0f172a', 'Tool mode text color'],
                        ['--cu-muted', '#64748b', 'Tool mode muted text (WCAG AA 5.4:1)'],
                    ] as [$token, $value, $usage])
                        <tr>
                            <td class="font-mono text-xs text-brand-600">{{ $token }}</td>
                            <td class="font-mono text-xs">{{ $value }}</td>
                            <td class="text-xs text-slate-500">{{ $usage }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>

</div>
