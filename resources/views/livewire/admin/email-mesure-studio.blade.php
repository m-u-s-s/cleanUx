{{-- CE QUI EST REELLEMENT PARTI. Les ouvertures et les clics ne sont pas mesures : les colonnes
     existent, aucun webhook ne les alimente. Un taux d ouverture serait un zero permanent. --}}
<div class="space-y-4">
    <x-filter-panel title="Fenêtre d’observation" subtitle="Tout ce qui suit porte sur cette période.">
        <label class="sr-only" for="fenetre-mesure">Fenêtre en jours</label>
        <select id="fenetre-mesure" wire:model.live="fenetre" class="max-w-xs">
            <option value="7">7 derniers jours</option>
            <option value="30">30 derniers jours</option>
            <option value="90">90 derniers jours</option>
            <option value="365">12 derniers mois</option>
        </select>
    </x-filter-panel>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-kpi-card title="E-mails partis" icon="📨" :value="number_format($this->reperes['envoyes'], 0, ',', ' ')" />
        <x-kpi-card title="Gabarits employés" icon="🧩" :value="$this->reperes['gabarits']" />
        <x-kpi-card title="Destinataires" icon="👥" :value="number_format($this->reperes['destinataires'], 0, ',', ' ')" />
        <x-kpi-card title="Échecs" icon="⚠️" :value="$this->reperes['echecs']" />
    </div>

    <x-table-shell title="Envois par gabarit" subtitle="Du plus actif au plus discret, sur la fenêtre choisie.">
        <table class="min-w-full text-sm">
            <thead>
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Gabarit</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Code</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Envois</th>
                    <th scope="col" class="px-4 py-3 text-right font-semibold">Destinataires</th>
                </tr>
            </thead>

            <tbody>
                @forelse($this->parGabarit as $ligne)
                    <tr wire:key="mesure-{{ $ligne->template_code }}">
                        <td class="px-4 py-3 font-semibold">{{ $ligne->nom }}</td>
                        <td class="px-4 py-3 opacity-70">{{ $ligne->template_code }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($ligne->envois, 0, ',', ' ') }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ number_format($ligne->destinataires, 0, ',', ' ') }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <x-empty-state icon="📭" title="Aucun envoi sur la période"
                                           message="Aucun e-mail n’est parti depuis cette plateforme sur la fenêtre choisie." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-shell>

    <x-table-shell title="Derniers envois" subtitle="Les quinze plus récents, quel que soit leur statut.">
        <table class="min-w-full text-sm">
            <thead>
                <tr>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Date</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Destinataire</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Objet</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Gabarit</th>
                    <th scope="col" class="px-4 py-3 text-left font-semibold">Statut</th>
                </tr>
            </thead>

            <tbody>
                @forelse($this->derniers as $message)
                    <tr wire:key="msg-{{ $message->id }}">
                        <td class="px-4 py-3 whitespace-nowrap">{{ $message->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $message->to_email }}</td>
                        <td class="px-4 py-3">{{ $message->subject }}</td>
                        <td class="px-4 py-3 opacity-70">{{ $message->template_code ?? '—' }}</td>
                        <td class="px-4 py-3"><x-badge :status="$message->status" /></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <x-empty-state icon="📭" title="Aucun envoi"
                                           message="Le registre des envois est vide." />
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-table-shell>

    {{-- CE QU ON NE MESURE PAS, DIT A VOIX HAUTE. Un tableau de bord qui tait ses angles morts
         laisse croire que ses zeros sont des resultats. --}}
    <x-app-card title="Ce qui n’est pas encore mesuré"
                subtitle="Nommé ici plutôt qu’affiché à zéro : un compteur toujours nul se lit comme un résultat.">
        <ul class="space-y-2 text-sm">
            <li>
                <span class="font-semibold">Ouvertures et clics.</span>
                Les colonnes existent sur les envois et la table des événements du prestataire aussi —
                mais aucune route ni aucun écrivain ne les alimente. Il manque le point d’entrée qui
                reçoit les retours du service d’expédition.
            </li>
            <li>
                <span class="font-semibold">Rebonds et plaintes.</span>
                Même chaîne, même manque : ils arrivent par le même canal.
            </li>
        </ul>
    </x-app-card>
</div>
