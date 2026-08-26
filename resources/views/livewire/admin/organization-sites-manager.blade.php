<div class="space-y-5">
    {{-- LA PAGE COMMENÇAIT PAR UN FORMULAIRE. Son premier titre etait « Ajouter un site » :
         un lecteur d ecran y entrait sans savoir ou il etait. --}}
    <x-page-shell eyebrow="Entreprises" title="Sites des entreprises"
                  subtitle="Les lieux d intervention declares par vos clients entreprises, et leur rattachement." />

    @include('livewire.admin.enterprise.sites.create-form')

    @include('livewire.admin.enterprise.sites.list')
</div>
