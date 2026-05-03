        <section class="grid grid-cols-2 gap-4 xl:grid-cols-6">
            <x-ui.stat title="Total" :value="$statsJour['total']" tone="slate" icon="📦" hint="Missions du jour" />
            <x-ui.stat title="À faire" :value="$statsJour['a_faire']" tone="amber" icon="⏳" hint="Encore à démarrer" />
            <x-ui.stat title="En cours" :value="$statsJour['en_cours']" tone="blue" icon="🚚" hint="En intervention" />
            <x-ui.stat title="Terminées" :value="$statsJour['terminees']" tone="green" icon="✅" hint="Clôturées" />
            <x-ui.stat title="Urgentes" :value="$statsJour['urgentes']" tone="red" icon="🚨" hint="À prioriser" />
            <x-ui.stat title="Progression" :value="$statsJour['progression'] . '%'" tone="emerald" icon="📈" hint="Avancement du jour" />
        </section>
