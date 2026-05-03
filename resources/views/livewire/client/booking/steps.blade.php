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
