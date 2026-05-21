<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <p class="text-sm font-bold uppercase text-indigo-600">Voice of Customer</p>
                <h1 class="text-2xl font-black text-slate-900">Net Promoter Score</h1>
                <p class="text-sm text-slate-500">Suivi satisfaction client (0-10 promoteurs/passifs/détracteurs).</p>
            </div>
            <a href="{{ route('admin.dashboard') }}" class="rounded-xl border px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">← Dashboard</a>
        </div>

        <div class="flex gap-2">
            @foreach (['7d' => '7j', '30d' => '30j', '90d' => '90j', 'all' => 'Tout'] as $key => $label)
                <button wire:click="setPeriod('{{ $key }}')"
                        class="rounded-lg border px-3 py-1.5 text-xs font-semibold {{ $period === $key ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-slate-600' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Score principal --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4">
            <div class="lg:col-span-2 rounded-2xl bg-white border shadow-sm p-6">
                <p class="text-xs uppercase font-bold text-slate-500">NPS Score</p>
                @php
                    $nps = $score['nps'];
                    $color = $nps === null ? 'slate' : ($nps >= 50 ? 'emerald' : ($nps >= 0 ? 'amber' : 'rose'));
                @endphp
                <p class="text-6xl font-black text-{{ $color }}-600 mt-2">{{ $nps !== null ? $nps : '—' }}</p>
                <p class="text-xs text-slate-500 mt-2">
                    Calculé sur {{ number_format($score['total']) }} réponses
                    @if ($nps !== null)
                        — {{ $score['promoter_percent'] }}% promoteurs, {{ $score['detractor_percent'] }}% détracteurs
                    @endif
                </p>
                @if ($nps !== null)
                    @if ($nps >= 50)
                        <p class="text-xs font-bold text-emerald-600 mt-2">🌟 Excellent — top quartile mondial</p>
                    @elseif ($nps >= 30)
                        <p class="text-xs font-bold text-emerald-600 mt-2">👍 Très bon</p>
                    @elseif ($nps >= 0)
                        <p class="text-xs font-bold text-amber-600 mt-2">📊 Moyen — à améliorer</p>
                    @else
                        <p class="text-xs font-bold text-rose-600 mt-2">🚨 Critique — action immédiate requise</p>
                    @endif
                @endif
            </div>

            <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-5">
                <p class="text-xs uppercase font-bold text-emerald-700">Promoteurs (9-10)</p>
                <p class="text-3xl font-black text-emerald-700 mt-1">{{ number_format($score['promoters']) }}</p>
                @if ($score['total'] > 0)
                    <p class="text-xs text-emerald-600 mt-1">{{ $score['promoter_percent'] }}%</p>
                @endif
            </div>

            <div class="rounded-2xl bg-rose-50 border border-rose-200 p-5">
                <p class="text-xs uppercase font-bold text-rose-700">Détracteurs (0-6)</p>
                <p class="text-3xl font-black text-rose-700 mt-1">{{ number_format($score['detractors']) }}</p>
                @if ($score['total'] > 0)
                    <p class="text-xs text-rose-600 mt-1">{{ $score['detractor_percent'] }}%</p>
                @endif
            </div>
        </div>

        {{-- Filtres --}}
        <div class="rounded-2xl bg-white border shadow-sm">
            <div class="p-4 flex flex-col md:flex-row gap-3 border-b">
                <select wire:model.live="surveyFilter" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Toutes enquêtes</option>
                    <option value="post_booking">Post-mission</option>
                    <option value="monthly">Mensuel</option>
                    <option value="annual">Annuel</option>
                    <option value="onboarding">Onboarding</option>
                    <option value="churn">Churn</option>
                </select>
                <select wire:model.live="categoryFilter" class="rounded-lg border-slate-300 text-sm">
                    <option value="">Toutes catégories</option>
                    <option value="promoter">Promoteurs</option>
                    <option value="passive">Passifs</option>
                    <option value="detractor">Détracteurs</option>
                </select>
            </div>

            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Utilisateur</th>
                        <th class="px-3 py-2">Enquête</th>
                        <th class="px-3 py-2">Score</th>
                        <th class="px-3 py-2">Catégorie</th>
                        <th class="px-3 py-2">Commentaire</th>
                        <th class="px-3 py-2">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rows as $r)
                        @php $cat = ['promoter'=>'emerald','passive'=>'amber','detractor'=>'rose'][$r->category] ?? 'slate'; @endphp
                        <tr class="border-t hover:bg-slate-50">
                            <td class="px-3 py-2">
                                <p class="font-semibold">{{ $r->user?->name }}</p>
                                <p class="text-xs text-slate-500">{{ $r->user?->email }}</p>
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $r->survey_code }}</td>
                            <td class="px-3 py-2 font-bold text-{{ $cat }}-700">{{ $r->score }}/10</td>
                            <td class="px-3 py-2">
                                <span class="inline-block rounded-full bg-{{ $cat }}-100 text-{{ $cat }}-700 px-2 py-0.5 text-xs font-semibold">{{ $r->category }}</span>
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-600 max-w-xs truncate">{{ $r->comment }}</td>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $r->responded_at?->format('d/m H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-8 text-center text-slate-400">Aucune réponse sur cette période.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $rows->links() }}</div>
        </div>
    </div>
</div>
