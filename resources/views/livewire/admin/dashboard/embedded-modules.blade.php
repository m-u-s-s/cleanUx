<livewire:admin.rh-quality-scores />
<livewire:admin-feedbacks :scope-id="$adminScopeId" />
<x-admin.recapitulatif-acces />

@if(! $zoneScopeLocked)
    <livewire:admin.gestion-utilisateurs />
    <livewire:admin.agenda-hebdomadaire />
@endif
