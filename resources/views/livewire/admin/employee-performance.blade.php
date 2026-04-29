<div class="bg-white rounded-3xl border p-6">
    <h2 class="text-xl font-bold mb-4">Performance employés</h2>

    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b">
                    <th class="py-2">Employé</th>
                    <th>Missions</th>
                    <th>Terminées</th>
                    <th>Note</th>
                    <th>Retards</th>
                    <th>Marge générée</th>
                </tr>
            </thead>

            <tbody>
                @foreach($employees as $emp)
                    <tr class="border-b">
                        <td class="py-3 font-semibold">{{ $emp['name'] }}</td>
                        <td>{{ $emp['missions'] }}</td>
                        <td>{{ $emp['completed'] }}</td>
                        <td>
                            <span class="font-bold {{ $emp['rating'] >= 4 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $emp['rating'] }}/5
                            </span>
                        </td>
                        <td>
                            <span class="{{ $emp['late'] > 0 ? 'text-red-600' : 'text-green-600' }}">
                                {{ $emp['late'] }}
                            </span>
                        </td>
                        <td class="font-bold">
                            €{{ number_format($emp['margin'], 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>