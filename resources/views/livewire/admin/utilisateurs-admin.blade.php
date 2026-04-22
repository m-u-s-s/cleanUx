<div class="p-4 md:p-6 space-y-6">
    <div>
        <h2 class="text-2xl font-bold text-blue-900">👥 Utilisateurs admin</h2>
        <p class="text-sm text-gray-500">
            Consultez les clients, employés et administrateurs de la plateforme.
        </p>
    </div>

    <div class="bg-white rounded-2xl shadow border p-4">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <input type="text" wire:model.live="search" placeholder="Nom, email, TVA..."
                   class="w-full border-gray-300 rounded-lg shadow-sm">

            <select wire:model.live="role" class="w-full border-gray-300 rounded-lg shadow-sm">
                <option value="">— Tous les rôles —</option>
                <option value="client">Client</option>
                <option value="employe">Employé</option>
                <option value="entreprise">Entreprise</option>
                <option value="admin">Admin</option>
            </select>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('admin.utilisateurs.manage') }}"
           class="inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            Ouvrir la gestion avancée
        </a>
        <p class="text-sm text-gray-500">Active, désactive et change les rôles sans quitter le backoffice.</p>
    </div>

    <div class="space-y-4">
        @forelse($users as $user)
            <div class="bg-white border rounded-2xl shadow-sm p-4">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <div>
                        <p class="font-semibold text-slate-900">{{ $user->name }}</p>
                        <p class="text-sm text-gray-600">{{ $user->email }}</p>
                        @if($user->tva_number)
                            <p class="text-sm text-gray-500">TVA : {{ $user->tva_number }}</p>
                        @endif
                    </div>

                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-3 py-1 rounded-full bg-slate-100 text-slate-700 text-xs font-semibold capitalize">
                            {{ $user->role }}
                        </span>
                    </div>
                </div>
            </div>
        @empty
            <div class="bg-white border rounded-xl p-6 text-center text-gray-500 italic">
                Aucun utilisateur trouvé.
            </div>
        @endforelse
    </div>

    <div>
        {{ $users->links() }}
    </div>
</div>