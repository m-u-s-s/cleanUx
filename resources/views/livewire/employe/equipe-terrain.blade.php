<div class="space-y-8">
    @include('livewire.employe.team.hero')

    <div class="grid gap-6 xl:grid-cols-2">
        @include('livewire.employe.team.led-teams')
        @include('livewire.employe.team.member-teams')
    </div>

    @include('livewire.employe.team.active-assignments')
</div>
