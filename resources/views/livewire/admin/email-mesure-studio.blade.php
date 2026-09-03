{{-- CE QUI EST REELLEMENT PARTI, ET CE QUI EN EST REVENU. Les remis, ouvertures, clics et
     rebonds viennent du point d entree des retours ; s il n est pas configure, l ecran le dit. --}}
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

    {{-- LES RETOURS DU SERVICE D EXPEDITION. Un zero ici ne veut rien dire tant que le point
         d entree n est pas configure : la carte plus bas dit lequel des deux. --}}
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <x-kpi-card title="Remis" icon="✅" :value="number_format($this->reperes['remis'], 0, ',', ' ')" />
        <x-kpi-card title="Ouverts" icon="👀" :value="number_format($this->reperes['ouverts'], 0, ',', ' ')" />
        <x-kpi-card title="Cliqués" icon="🖱️" :value="number_format($this->reperes['cliques'], 0, ',', ' ')" />
        <x-kpi-card title="Rebonds" icon="↩️" :value="number_format($this->reperes['rebonds'], 0, ',', ' ')" />
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

    {{-- UN ZERO NE VEUT PAS DIRE LA MEME CHOSE selon que le point d entree est branche ou non.
         Le taire laisserait lire « personne n a ouvert » la ou il faut lire « nous l ignorons ». --}}
    <x-app-card title="Retours du service d’expédition"
                subtitle="D’où viennent les remis, les ouvertures, les clics et les rebonds.">
        @if($this->retoursConfigures)
            <p class="text-sm">
                <span class="font-semibold">Le point d’entrée est configuré.</span>
                Les compteurs ci-dessus reflètent ce que le service d’expédition nous a rapporté :
                un zéro se lit alors comme une absence réelle.
            </p>
            <p class="mt-2 text-sm opacity-70">
                Adresse à déclarer chez le fournisseur : <code>{{ url('/webhooks/email/mailgun') }}</code>.
                Chaque appel est vérifié par signature et daté ; un événement rejoué n’est jamais compté deux fois.
            </p>
        @else
            <p class="text-sm">
                <span class="font-semibold">Aucun point d’entrée n’est configuré.</span>
                Les compteurs de remis, d’ouvertures, de clics et de rebonds resteront donc à zéro —
                et ce zéro veut dire « nous ne le savons pas », pas « personne n’a ouvert ».
            </p>
            <p class="mt-2 text-sm opacity-70">
                Pour l’activer : renseigner <code>MAILGUN_WEBHOOK_SIGNING_KEY</code>, puis déclarer
                <code>{{ url('/webhooks/email/mailgun') }}</code> chez le fournisseur. Sans clé, la porte
                reste fermée — un webhook qui accepte des charges non vérifiées est pire que pas de webhook.
            </p>
        @endif
    </x-app-card>
</div>
