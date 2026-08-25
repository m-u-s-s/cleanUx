{{--
    LA NOTIFICATION DE VERRE.

    88 fichiers appellent `dispatch('toast', ...)` : ce composant est le seul rendu de
    toutes les notifications du produit. Le refondre les transforme toutes d'un coup.

    Ce qu'il gagne ici : la matiere du theme au lieu d'un pastel en dur, une icone
    tracee au lieu d'un emoji, et l'EMPILEMENT — l'ancien ecrasait une notification par
    la suivante, si bien qu'une action qui en emettait deux n'en montrait qu'une.

    Le contrat d'appel ne change pas : `dispatch('toast', message)` ou
    `dispatch('toast', { message, type })`, avec un second argument de repli.
--}}
<div
    x-data="{
        piles: [],
        suivant: 1,

        open(payload = {}, fallbackType = 'success') {
            const message = typeof payload === 'string'
                ? payload
                : (payload.message || payload.msg || '');

            if (! message) return;

            const type = typeof payload === 'string'
                ? (fallbackType || 'success')
                : (payload.type || fallbackType || 'success');

            const id = this.suivant++;
            this.piles.push({ id, message, type, sortie: false });

            // Au-dela de trois, la plus ancienne cede sa place : une colonne qui
            // deborde de l'ecran ne notifie plus, elle masque.
            if (this.piles.length > 3) this.fermer(this.piles[0].id);

            try {
                new Audio(type === 'success' ? '/sounds/success.mp3' : '/sounds/error.mp3').play();
            } catch (e) { /* lecture refusee tant que l'utilisateur n'a pas interagi */ }

            setTimeout(() => this.fermer(id), 4200);
        },

        fermer(id) {
            const item = this.piles.find((p) => p.id === id);
            if (! item || item.sortie) return;

            // On marque la sortie avant de retirer : sans ce temps, l'element
            // disparait d'un coup et l'animation de sortie ne se voit jamais.
            item.sortie = true;
            setTimeout(() => { this.piles = this.piles.filter((p) => p.id !== id); }, 260);
        },
    }"
    x-init="Livewire.on('toast', (...args) => open(args[0] || {}, args[1] || 'success'))"
    {{-- Le second canal : `window.brioToast(...)`, pour les scripts de vue qui n'ont aucun
         composant Livewire sous la main. Ils appelaient `alert()` faute de mieux. --}}
    x-on:brio-toast.window="open($event.detail || {}, 'success')"
    class="brio-toasts"
    role="status"
    aria-live="polite"
    aria-atomic="false"
>
    <template x-for="item in piles" :key="item.id">
        <div class="brio-toast" :class="[`brio-toast-${item.type}`, item.sortie && 'brio-toast-sortie']">
            <span class="brio-toast-aura" aria-hidden="true"></span>

            <div class="brio-toast-icone" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <template x-if="item.type === 'success'"><polyline points="20 6 9 17 4 12" /></template>
                    <template x-if="item.type === 'error' || item.type === 'danger'">
                        <g><line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" /></g>
                    </template>
                    <template x-if="item.type === 'warning'">
                        <g><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" /></g>
                    </template>
                    <template x-if="! ['success','error','danger','warning'].includes(item.type)">
                        <g><circle cx="12" cy="12" r="9" /><line x1="12" y1="16" x2="12" y2="12" /><line x1="12" y1="8" x2="12.01" y2="8" /></g>
                    </template>
                </svg>
            </div>

            <p class="brio-toast-texte" x-text="item.message"></p>

            <button
                type="button"
                class="brio-toast-fermer"
                @click="fermer(item.id)"
                :aria-label="`Fermer : ${item.message}`"
            >
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" />
                </svg>
            </button>

            <span class="brio-toast-jauge" aria-hidden="true"></span>
        </div>
    </template>
</div>
