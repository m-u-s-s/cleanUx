<x-page-shell
    title="🚀 Platform Readiness"
    subtitle="Vérifiez si votre SaaS est prêt pour la production.">

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

        @foreach($checks as $key => $value)

            @if(is_array($value))
                @foreach($value as $subKey => $subValue)
                    <x-ready-card
                        title="{{ ucfirst($subKey) }}"
                        :status="$subValue" />
                @endforeach
            @else
                <x-ready-card
                    title="{{ ucfirst($key) }}"
                    :status="$value" />
            @endif

        @endforeach

    </div>

</x-page-shell>