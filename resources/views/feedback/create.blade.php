<x-app-layout>
    <div class="mx-auto max-w-4xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
        <x-page-shell
            title="Laisser un feedback"
            subtitle="Partage ton retour sur la prestation pour aider l’équipe à améliorer l’expérience."
            eyebrow="Qualité & satisfaction"
        >
            <x-slot name="actions">
                <a href="{{ route('client.dashboard') }}" class="cu-btn-secondary">← Retour au dashboard</a>
            </x-slot>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="cu-mini-kpi"><p class="cu-mini-kpi-label">Date</p><p class="cu-mini-kpi-value text-lg">{{ $rendezVous->date }}</p></div>
                <div class="cu-mini-kpi"><p class="cu-mini-kpi-label">Heure</p><p class="cu-mini-kpi-value text-lg">{{ $rendezVous->heure }}</p></div>
                <div class="cu-mini-kpi"><p class="cu-mini-kpi-label">Employé</p><p class="cu-mini-kpi-value text-lg">{{ $rendezVous->employe->name ?? '—' }}</p></div>
            </div>
        </x-page-shell>

        <div class="cu-card p-6 cu-fade-up">
            @if ($errors->any())
                <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                    <ul class="list-disc space-y-1 pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('feedback.store', $rendezVous) }}" class="space-y-6">
                @csrf

                <div class="grid grid-cols-1 gap-6 lg:grid-cols-[1.1fr_0.9fr]">
                    <div class="space-y-5">
                        <div>
                            <label for="commentaire" class="cu-field-label">Ton commentaire</label>
                            <textarea
                                name="commentaire"
                                id="commentaire"
                                rows="7"
                                maxlength="1000"
                                class="w-full rounded-[22px] border-slate-300"
                                placeholder="Dis-nous comment s'est passé le rendez-vous..."
                            >{{ old('commentaire') }}</textarea>
                            <p class="mt-2 text-xs text-slate-400">Décris le ressenti global, la ponctualité, la qualité perçue et les points à améliorer.</p>
                        </div>
                    </div>

                    <div class="space-y-5">
                        <div class="rounded-[24px] border border-amber-200 bg-amber-50/90 p-5">
                            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Évaluation</p>
                            <label for="note" class="mt-3 block text-sm font-semibold text-amber-900">Note</label>
                            <select name="note" id="note" class="mt-2 w-full rounded-2xl border-amber-200 bg-white">
                                @for ($i = 1; $i <= 5; $i++)
                                    <option value="{{ $i }}" @selected(old('note', 5) == $i)>{{ $i }}/5</option>
                                @endfor
                            </select>
                            <p class="mt-3 text-sm text-amber-800">Une note claire aide l’équipe à repérer plus vite les retours prioritaires.</p>
                        </div>

                        <div class="cu-note-card">
                            <p class="font-semibold text-slate-900">Conseil</p>
                            <p class="mt-2 leading-6">Un commentaire précis est plus utile qu’un simple “c’était bien”. Tu peux mentionner la ponctualité, le soin apporté et la qualité du résultat.</p>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit" class="cu-btn-primary">💾 Envoyer mon feedback</button>
                    <a href="{{ route('client.dashboard') }}" class="cu-btn-secondary">Annuler</a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
