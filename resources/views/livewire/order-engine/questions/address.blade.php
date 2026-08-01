{{--
    L'adresse débloque la preuve de disponibilité — « 14 peintres à moins de 8 km, premier créneau
    aujourd'hui à 16 h ». C'est pour cela qu'elle est posée en FIN de questionnaire et non au
    début : elle récompense le client d'être allé jusque-là, elle ne le filtre pas à l'entrée.
--}}
<input type="text" wire:model.live.debounce.400ms="value"
    id="{{ $question->code }}"
    autocomplete="street-address"
    placeholder="{{ $question->placeholder ?: 'Rue, numéro, code postal' }}"
    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-[15px] text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
