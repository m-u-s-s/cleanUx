{{--
    Champs natifs, à dessein : le sélecteur du système est meilleur que tout ce qu'on
    réimplémenterait, et il est déjà accessible et localisé.
--}}
<input
    type="{{ $question->type === \App\Support\Domain\QuestionType::TIME ? 'time' : 'date' }}"
    wire:model.live="value"
    id="{{ $question->code }}"
    @if (($question->validation['min'] ?? null)) min="{{ $question->validation['min'] }}" @endif
    @if (($question->validation['max'] ?? null)) max="{{ $question->validation['max'] }}" @endif
    class="w-full rounded-xl border border-slate-300 px-4 py-3 text-[15px] tabular-nums text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
