<section class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-5">
    @php
        $communicationLinks = [
            [
                'label' => 'Notifications',
                'description' => 'Voir les messages système et actions récentes.',
                'route' => 'notifications',
            ],
            [
                'label' => 'Alertes admin',
                'description' => 'Suivre les problèmes à traiter rapidement.',
                'route' => 'admin.alerts',
            ],
            [
                'label' => 'Emails produit',
                'description' => 'Prévisualiser et piloter les communications.',
                'route' => 'admin.emails',
            ],
            [
                'label' => 'Litiges client',
                'description' => 'Suivre les demandes et réclamations clients.',
                'route' => 'client.litiges',
            ],
            [
                'label' => 'Incident terrain',
                'description' => 'Remonter un problème depuis le terrain.',
                'route' => 'employe.incident',
            ],
        ];
    @endphp

    @foreach ($communicationLinks as $link)
        @if (Route::has($link['route']))
            <a href="{{ route($link['route']) }}"
               class="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-md">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <p class="text-sm font-semibold text-slate-950">{{ $link['label'] }}</p>
                        <p class="mt-1 text-xs leading-5 text-slate-500">{{ $link['description'] }}</p>
                    </div>

                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-600 transition group-hover:bg-slate-950 group-hover:text-white">
                        →
                    </span>
                </div>
            </a>
        @endif
    @endforeach
</section>