<x-action-section>
    <x-slot name="title">
        Supprimer le compte
    </x-slot>

    <x-slot name="description">
        Supprimez définitivement votre compte.
    </x-slot>

    <x-slot name="content">
        <div class="max-w-xl text-sm text-gray-600">
            Une fois votre compte supprimé, toutes vos données et ressources associées seront définitivement effacées.
            Pensez à conserver les informations importantes avant de continuer.
        </div>

        <div class="mt-5">
            <x-danger-button wire:click="confirmUserDeletion" wire:loading.attr="disabled">
                Supprimer le compte
            </x-danger-button>
        </div>

        <x-dialog-modal wire:model.live="confirmingUserDeletion">
            <x-slot name="title">
                Supprimer le compte
            </x-slot>

            <x-slot name="content">
                Êtes-vous sûr de vouloir supprimer votre compte ? Cette action est définitive.
                Veuillez entrer votre mot de passe pour confirmer.

                <div class="mt-4" x-data="{}" x-on:confirming-delete-user.window="setTimeout(() => $refs.password.focus(), 250)">
                    <x-input type="password" class="mt-1 block w-3/4"
                                autocomplete="current-password"
                                placeholder="Mot de passe"
                                x-ref="password"
                                wire:model="password"
                                wire:keydown.enter="deleteUser" />

                    <x-input-error for="password" class="mt-2" />
                </div>
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('confirmingUserDeletion')" wire:loading.attr="disabled">
                    Annuler
                </x-secondary-button>

                <x-danger-button class="ms-3" wire:click="deleteUser" wire:loading.attr="disabled">
                    Supprimer définitivement
                </x-danger-button>
            </x-slot>
        </x-dialog-modal>
    </x-slot>
</x-action-section>