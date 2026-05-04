<div class="py-6">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
        @include('livewire.employe.teamlead.hero')

        <div class="grid grid-cols-1 lg:grid-cols-[320px_minmax(0,1fr)] gap-6">
            @include('livewire.employe.teamlead.selector')

            <div class="space-y-6">
                @if($selectedSegment)
                    @include('livewire.employe.teamlead.assignment-panel')
                    @include('livewire.employe.teamlead.member-status-panel')
                    @include('livewire.employe.teamlead.reinforcement-panel')
                @else
                    @include('livewire.employe.teamlead.empty-state')
                @endif

                @include('livewire.employe.teamlead.recent-reinforcements')
            </div>
        </div>
    </div>
</div>
