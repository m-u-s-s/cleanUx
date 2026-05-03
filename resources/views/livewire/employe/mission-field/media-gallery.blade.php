<section class="rounded-[2rem] border border-slate-200 bg-white p-5 shadow-sm">
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-sm font-semibold uppercase tracking-wide text-purple-600">Preuves</p>
            <h2 class="mt-1 text-xl font-black text-slate-900">Photos & médias</h2>
        </div>

        <span class="rounded-full bg-purple-50 px-3 py-1 text-xs font-black text-purple-700 ring-1 ring-purple-100">
            {{ $mission->media->count() }} fichier(s)
        </span>
    </div>

    @if($mission->media->isEmpty())
        <div class="mt-5 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500">
            Les photos avant/après apparaîtront ici dès qu’elles seront envoyées depuis le bloc exécution.
        </div>
    @else
        <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach($mission->media->take(9) as $media)
                <a href="{{ asset('storage/'.$media->path) }}" target="_blank" class="group overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                    <img src="{{ asset('storage/'.$media->path) }}" alt="Média mission" class="h-28 w-full object-cover transition group-hover:scale-105">
                    <div class="px-3 py-2 text-xs font-bold text-slate-600">
                        {{ $media->media_type ?? 'photo' }}
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</section>
