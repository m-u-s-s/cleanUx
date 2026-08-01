{{--
    Le chantier multi-métiers : ce qui est prévu, dans quel ordre, et pour combien.

    Une douche à refaire, c'est un plombier ET un carreleur. Le client ne gère qu'une commande, un
    suivi, un interlocuteur — mais il doit VOIR le séquencement, sinon il croit que tout le monde
    arrive le même matin.
--}}
<section class="rounded-2xl border border-slate-200 bg-white p-5" aria-labelledby="chantier-titre">

    <h2 id="chantier-titre" class="text-lg font-semibold text-slate-900">Votre chantier</h2>
    <p class="mt-0.5 text-sm text-slate-500">
        Une seule commande, un seul suivi — même si plusieurs professionnels interviennent.
    </p>

    @if ($sequenceError)
        {{-- Le refus est AFFICHÉ : corriger en silence ferait croire que le geste a été pris. --}}
        <p class="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900" role="alert">
            {{ $sequenceError }}
        </p>
    @endif

    {{-- ─── La timeline verticale ───────────────────────────────────────────────────────── --}}
    @if ($this->timeline()->isNotEmpty())
        <ol class="mt-4 space-y-2" x-data="bundleSorter()" x-init="boot()" data-bundle-root>
            @foreach ($this->timeline() as $step)
                <li draggable="true" data-item-id="{{ $step['item']->id }}"
                    class="relative rounded-xl border border-slate-200 bg-slate-50/60 p-4 pl-11"
                    wire:key="bundle-{{ $step['item']->id }}">

                    {{-- Le rang, visible : c'est l'ordre d'intervention, pas une décoration. --}}
                    <span aria-hidden="true"
                        class="absolute left-3 top-4 flex h-6 w-6 items-center justify-center rounded-full bg-slate-900 text-xs font-medium text-white">
                        {{ $loop->iteration }}
                    </span>

                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-[15px] font-medium text-slate-900">{{ $step['trade']?->name }}</p>
                            <p class="mt-0.5 text-sm text-slate-600">
                                {{ $step['starts_at']->translatedFormat('l j F, H\hi') }}
                                — {{ $step['ends_at']->format('H\hi') }}
                            </p>

                            @if ($step['waits_for'])
                                {{--
                                    Le délai n'est pas une marge de confort : une chape sèche, et le
                                    carreleur ne pose pas sur du frais. Le dire évite au client de
                                    croire à une lenteur.
                                --}}
                                <p class="mt-1 text-xs text-slate-500">
                                    Après « {{ $step['waits_for'] }} »
                                    @if ($step['gap_min'] > 0)
                                        · {{ $step['gap_min'] >= 1440
                                            ? round($step['gap_min'] / 1440).' j de séchage'
                                            : $step['gap_min'].' min d’attente' }}
                                    @endif
                                </p>
                            @endif
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            {{-- Les flèches restent : le glisser-déposer ne fonctionne ni au
                                 clavier ni avec un lecteur d'écran. --}}
                            <button type="button" aria-label="Monter" @disabled($loop->first)
                                x-on:click="nudge('{{ $step['item']->id }}', -1)"
                                class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-200 disabled:opacity-30">↑</button>
                            <button type="button" aria-label="Descendre" @disabled($loop->last)
                                x-on:click="nudge('{{ $step['item']->id }}', 1)"
                                class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-200 disabled:opacity-30">↓</button>
                            <button type="button" wire:click="removeService({{ $step['item']->id }})"
                                aria-label="Retirer {{ $step['trade']?->name }}"
                                class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-400 hover:bg-rose-50 hover:text-rose-700">×</button>
                        </div>
                    </div>
                </li>
            @endforeach
        </ol>
    @endif

    {{-- ─── Ajouter un service ──────────────────────────────────────────────────────────── --}}
    @if ($this->bundleSuggestions()->isNotEmpty())
        <div class="mt-5 border-t border-slate-100 pt-4">
            <p class="text-sm font-medium text-slate-900">Souvent commandé avec</p>

            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($this->bundleSuggestions() as $suggestion)
                    <button type="button" wire:click="addService({{ $suggestion['trade']->id }})"
                        class="min-h-[44px] rounded-xl border border-slate-300 bg-white px-4 text-sm font-medium text-slate-800 hover:bg-slate-50">
                        + {{ $suggestion['trade']->name }}
                    </button>
                @endforeach
            </div>
        </div>
    @endif

    <button type="button" wire:click="backToTrades"
        class="mt-4 min-h-[48px] w-full rounded-xl border border-dashed border-slate-300 text-sm font-medium text-slate-700 hover:bg-slate-50">
        Ajouter un autre service
    </button>

    {{-- ─── Le devis consolidé, dépliable ───────────────────────────────────────────────── --}}
    @if ($this->bundleQuote())
        <div class="mt-5 border-t border-slate-100 pt-4">
            @foreach ($this->bundleQuote()['items'] as $line)
                <details class="mb-2 rounded-xl bg-slate-50/60 p-3">
                    <summary class="flex cursor-pointer items-baseline justify-between gap-3 text-sm text-slate-800">
                        <span class="min-w-0 truncate">{{ $line['trade']?->name }}</span>
                        <span class="shrink-0 tabular-nums">
                            @if ($line['quote']->quoteOnly)
                                Sur devis
                            @else
                                {{ number_format($line['quote']->minCents / 100, 0, ',', ' ') }} €
                            @endif
                        </span>
                    </summary>

                    <ul class="mt-2 space-y-1 border-t border-slate-200 pt-2 text-sm">
                        @foreach ($line['quote']->lines as $detail)
                            <li class="flex items-baseline justify-between gap-3">
                                <span class="min-w-0 text-slate-600">{{ $detail['label'] }}</span>
                                <span class="shrink-0 tabular-nums text-slate-700">
                                    {{ number_format($detail['min_cents'] / 100, 0, ',', ' ') }} €
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endforeach

            {{-- La remise groupée sur SA propre ligne : une remise que le client ne voit pas ne le
                 décide à rien. --}}
            @foreach ($this->bundleQuote()['order']->lines as $orderLine)
                @if (($orderLine['min_cents'] ?? 0) < 0)
                    <div class="flex items-baseline justify-between gap-3 px-3 text-sm text-emerald-700">
                        <span>{{ $orderLine['label'] }}</span>
                        <span class="tabular-nums">{{ number_format($orderLine['min_cents'] / 100, 0, ',', ' ') }} €</span>
                    </div>
                @endif
            @endforeach

            <div class="mt-3 flex items-baseline justify-between border-t border-slate-200 px-3 pt-3">
                <span class="text-sm font-medium text-slate-900">Total du chantier</span>
                <span class="text-xl font-semibold tabular-nums text-slate-900">
                    @if ($this->bundleQuote()['order']->quoteOnly)
                        Sur devis
                    @elseif ($this->bundleQuote()['order']->isExact())
                        {{ number_format($this->bundleQuote()['order']->minCents / 100, 0, ',', ' ') }} €
                    @else
                        {{ number_format($this->bundleQuote()['order']->minCents / 100, 0, ',', ' ') }}–{{ number_format($this->bundleQuote()['order']->maxCents / 100, 0, ',', ' ') }} €
                    @endif
                </span>
            </div>
        </div>
    @endif
</section>

@push('scripts')
<script>
    window.bundleSorter = () => ({
        dragged: null,

        boot() {
            const root = this.$el;

            root.addEventListener('dragstart', (e) => {
                this.dragged = e.target.closest('[data-item-id]');
                if (this.dragged) {
                    e.dataTransfer.effectAllowed = 'move';
                    this.dragged.style.opacity = '0.4';
                }
            });

            root.addEventListener('dragend', () => {
                if (this.dragged) {
                    this.dragged.style.opacity = '';
                }
                this.dragged = null;
            });

            root.addEventListener('dragover', (e) => {
                e.preventDefault();
                const over = e.target.closest('[data-item-id]');

                if (! over || ! this.dragged || over === this.dragged) {
                    return;
                }

                const box = over.getBoundingClientRect();
                const after = (e.clientY - box.top) > (box.height / 2);
                over.parentNode.insertBefore(this.dragged, after ? over.nextSibling : over);
            });

            root.addEventListener('drop', (e) => {
                e.preventDefault();
                this.commit();
            });
        },

        /** Le même déplacement, au clavier : c'est la seule voie accessible. */
        nudge(itemId, direction) {
            const items = Array.from(this.$el.querySelectorAll('[data-item-id]'));
            const index = items.findIndex((el) => el.dataset.itemId === String(itemId));
            const target = index + direction;

            if (index < 0 || target < 0 || target >= items.length) {
                return;
            }

            const ids = items.map((el) => el.dataset.itemId);
            [ids[index], ids[target]] = [ids[target], ids[index]];

            this.$wire.reorderServices(ids);
        },

        commit() {
            // Le serveur revalide : une dépendance de chantier ne se contourne pas depuis le
            // navigateur, et il renvoie un refus lisible plutôt qu'un ordre corrigé en douce.
            this.$wire.reorderServices(
                Array.from(this.$el.querySelectorAll('[data-item-id]')).map((el) => el.dataset.itemId),
            );
        },
    });
</script>
@endpush
