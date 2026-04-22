<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        <div class="xl:col-span-2">
            <div class="bg-white rounded-3xl shadow-sm border border-slate-200 overflow-hidden">
                @include('livewire.client.booking.header')

                <div class="px-6 md:px-8 py-8">
                    @if($step === 1)
                        @include('livewire.client.booking.step-1-service')
                    @endif

                    @if($step === 2)
                        @include('livewire.client.booking.step-2-details')
                    @endif

                    @if($step === 3)
                        @include('livewire.client.booking.step-3-coordinates')
                    @endif

                    @if($step === 4)
                        @include('livewire.client.booking.step-4-scheduling')
                    @endif

                    @if($step === 5)
                        @include('livewire.client.booking.step-5-confirmation')
                    @endif

                    @include('livewire.client.booking.actions')
                </div>
            </div>
        </div>

        <div class="xl:col-span-1">
            @include('livewire.client.booking.sidebar')
        </div>
    </div>
</div>
