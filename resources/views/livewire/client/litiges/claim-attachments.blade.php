@if(is_array($claim->attachments) && count($claim->attachments))
    <div class="space-y-2">
        <p class="text-sm font-bold text-slate-800">Preuves ajoutées</p>

        <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
            @foreach($claim->attachments as $attachment)
                <a
                    href="{{ asset('storage/'.$attachment['path']) }}"
                    target="_blank"
                    class="block overflow-hidden rounded-2xl border border-slate-200 bg-slate-50">
                    <img
                        src="{{ asset('storage/'.$attachment['path']) }}"
                        class="h-24 w-full object-cover"
                        alt="Preuve litige">
                </a>
            @endforeach
        </div>
    </div>
@endif
