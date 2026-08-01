@if ($question->type === \App\Support\Domain\QuestionType::TEXTAREA)
    <textarea wire:model.live.debounce.400ms="value" rows="4"
        id="{{ $question->code }}"
        placeholder="{{ $question->placeholder }}"
        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-[15px] leading-relaxed text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10"></textarea>
@else
    <input type="text" wire:model.live.debounce.400ms="value"
        id="{{ $question->code }}"
        placeholder="{{ $question->placeholder }}"
        class="w-full rounded-xl border border-slate-300 px-4 py-3 text-[15px] text-slate-900 focus:border-slate-900 focus:outline-none focus:ring-2 focus:ring-slate-900/10">
@endif
