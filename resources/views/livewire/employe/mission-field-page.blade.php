<div class="space-y-6 p-4 pb-28 sm:p-6 lg:p-8">
    @include('livewire.employe.mission-field.hero')

    <div class="grid gap-6 xl:grid-cols-[minmax(0,0.9fr)_minmax(420px,1.1fr)]">
        <div class="space-y-6">
            @include('livewire.employe.mission-field.status-rail')
            @include('livewire.employe.mission-field.client-card')
            @include('livewire.employe.mission-field.checklists')
            @include('livewire.employe.mission-field.media-gallery')
        </div>

        <div class="space-y-6 xl:sticky xl:top-6 xl:self-start">
            @include('livewire.employe.mission-field.action-hub')
        </div>
    </div>

    @include('livewire.employe.mission-field.mobile-action-bar')
</div>
