{{--
    SIGNALER UN INCIDENT, ET RIEN D'AUTRE.

    Cette page ouvrait sur le bandeau « Centre de communication & suivi qualite » — un heros
    editorial, des tuiles sans donnee et un memo de process, AVANT le formulaire. C'est du
    contenu d'administration : il reste sur les pages admin qui l'incluent, pas au-dessus du
    signalement d'un prestataire sur le terrain. Meme decision que pour les notifications.
--}}
<div class="space-y-6" data-phase2t-root="true">

<div class="space-y-6">
    @include('livewire.employe.incidents.hero')
    @include('livewire.employe.incidents.flash')
    @include('livewire.employe.incidents.form')
</div>

</div>