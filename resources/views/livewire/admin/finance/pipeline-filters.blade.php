<div class="grid gap-3 md:grid-cols-4 xl:grid-cols-9">
    <input wire:model.live.debounce.300ms="search" type="text" placeholder="Recherche" class="rounded-xl border-slate-300 text-sm shadow-sm xl:col-span-2">
    <input wire:model.live="dateFrom" type="date" class="rounded-xl border-slate-300 text-sm shadow-sm">
    <input wire:model.live="dateTo" type="date" class="rounded-xl border-slate-300 text-sm shadow-sm">

    <select wire:model.live="status" class="rounded-xl border-slate-300 text-sm shadow-sm">
        <option value="">Tous statuts</option>
        <option value="en_attente">En attente</option>
        <option value="confirme">Confirmé</option>
        <option value="en_route">En route</option>
        <option value="sur_place">Sur place</option>
        <option value="termine">Terminé</option>
        <option value="annule">Annulé</option>
        <option value="refuse">Refusé</option>
    </select>

    <select wire:model.live="market" class="rounded-xl border-slate-300 text-sm shadow-sm">
        <option value="">Tous marchés</option>
        <option value="particulier">Particulier</option>
        <option value="entreprise">Entreprise</option>
    </select>

    <select wire:model.live="zoneId" class="rounded-xl border-slate-300 text-sm shadow-sm">
        <option value="">Toutes zones</option>
        @foreach($this->zones as $zone)
            <option value="{{ $zone->id }}">{{ $zone->name }}</option>
        @endforeach
    </select>

    <select wire:model.live="serviceId" class="rounded-xl border-slate-300 text-sm shadow-sm">
        <option value="">Tous services</option>
        @foreach($this->services as $service)
            <option value="{{ $service->id }}">{{ $service->name }}</option>
        @endforeach
    </select>

    <select wire:model.live="organizationId" class="rounded-xl border-slate-300 text-sm shadow-sm">
        <option value="">Toutes entreprises</option>
        @foreach($this->organizations as $organization)
            <option value="{{ $organization->id }}">{{ $organization->name }}</option>
        @endforeach
    </select>

    <select wire:model.live="viewMode" class="rounded-xl border-slate-300 text-sm shadow-sm">
        <option value="all">Tout</option>
        <option value="quotes">Devis</option>
        <option value="invoices">Factures</option>
        <option value="cancelled">Annulations</option>
    </select>

    <select wire:model.live="paymentFilter" class="rounded-xl border-slate-300 text-sm shadow-sm xl:col-span-2">
        <option value="">Tous paiements</option>
        <option value="quoted_only">Devis sans facture</option>
        <option value="pending">À encaisser</option>
        <option value="paid">Payé</option>
        <option value="overdue">En retard</option>
    </select>
</div>
