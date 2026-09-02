@props(['status'])

@php
    $normalized = strtolower((string) $status);

    $config = match($normalized) {
        'confirme' => ['classes' => 'bg-green-100 text-green-700 border-green-200', 'label' => 'Confirmé'],
        'annule' => ['classes' => 'bg-rose-100 text-rose-700 border-rose-200', 'label' => 'Annulé'],
        'refuse' => ['classes' => 'bg-red-100 text-red-700 border-red-200', 'label' => 'Refusé'],
        'en_attente' => ['classes' => 'bg-yellow-100 text-yellow-700 border-yellow-200', 'label' => 'En attente'],
        // POSE PAR `BookingHub` QUAND UNE SOCIETE EXIGE UNE APPROBATION. Il manquait ici, et le
        // repli imprimait la valeur brute de la base : « Pending approval », en anglais.
        'pending_approval' => ['classes' => 'bg-amber-100 text-amber-700 border-amber-200', 'label' => 'Attente d’approbation'],
        'en_route' => ['classes' => 'bg-blue-100 text-blue-700 border-blue-200', 'label' => 'En route'],
        'sur_place' => ['classes' => 'bg-indigo-100 text-indigo-700 border-indigo-200', 'label' => 'Sur place'],
        'termine' => ['classes' => 'bg-emerald-100 text-emerald-700 border-emerald-200', 'label' => 'Terminé'],
        // PORTES PAR LES SITES DES SOCIETES. Sans eux le repli imprimait « Active » et
        // « Archived » — la valeur brute de la base, en anglais, sur un ecran francais.
        'active' => ['classes' => 'bg-emerald-100 text-emerald-700 border-emerald-200', 'label' => 'Actif'],
        'archived' => ['classes' => 'bg-slate-100 text-slate-600 border-slate-200', 'label' => 'Archivé'],
        // LES QUATRE STATUTS D UNE FACTURE. Le repli imprimait « Issued », « Partial »,
        // « Paid », « Overdue » — la valeur brute, en anglais, sur un ecran de finance francais.
        'issued' => ['classes' => 'bg-blue-100 text-blue-700 border-blue-200', 'label' => 'Émise'],
        'partial' => ['classes' => 'bg-amber-100 text-amber-700 border-amber-200', 'label' => 'Partiellement payée'],
        'paid' => ['classes' => 'bg-emerald-100 text-emerald-700 border-emerald-200', 'label' => 'Payée'],
        'overdue' => ['classes' => 'bg-red-100 text-red-700 border-red-200', 'label' => 'En retard'],
        // LES CINQ ETATS D UNE DEMANDE D APPROBATION B2B. L ecran les peignait a la main avec
        // ses propres classes : deux vocabulaires de couleur pour la meme notion de statut.
        'pending_manager' => ['classes' => 'bg-amber-100 text-amber-700 border-amber-200', 'label' => 'Attente manager'],
        'pending_finance' => ['classes' => 'bg-amber-100 text-amber-700 border-amber-200', 'label' => 'Attente finance'],
        'approved' => ['classes' => 'bg-emerald-100 text-emerald-700 border-emerald-200', 'label' => 'Approuvée'],
        'rejected' => ['classes' => 'bg-red-100 text-red-700 border-red-200', 'label' => 'Refusée'],
        'cancelled' => ['classes' => 'bg-slate-100 text-slate-600 border-slate-200', 'label' => 'Annulée'],
        default => ['classes' => 'bg-gray-100 text-gray-700 border-gray-200', 'label' => ucfirst(str_replace('_', ' ', $normalized))],
    };
@endphp

<span class="inline-flex items-center px-2.5 py-1 rounded-full border text-xs font-semibold {{ $config['classes'] }}">
    {{ $config['label'] }}
</span>