@if (session('success'))
    <div role="alert" class="brio-alerte brio-alerte-success">
        {{ session('success') }}
    </div>
@endif
